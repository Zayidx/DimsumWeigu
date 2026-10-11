<?php

namespace App\Services\Finance;

use App\Models\FinanceCashLedger;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Models\WarehouseFinanceCashHandover;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\PasswordStepUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseFinanceHandoverService
{
    public function __construct(private readonly WarehouseCashLedgerService $cash, private readonly AuditLogger $audit) {}

    public function request(string $amount, string $notes, string $key): WarehouseFinanceCashHandover
    {
        abort_unless(auth()->user()?->hasAnyRole(['warehouse', 'super-admin'])
            && auth()->user()?->can('warehouse-finance-handovers-create'), 403);
        PasswordStepUp::requireRecent();
        $notes = trim($notes);
        try {
            $cents = FixedDecimal::money($amount);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => __('Nominal serah-terima tidak valid.')]);
        }
        if ($cents < 1 || mb_strlen($notes) < 3 || mb_strlen($notes) > 2000 || strlen($key) < 8 || strlen($key) > 96) {
            throw ValidationException::withMessages(['amount' => __('Isi nominal positif, catatan, dan kunci transaksi yang valid.')]);
        }
        $fingerprint = hash('sha256', json_encode([auth()->id(), $cents, $notes], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($cents, $notes, $key, $fingerprint): WarehouseFinanceCashHandover {
            $warehouse = Warehouse::query()->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->lockForUpdate()->firstOrFail();
            $existing = WarehouseFinanceCashHandover::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->requested_by !== auth()->id() || $existing->payload_hash !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotencyKey' => __('Kunci transaksi telah digunakan untuk pengajuan berbeda.')]);
                }

                return $existing;
            }
            $balance = WarehouseCashLedger::query()->where('warehouse_id', $warehouse->id)->latest('id')->value('balance_after');
            $balanceCents = $balance === null ? 0 : FixedDecimal::money((string) $balance);
            $pendingCents = WarehouseFinanceCashHandover::query()->where('warehouse_id', $warehouse->id)->where('status', 'pending')
                ->get(['requested_amount'])->sum(fn (WarehouseFinanceCashHandover $row) => FixedDecimal::money((string) $row->requested_amount));
            if ($cents > $balanceCents - $pendingCents) {
                throw ValidationException::withMessages(['amount' => __('Saldo kas Gudang Bersama yang belum diajukan tidak mencukupi.')]);
            }
            $handover = WarehouseFinanceCashHandover::query()->create([
                'number' => 'WFC-'.now()->format('ymdHis').'-'.Str::upper(Str::random(6)),
                'warehouse_id' => $warehouse->id, 'requested_by' => auth()->id(),
                'requested_amount' => FixedDecimal::moneyText($cents), 'status' => 'pending',
                'notes' => $notes, 'idempotency_key' => $key, 'payload_hash' => $fingerprint,
            ]);
            $this->audit->record('warehouse_finance_handover.requested', $handover, null, [
                'requested_amount' => $handover->requested_amount, 'warehouse_id' => $warehouse->id,
            ]);

            return $handover;
        }, attempts: 3);
    }

    public function confirm(int $id, string $actualAmount, ?string $verificationNote = null): WarehouseFinanceCashHandover
    {
        abort_unless(auth()->user()?->hasAnyRole(['finance', 'super-admin'])
            && auth()->user()?->can('warehouse-finance-handovers-confirm'), 403);
        PasswordStepUp::requireRecent();
        $verificationNote = trim((string) $verificationNote);
        try {
            $actualCents = FixedDecimal::money($actualAmount);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['actualAmount' => __('Nominal fisik tidak valid.')]);
        }
        if ($actualCents < 1 || mb_strlen($verificationNote) > 2000) {
            throw ValidationException::withMessages(['actualAmount' => __('Nominal fisik harus positif dan catatan maksimal 2.000 karakter.')]);
        }

        return DB::transaction(function () use ($id, $actualCents, $verificationNote): WarehouseFinanceCashHandover {
            $handover = WarehouseFinanceCashHandover::query()->lockForUpdate()->findOrFail($id);
            $warehouse = Warehouse::query()->whereKey($handover->warehouse_id)->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->lockForUpdate()->firstOrFail();
            if ($handover->status === 'confirmed') {
                if (FixedDecimal::money((string) $handover->actual_amount) !== $actualCents) {
                    throw ValidationException::withMessages(['actualAmount' => __('Serah-terima sudah dikonfirmasi dengan nominal lain.')]);
                }

                return $handover;
            }
            if ($handover->status !== 'pending') {
                throw ValidationException::withMessages(['handover' => __('Hanya pengajuan pending yang dapat dikonfirmasi.')]);
            }
            if ($handover->requested_by === auth()->id() && ! auth()->user()->hasRole('super-admin')) {
                throw ValidationException::withMessages(['handover' => __('Pengirim tidak dapat menerima uangnya sendiri.')]);
            }
            $varianceCents = $actualCents - FixedDecimal::money((string) $handover->requested_amount);
            if ($varianceCents !== 0 && mb_strlen($verificationNote) < 3) {
                throw ValidationException::withMessages(['verificationNote' => __('Selisih kas wajib disertai catatan pemeriksaan.')]);
            }
            $balance = WarehouseCashLedger::query()->where('warehouse_id', $warehouse->id)->latest('id')->value('balance_after');
            $balanceCents = $balance === null ? 0 : FixedDecimal::money((string) $balance);
            $otherPending = WarehouseFinanceCashHandover::query()->where('warehouse_id', $warehouse->id)->where('status', 'pending')
                ->where('id', '!=', $handover->id)->get(['requested_amount'])
                ->sum(fn (WarehouseFinanceCashHandover $row) => FixedDecimal::money((string) $row->requested_amount));
            if ($actualCents > $balanceCents - $otherPending) {
                throw ValidationException::withMessages(['actualAmount' => __('Saldo kas bebas Gudang Bersama tidak cukup untuk nominal fisik ini.')]);
            }
            $this->cash->append($warehouse->id, null, 'finance_handover_out', $actualCents,
                WarehouseFinanceCashHandover::class, $handover->id, $verificationNote ?: $handover->notes);
            $financeBefore = FinanceCashLedger::query()->latest('id')->value('balance_after');
            $financeBeforeCents = $financeBefore === null ? 0 : FixedDecimal::money((string) $financeBefore);
            FinanceCashLedger::query()->create([
                'warehouse_finance_cash_handover_id' => $handover->id, 'user_id' => auth()->id(),
                'amount' => FixedDecimal::moneyText($actualCents),
                'balance_before' => FixedDecimal::moneyText($financeBeforeCents),
                'balance_after' => FixedDecimal::moneyText($financeBeforeCents + $actualCents),
                'created_at' => now(),
            ]);
            $handover->update([
                'status' => 'confirmed', 'confirmed_by' => auth()->id(), 'confirmed_at' => now(),
                'actual_amount' => FixedDecimal::moneyText($actualCents),
                'variance_amount' => FixedDecimal::moneyText($varianceCents),
                'verification_note' => $verificationNote ?: null,
            ]);
            $this->audit->record('warehouse_finance_handover.confirmed', $handover, ['status' => 'pending'], [
                'status' => 'confirmed', 'requested_amount' => $handover->requested_amount,
                'actual_amount' => $handover->actual_amount, 'variance_amount' => $handover->variance_amount,
            ]);

            return $handover->refresh();
        }, attempts: 3);
    }

    public function cancel(int $id): WarehouseFinanceCashHandover
    {
        abort_unless(auth()->user()?->hasAnyRole(['warehouse', 'super-admin'])
            && auth()->user()?->can('warehouse-finance-handovers-create'), 403);
        PasswordStepUp::requireRecent();

        return DB::transaction(function () use ($id): WarehouseFinanceCashHandover {
            $handover = WarehouseFinanceCashHandover::query()->lockForUpdate()->findOrFail($id);
            abort_unless($handover->requested_by === auth()->id() || auth()->user()->hasRole('super-admin'), 403);
            if ($handover->status === 'cancelled') {
                return $handover;
            }
            if ($handover->status !== 'pending') {
                throw ValidationException::withMessages(['handover' => __('Kas yang telah diterima Finance tidak dapat dibatalkan.')]);
            }
            $handover->update(['status' => 'cancelled']);
            $this->audit->record('warehouse_finance_handover.cancelled', $handover, ['status' => 'pending'], ['status' => 'cancelled']);

            return $handover;
        }, attempts: 3);
    }
}
