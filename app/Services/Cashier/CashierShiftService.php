<?php

namespace App\Services\Cashier;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\ExpenseCategory;
use App\Models\OutletExpense;
use App\Models\ShiftCashMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use App\Support\PasswordStepUp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CashierShiftService
{
    public const INITIAL_PETTY_CASH_CENTS = 5_000_000;

    public function __construct(private readonly AuditLogger $audit, private readonly WarehouseCashLedgerService $cashLedger) {}

    public function open(int $outletId, int $warehouseId): CashierShift
    {
        $user = auth()->user();
        abort_unless($user && $user->can('cashier-shifts-open'), 403);

        try {
            return DB::transaction(function () use ($user, $outletId, $warehouseId) {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $outlet = app(OutletAccess::class)->require($lockedUser, $outletId);
                $warehouse = Warehouse::query()->whereKey($warehouseId)->where('outlet_id', $outlet->id)
                    ->where('is_active', true)->where('is_sales_enabled', true)->lockForUpdate()->firstOrFail();

                if (CashierShift::query()->where('open_user_id', $lockedUser->id)->where('status', 'open')->exists()) {
                    throw ValidationException::withMessages(['shift' => __('Tutup shift yang sedang terbuka sebelum membuat shift baru.')]);
                }

                $lastCashEntry = WarehouseCashLedger::query()->where('warehouse_id', $warehouse->id)->latest('id')->first();
                $openingCents = $lastCashEntry
                    ? FixedDecimal::money((string) $lastCashEntry->balance_after)
                    : self::INITIAL_PETTY_CASH_CENTS;

                $shift = CashierShift::query()->create([
                    'user_id' => $lockedUser->id, 'open_user_id' => $lockedUser->id,
                    'outlet_id' => $outlet->id, 'warehouse_id' => $warehouse->id,
                    'status' => 'open', 'opening_cash' => FixedDecimal::moneyText($openingCents),
                    'expected_cash' => FixedDecimal::moneyText(max(0, $openingCents)), 'opened_at' => now(),
                ]);
                if (! $lastCashEntry) {
                    $this->cashLedger->append($warehouse->id, $shift->id, 'shift_opening', self::INITIAL_PETTY_CASH_CENTS, CashierShift::class, $shift->id, 'Saldo awal petty cash');
                }
                $this->audit->record('cashier_shift.opened', $shift, null, ['outlet_id' => $outlet->id, 'warehouse_id' => $warehouse->id, 'opening_cash' => FixedDecimal::moneyText($openingCents)]);

                return $shift;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['shift' => __('Shift aktif sudah dibuka pada sesi lain.')]);
        }
    }

    public function recordMovement(CashierShift $shift, string $type, string $amount, string $note, string $idempotencyKey): ShiftCashMovement
    {
        abort_unless(auth()->user()?->can('cash-management-create'), 403);
        PasswordStepUp::requireRecent();
        $note = trim($note);
        Validator::make(['amount' => $amount, 'note' => $note, 'key' => $idempotencyKey], ['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'], 'note' => ['required', 'string', 'min:3', 'max:2000'], 'key' => ['required', 'string', 'max:96']])->validate();
        if (! in_array($type, ['in', 'out'], true)) {
            throw ValidationException::withMessages(['type' => __('Jenis pergerakan kas tidak valid.')]);
        }
        $cents = FixedDecimal::money($amount);
        if ($cents <= 0 || mb_strlen($note) < 3) {
            throw ValidationException::withMessages(['amount' => __('Jumlah harus lebih dari nol dan catatan wajib diisi.')]);
        }

        return DB::transaction(function () use ($shift, $type, $cents, $note, $idempotencyKey) {
            $locked = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);
            $this->assertShiftAccess($locked);
            abort_unless($locked->outlet_id === (int) session('active_outlet_id'), 404);
            if ($existing = ShiftCashMovement::where('idempotency_key', $idempotencyKey)->first()) {
                if ($existing->cashier_shift_id !== $locked->id || $existing->user_id !== auth()->id() || $existing->type !== $type || $existing->note !== $note || FixedDecimal::money($existing->amount) !== $cents) {
                    throw ValidationException::withMessages(['amount' => __('Kunci pergerakan kas sudah digunakan untuk data lain.')]);
                }

                return $existing;
            }
            abort_unless($locked->status === 'open', 422, __('Shift sudah ditutup.'));
            $movement = ShiftCashMovement::query()->create([
                'cashier_shift_id' => $locked->id, 'user_id' => auth()->id(), 'type' => $type,
                'amount' => FixedDecimal::moneyText($cents), 'note' => $note,
                'idempotency_key' => $idempotencyKey,
            ]);
            $expense = null;
            if ($type === 'out') {
                $category = ExpenseCategory::query()->firstOrCreate(
                    ['outlet_id' => $locked->outlet_id, 'name' => 'Pembelanjaan Operasional Outlet'],
                    ['is_active' => true],
                );
                $expense = OutletExpense::query()->create([
                    'outlet_id' => $locked->outlet_id,
                    'warehouse_id' => $locked->warehouse_id,
                    'user_id' => auth()->id(),
                    'expense_category_id' => $category->id,
                    'cashier_shift_id' => $locked->id,
                    'category' => $category->name,
                    'method' => 'cash',
                    'idempotency_key' => 'cash-movement-out:'.$idempotencyKey,
                    'request_fingerprint' => hash('sha256', json_encode([$locked->id, $cents, $note], JSON_THROW_ON_ERROR)),
                    'amount' => FixedDecimal::moneyText($cents),
                    'reference' => ShiftCashMovement::class.':'.$movement->id,
                    'description' => $note,
                    'occurred_on' => today()->toDateString(),
                ]);
                $movement->update(['reference' => OutletExpense::class.':'.$expense->id]);
            }
            $this->cashLedger->append(
                $locked->warehouse_id,
                $locked->id,
                $type === 'in' ? 'cash_in' : 'expense',
                $cents,
                $expense ? OutletExpense::class : ShiftCashMovement::class,
                $expense?->id ?? $movement->id,
                $note,
            );
            $locked->expected_cash = FixedDecimal::moneyText($this->expectedCashCents($locked));
            $locked->save();
            if ($expense) {
                $this->audit->record('expense.recorded', $expense, null, ['warehouse_id' => $locked->warehouse_id, 'category' => $category->name, 'amount' => $expense->amount, 'method' => 'cash'], ['outlet_id' => $locked->outlet_id]);
            }
            $this->audit->record('cashier_shift.cash_movement_recorded', $movement, null, ['type' => $type, 'amount' => FixedDecimal::moneyText($cents)], ['outlet_id' => $locked->outlet_id]);

            return $movement;
        }, attempts: 3);
    }

    public function close(int $shiftId, string $actualCash, ?string $note = null): CashierShift
    {
        abort_unless(auth()->user()?->can('cashier-shifts-close'), 403);
        PasswordStepUp::requireRecent();

        return $this->finishClosing($shiftId, $actualCash, $note, false);
    }

    public function forceClose(int $shiftId, string $actualCash, string $reason): CashierShift
    {
        abort_unless(auth()->user()?->can('cashier-shifts-force-close'), 403);
        PasswordStepUp::requireRecent();
        Validator::make(['actual_cash' => $actualCash, 'reason' => trim($reason)], ['actual_cash' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'], 'reason' => ['required', 'string', 'min:3', 'max:2000']])->validate();

        return $this->finishClosing($shiftId, $actualCash, trim($reason), true);
    }

    private function finishClosing(int $shiftId, string $actualCash, ?string $note, bool $force): CashierShift
    {
        $actualCents = FixedDecimal::money($actualCash);

        return DB::transaction(function () use ($shiftId, $actualCents, $note, $force) {
            $shift = CashierShift::query()->lockForUpdate()->findOrFail($shiftId);
            if ($force) {
                abort_unless($shift->outlet_id === (int) session('active_outlet_id'), 404);
                app(OutletAccess::class)->require(auth()->user(), $shift->outlet_id);
            } else {
                $this->assertShiftAccess($shift);
            }
            abort_unless($shift->status === 'open', 422, __('Shift sudah ditutup.'));
            $expectedCents = $this->expectedCashCents($shift);
            $difference = $actualCents - $expectedCents;
            if ($difference !== 0 && mb_strlen((string) $note) < 3) {
                throw ValidationException::withMessages(['note' => __('Catatan wajib diisi saat terdapat selisih kas.')]);
            }

            if ($difference > 0) {
                $this->cashLedger->append($shift->warehouse_id, $shift->id, 'cash_in', $difference, CashierShift::class, $shift->id, 'Penyesuaian hasil hitung kas shift');
            } elseif ($difference < 0) {
                $this->cashLedger->append($shift->warehouse_id, $shift->id, 'cash_out', abs($difference), CashierShift::class, $shift->id, 'Penyesuaian hasil hitung kas shift');
            }

            $before = $shift->only(['status', 'expected_cash']);
            $shift->forceFill([
                'status' => 'closed', 'expected_cash' => FixedDecimal::moneyText($expectedCents),
                'actual_cash' => FixedDecimal::moneyText($actualCents), 'cash_difference' => FixedDecimal::moneyText($difference),
                'closed_at' => now(), 'closed_by' => auth()->id(), 'closing_note' => $note, 'open_user_id' => null,
                'is_force_closed' => $force,
            ])->save();
            Cart::query()->where('cashier_shift_id', $shift->id)->whereIn('status', ['active', 'held'])->update(['status' => 'expired', 'updated_at' => now()]);
            $this->audit->record($force ? 'cashier_shift.force_closed' : 'cashier_shift.closed', $shift, $before, $shift->only(['status', 'expected_cash', 'actual_cash', 'cash_difference', 'closed_at', 'is_force_closed', 'closing_note']), ['outlet_id' => $shift->outlet_id]);

            return $shift->refresh();
        }, attempts: 3);
    }

    public function expectedCashCents(CashierShift $shift): int
    {
        return max(0, $this->cashPositionCents($shift));
    }

    public function cashPositionCents(CashierShift $shift): int
    {
        $balance = WarehouseCashLedger::query()
            ->where('warehouse_id', $shift->warehouse_id)
            ->latest('id')
            ->value('balance_after');

        return $balance === null
            ? FixedDecimal::money((string) $shift->opening_cash)
            : FixedDecimal::money((string) $balance);
    }

    private function assertShiftAccess(CashierShift $shift): void
    {
        $user = auth()->user();
        if ($shift->user_id !== $user?->id) {
            abort(404);
        }
        $currentOutlet = request()->attributes->get('api_outlet_id') ?? (int) session('active_outlet_id');
        abort_unless($shift->outlet_id === (int) $currentOutlet, 404);
        app(OutletAccess::class)->require($user, $shift->outlet_id);
    }
}
