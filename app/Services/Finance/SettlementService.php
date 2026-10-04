<?php

namespace App\Services\Finance;

use App\Models\CustomerCredit;
use App\Models\CashierShift;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\ShiftCashMovement;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Models\Warehouse;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    public function __construct(private readonly AuditLogger $audit, private readonly WarehouseCashLedgerService $cashLedger) {}

    public function payPayable(int $payableId, string $amount, string $method, string $idempotencyKey, ?string $reference = null): PayablePayment
    {
        abort_unless(auth()->user()?->can('payables-pay'), 403);
        $this->validatePayment($amount, $method, $idempotencyKey);
        $cents = FixedDecimal::money($amount);
        return DB::transaction(function () use ($payableId, $cents, $method, $idempotencyKey, $reference) {
            $existing = PayablePayment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->payable_id !== $payableId || FixedDecimal::money((string) $existing->amount) !== $cents || $existing->method !== $method) {
                    throw ValidationException::withMessages(['idempotencyKey' => __('Kunci pembayaran sudah digunakan untuk pembayaran lain.')]);
                }
                return $existing;
            }
            $payable = Payable::query()->with('receiving.order.warehouse')->whereKey($payableId)->lockForUpdate()->firstOrFail();
            $this->assertPayableAccess($payable);
            $outstanding = FixedDecimal::money((string) $payable->amount) - FixedDecimal::money((string) $payable->paid_amount);
            if (! in_array($payable->status, ['unpaid', 'partial'], true) || $cents > $outstanding) {
                throw ValidationException::withMessages(['amount' => __('Pembayaran melebihi sisa hutang atau hutang sudah lunas.')]);
            }
            $payment = PayablePayment::query()->create([
                'payable_id' => $payable->id, 'user_id' => auth()->id(), 'method' => $method,
                'amount' => FixedDecimal::moneyText($cents), 'reference' => $reference, 'idempotency_key' => $idempotencyKey, 'paid_at' => now(),
            ]);
            $after = $outstanding - $cents;
            $payable->forceFill([
                'paid_amount' => FixedDecimal::moneyText(FixedDecimal::money((string) $payable->paid_amount) + $cents),
                'status' => $after === 0 ? 'paid' : 'partial',
            ])->save();
            if ($method === 'cash') {
                $this->recordCashMovement($payable->receiving->warehouse, $cents, 'out', PayablePayment::class, $payment->id, 'supplier_payment', __('Pembayaran hutang pemasok :reference', ['reference' => $payable->reference]));
            }
            $this->audit->record('payable.payment_recorded', $payment, null, ['payable_id' => $payable->id, 'amount' => FixedDecimal::moneyText($cents), 'method' => $method, 'outstanding_after' => FixedDecimal::moneyText($after)]);
            return $payment;
        }, attempts: 3);
    }

    public function collectReceivable(int $receivableId, string $amount, string $method, string $idempotencyKey, ?string $reference = null): ReceivablePayment
    {
        abort_unless(auth()->user()?->can('receivables-collect'), 403);
        $this->validatePayment($amount, $method, $idempotencyKey);
        $cents = FixedDecimal::money($amount);
        return DB::transaction(function () use ($receivableId, $cents, $method, $idempotencyKey, $reference) {
            $existing = ReceivablePayment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->receivable_id !== $receivableId || FixedDecimal::money((string) $existing->amount) !== $cents || $existing->method !== $method) {
                    throw ValidationException::withMessages(['idempotencyKey' => __('Kunci pembayaran sudah digunakan untuk pembayaran lain.')]);
                }
                return $existing;
            }
            $receivable = Receivable::query()->with('transaction')->whereKey($receivableId)->lockForUpdate()->firstOrFail();
            abort_unless($receivable->transaction && $receivable->transaction->outlet_id === (int) session('active_outlet_id'), 404);
            app(OutletAccess::class)->require(auth()->user(), $receivable->transaction->outlet_id);
            $outstanding = FixedDecimal::money((string) $receivable->amount) - FixedDecimal::money((string) $receivable->paid_amount);
            if (! in_array($receivable->status, ['unpaid', 'partial'], true) || $cents > $outstanding) {
                throw ValidationException::withMessages(['amount' => __('Pembayaran melebihi sisa piutang atau piutang sudah lunas.')]);
            }
            $payment = ReceivablePayment::query()->create([
                'receivable_id' => $receivable->id, 'user_id' => auth()->id(), 'method' => $method,
                'amount' => FixedDecimal::moneyText($cents), 'reference' => $reference, 'idempotency_key' => $idempotencyKey, 'paid_at' => now(),
            ]);
            $paidAfter = FixedDecimal::money((string) $receivable->paid_amount) + $cents;
            $after = FixedDecimal::money((string) $receivable->amount) - $paidAfter;
            $receivable->forceFill(['paid_amount' => FixedDecimal::moneyText($paidAfter), 'status' => $after === 0 ? 'paid' : 'partial'])->save();
            $this->updateTransactionPayment($receivable->transaction, $cents, $after === 0);
            if ($method === 'cash') {
                $warehouse = Warehouse::query()->whereKey($receivable->transaction->warehouse_id)->firstOrFail();
                $this->recordCashMovement($warehouse, $cents, 'in', ReceivablePayment::class, $payment->id, 'cash_in', __('Pembayaran piutang :reference', ['reference' => $receivable->reference]));
            }
            $beforeCredit = Receivable::query()->where('customer_id', $receivable->customer_id)->whereIn('status', ['unpaid', 'partial'])->get(['amount', 'paid_amount'])
                ->sum(fn (Receivable $row) => FixedDecimal::money((string) $row->amount) - FixedDecimal::money((string) $row->paid_amount)) + $cents;
            CustomerCredit::query()->create([
                'customer_id' => $receivable->customer_id, 'transaction_id' => $receivable->transaction_id, 'user_id' => auth()->id(),
                'type' => 'payment', 'amount' => FixedDecimal::moneyText($cents),
                'balance_before' => FixedDecimal::moneyText($beforeCredit), 'balance_after' => FixedDecimal::moneyText($beforeCredit - $cents),
                'idempotency_key' => 'receivable-payment:'.$idempotencyKey, 'note' => __('Pembayaran piutang :reference', ['reference' => $receivable->reference]),
            ]);
            $this->audit->record('receivable.payment_collected', $payment, null, ['receivable_id' => $receivable->id, 'amount' => FixedDecimal::moneyText($cents), 'method' => $method, 'outstanding_after' => FixedDecimal::moneyText($after)]);
            return $payment;
        }, attempts: 3);
    }

    private function updateTransactionPayment(Transaction $transaction, int $cents, bool $complete): void
    {
        $newPaid = FixedDecimal::money((string) $transaction->paid_total) + $cents;
        $status = $complete ? 'paid' : 'partial';
        $transaction->forceFill(['paid_total' => FixedDecimal::moneyText($newPaid), 'payment_status' => $status, 'paid_at' => $complete ? now() : $transaction->paid_at])->save();
        $tender = TransactionTender::query()->where('transaction_id', $transaction->id)->where('method', 'pay-later')->first();
        if ($tender && $complete) {
            $tender->forceFill(['status' => 'paid', 'confirmed_at' => now()])->save();
        }
    }

    private function assertPayableAccess(Payable $payable): void
    {
        $warehouse = $payable->receiving?->order?->warehouse ?? $payable->receiving?->warehouse;
        abort_unless($warehouse, 404);
        if ($warehouse->outlet_id) {
            abort_unless($warehouse->outlet_id === (int) session('active_outlet_id'), 404);
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        } else {
            abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('warehouses-access'), 404);
        }
    }

    private function validatePayment(string $amount, string $method, string $key): void
    {
        $cents = FixedDecimal::money($amount);
        if ($cents <= 0 || ! in_array($method, ['cash', 'bank-transfer', 'qris'], true) || $key === '' || strlen($key) > 96) {
            throw ValidationException::withMessages(['amount' => __('Jumlah, metode, atau kunci pembayaran tidak valid.')]);
        }
    }

    private function recordCashMovement(Warehouse $warehouse, int $cents, string $direction, string $referenceType, int $referenceId, string $ledgerType, string $note): void
    {
        $shift = CashierShift::query()->where('open_user_id', auth()->id())->where('warehouse_id', $warehouse->id)->where('status', 'open')->lockForUpdate()->firstOrFail();
        $movement = ShiftCashMovement::query()->create([
            'cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'type' => $direction,
            'amount' => FixedDecimal::moneyText($cents), 'reference' => $referenceType.':'.$referenceId, 'note' => $note,
        ]);
        $this->cashLedger->append($warehouse->id, $shift->id, $ledgerType, $cents, $referenceType, $referenceId, $note);
        $shift->expected_cash = FixedDecimal::moneyText(app(\App\Services\Cashier\CashierShiftService::class)->expectedCashCents($shift));
        $shift->save();
        $this->audit->record('cashier_shift.cash_movement_recorded', $movement, null, ['type' => $direction, 'amount' => FixedDecimal::moneyText($cents), 'source' => $referenceType]);
    }
}
