<?php

namespace App\Services\Cashier;

use App\Models\CashHandover;
use App\Models\CashierShift;
use App\Models\ShiftCashMovement;
use App\Models\Warehouse;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use App\Support\PasswordStepUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashHandoverService
{
    public function __construct(private readonly AuditLogger $audit, private readonly WarehouseCashLedgerService $cash, private readonly CashierShiftService $shifts) {}

    public function request(int $sourceId, int $destinationId, string $amount, string $notes, string $key): CashHandover
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();
        $data = ['source_warehouse_id' => $sourceId, 'destination_warehouse_id' => $destinationId, 'amount' => $amount, 'notes' => trim($notes), 'idempotency_key' => $key];
        Validator::make($data, ['destination_warehouse_id' => ['different:source_warehouse_id'], 'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'], 'notes' => ['required', 'string', 'min:3', 'max:2000'], 'idempotency_key' => ['required', 'string', 'max:96']])->validate();
        $data['amount'] = FixedDecimal::moneyText(FixedDecimal::money($amount));
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $fingerprint) {
            $source = $this->warehouse($data['source_warehouse_id'], true);
            $destination = $this->warehouse($data['destination_warehouse_id']);
            if ($existing = CashHandover::where('idempotency_key', $data['idempotency_key'])->first()) {
                abort_unless($existing->requested_by === auth()->id(), 404);
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => __('Kunci serah-terima telah digunakan untuk data lain.')]);
                }

                return $existing;
            }
            $shift = $source->outlet_id ? $this->ownShift($source) : null;
            if ($shift && $this->shifts->expectedCashCents($shift) < FixedDecimal::money($data['amount'])) {
                throw ValidationException::withMessages(['amount' => __('Kas shift sumber tidak cukup.')]);
            }
            $handover = CashHandover::create($data + ['number' => 'CH-'.now()->format('ymdHis').'-'.Str::upper(Str::random(6)), 'cashier_shift_id' => $shift?->id, 'requested_by' => auth()->id(), 'status' => 'pending', 'request_fingerprint' => $fingerprint]);
            $this->audit->record('cash_handover.requested', $handover, null, ['source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'amount' => $handover->amount], ['outlet_id' => $source->outlet_id]);

            return $handover;
        }, attempts: 3);
    }

    public function approve(int $id): CashHandover
    {
        $this->permit('cash-management-approve');
        PasswordStepUp::requireRecent();

        return DB::transaction(function () use ($id) {
            $handover = CashHandover::lockForUpdate()->findOrFail($id);
            $source = $this->warehouse($handover->source_warehouse_id, true);
            $this->warehouse($handover->destination_warehouse_id);
            if (in_array($handover->status, ['approved', 'confirmed'], true)) {
                return $handover;
            }
            if ($handover->status !== 'pending') {
                throw ValidationException::withMessages(['handover' => __('Serah-terima ini tidak menunggu persetujuan.')]);
            }
            if ($handover->requested_by === auth()->id() && ! auth()->user()->hasRole('super-admin')) {
                throw ValidationException::withMessages(['handover' => __('Permintaan harus disetujui petugas lain.')]);
            }
            $shift = $handover->cashier_shift_id ? CashierShift::where('warehouse_id', $source->id)->where('outlet_id', $source->outlet_id)->where('user_id', $handover->requested_by)->where('status', 'open')->lockForUpdate()->find($handover->cashier_shift_id) : null;
            if ($source->outlet_id && ! $shift) {
                throw ValidationException::withMessages(['handover' => __('Shift sumber sudah ditutup. Batalkan permintaan dan buat ulang pada shift aktif.')]);
            }
            $cents = FixedDecimal::money($handover->amount);
            if ($shift && $this->shifts->expectedCashCents($shift) < $cents) {
                throw ValidationException::withMessages(['amount' => __('Kas shift sumber tidak cukup.')]);
            }
            $this->cash->append($source->id, $shift?->id, 'handover_out', $cents, CashHandover::class, $handover->id, $handover->notes);
            if ($shift) {
                $this->movement($shift, $handover, 'out');
            }
            $handover->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $this->audit->record('cash_handover.approved', $handover, ['status' => 'pending'], ['status' => 'approved', 'amount' => $handover->amount, 'source_warehouse_id' => $source->id], ['outlet_id' => $source->outlet_id]);

            return $handover->refresh();
        }, attempts: 3);
    }

    public function confirm(int $id): CashHandover
    {
        abort_unless(auth()->user()?->canAny(['cash-management-create', 'cash-handovers-confirm']), 403);
        PasswordStepUp::requireRecent();

        return DB::transaction(function () use ($id) {
            $handover = CashHandover::lockForUpdate()->findOrFail($id);
            // Receiver needs destination access; no source outlet data is queried here.
            $destination = $this->warehouse($handover->destination_warehouse_id, true);
            if ($handover->status === 'confirmed') {
                return $handover;
            }
            if ($handover->status !== 'approved') {
                throw ValidationException::withMessages(['handover' => __('Kas harus disetujui dan dikirim sebelum dapat diterima.')]);
            }
            if ($handover->requested_by === auth()->id() && ! auth()->user()->hasRole('super-admin')) {
                throw ValidationException::withMessages(['handover' => __('Pengirim tidak dapat mengonfirmasi penerimaan sendiri.')]);
            }
            $shift = $destination->outlet_id ? $this->ownShift($destination) : null;
            $this->cash->append($destination->id, $shift?->id, 'handover_in', FixedDecimal::money($handover->amount), CashHandover::class, $handover->id, $handover->notes);
            if ($shift) {
                $this->movement($shift, $handover, 'in');
            }
            $handover->update(['status' => 'confirmed', 'confirmed_by' => auth()->id(), 'receiving_shift_id' => $shift?->id, 'confirmed_at' => now()]);
            $this->audit->record('cash_handover.confirmed', $handover, ['status' => 'approved'], ['status' => 'confirmed', 'amount' => $handover->amount, 'destination_warehouse_id' => $destination->id], ['outlet_id' => $destination->outlet_id]);

            return $handover->refresh();
        }, attempts: 3);
    }

    public function cancel(int $id): void
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();
        DB::transaction(function () use ($id) {
            $handover = CashHandover::lockForUpdate()->findOrFail($id);
            $source = $this->warehouse($handover->source_warehouse_id, true);
            abort_unless($handover->requested_by === auth()->id() || auth()->user()->can('cash-management-approve'), 403);
            if ($handover->status === 'cancelled') {
                return;
            }
            if ($handover->status !== 'pending') {
                throw ValidationException::withMessages(['handover' => __('Kas yang telah dikirim tidak dapat dibatalkan. Catat serah-terima balik setelah penerimaan.')]);
            }
            $handover->update(['status' => 'cancelled']);
            $this->audit->record('cash_handover.cancelled', $handover, ['status' => 'pending'], ['status' => 'cancelled'], ['outlet_id' => $source->outlet_id]);
        }, attempts: 3);
    }

    private function movement(CashierShift $shift, CashHandover $handover, string $direction): void
    {
        ShiftCashMovement::create(['cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'type' => $direction, 'amount' => $handover->amount, 'reference' => CashHandover::class.':'.$handover->id, 'note' => $handover->notes]);
        $shift->update(['expected_cash' => FixedDecimal::moneyText($this->shifts->expectedCashCents($shift))]);
    }

    private function ownShift(Warehouse $warehouse): CashierShift
    {
        $shift = CashierShift::where('open_user_id', auth()->id())->where('outlet_id', $warehouse->outlet_id)->where('warehouse_id', $warehouse->id)->where('status', 'open')->lockForUpdate()->first();
        if (! $shift) {
            throw ValidationException::withMessages(['handover' => __('Buka shift di gudang ini sebelum serah-terima kas.')]);
        }

        return $shift;
    }

    private function warehouse(int $id, bool $current = false): Warehouse
    {
        $warehouse = Warehouse::where('is_active', true)->findOrFail($id);
        if ($warehouse->outlet_id) {
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
            if ($current) {
                abort_unless($warehouse->outlet_id === (int) session('active_outlet_id'), 404);
            }
        } else {
            abort_unless($warehouse->type === 'central' && (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access') || (! $current && auth()->user()->can('cash-management-create'))), 404);
        }

        return $warehouse;
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
