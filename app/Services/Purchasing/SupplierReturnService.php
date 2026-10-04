<?php

namespace App\Services\Purchasing;

use App\Models\CashierShift;
use App\Models\GoodsReceiving;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Product;
use App\Models\ShiftCashMovement;
use App\Models\SupplierCreditNote;
use App\Models\SupplierRefund;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use App\Models\Warehouse;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\WarehouseCashLedgerService;
use App\Services\Inventory\StockMutationService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierReturnService
{
    public function __construct(private readonly StockMutationService $stock, private readonly AuditLogger $audit, private readonly WarehouseCashLedgerService $cash) {}

    public function draft(int $receivingId, array $lines, string $reason, string $resolution, string $key): SupplierReturn
    {
        $this->permit('supplier-returns-create');
        if (! in_array($resolution, ['credit', 'refund'], true) || trim($reason) === '' || mb_strlen($reason) > 2000 || $key === '' || strlen($key) > 96 || $lines === []) {
            $this->invalid('return', __('Data retur tidak valid.'));
        }
        $normalized = [];
        foreach ($lines as $line) {
            $id = (int) ($line['receiving_item_id'] ?? 0);
            $qty = FixedDecimal::quantity((string) ($line['quantity'] ?? '0'));
            if ($id < 1 || $qty < 1 || isset($normalized[$id])) {
                $this->invalid('items', __('Item dan jumlah retur harus valid dan tidak berulang.'));
            }
            $normalized[$id] = $qty;
        }
        ksort($normalized);
        $fingerprint = hash('sha256', json_encode([$receivingId, $normalized, trim($reason), $resolution]));

        return DB::transaction(function () use ($receivingId, $normalized, $reason, $resolution, $key, $fingerprint) {
            $receiving = GoodsReceiving::query()->with('warehouse')->lockForUpdate()->findOrFail($receivingId);
            $this->assertWarehouse($receiving->warehouse);
            $existing = SupplierReturn::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    $this->invalid('return', __('Kunci retur sudah digunakan untuk permintaan berbeda.'));
                }

                return $existing;
            }
            if ($receiving->status !== 'completed') {
                $this->invalid('return', __('Retur hanya dapat dibuat dari penerimaan selesai.'));
            }
            $sources = $receiving->items()->with('orderItem')->whereIn('id', array_keys($normalized))->lockForUpdate()->get()->keyBy('id');
            if ($sources->count() !== count($normalized)) {
                $this->invalid('items', __('Item retur harus berasal dari penerimaan yang dipilih.'));
            }
            $return = SupplierReturn::create(['number' => 'SR-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)), 'supplier_id' => $receiving->supplier_id, 'warehouse_id' => $receiving->warehouse_id, 'purchase_order_id' => $receiving->purchase_order_id, 'goods_receiving_id' => $receiving->id, 'created_by' => auth()->id(), 'status' => 'draft', 'resolution' => $resolution, 'reason' => trim($reason), 'idempotency_key' => $key, 'request_fingerprint' => $fingerprint]);
            $total = 0;
            foreach ($normalized as $id => $qty) {
                $source = $sources[$id];
                $previousItems = SupplierReturnItem::query()->where('goods_receiving_item_id', $id)->whereHas('supplierReturn', fn ($q) => $q->whereIn('status', ['draft', 'approved', 'completed']))->get(['quantity', 'line_total']);
                $reserved = $previousItems->sum(fn ($row) => FixedDecimal::quantity((string) $row->quantity));
                $accepted = FixedDecimal::quantity((string) $source->accepted_quantity);
                if ($qty + $reserved > $accepted) {
                    $this->invalid('items', __('Jumlah retur melebihi barang diterima setelah dikurangi retur sebelumnya.'));
                }
                $base = (int) round(FixedDecimal::money((string) $source->unit_cost) * $accepted / 1000);
                $rate = (int) round((float) ($source->orderItem?->tax_rate ?? 0) * 100);
                $originalValue = $base + (int) round($base * $rate / 10000);
                $remainingValue = $originalValue - $previousItems->sum(fn ($row) => FixedDecimal::money((string) $row->line_total));
                $amount = $qty + $reserved === $accepted ? $remainingValue : min($remainingValue, (int) round($originalValue * $qty / $accepted));
                $return->items()->create(['goods_receiving_item_id' => $id, 'product_id' => $source->product_id, 'product_batch_id' => $source->product_batch_id, 'quantity' => FixedDecimal::quantityText($qty), 'unit_cost' => $source->unit_cost, 'line_total' => FixedDecimal::moneyText($amount)]);
                $total += $amount;
            }
            $return->update(['total' => FixedDecimal::moneyText($total)]);
            $this->audit->record('supplier_return.created', $return, null, ['receiving_id' => $receivingId, 'total' => $return->total, 'resolution' => $resolution]);

            return $return;
        }, attempts: 3);
    }

    public function approve(int $id): SupplierReturn
    {
        $this->permit('supplier-returns-approve');

        return DB::transaction(function () use ($id) {
            $return = SupplierReturn::query()->with('warehouse')->lockForUpdate()->findOrFail($id);
            $this->assertWarehouse($return->warehouse);
            if (in_array($return->status, ['approved', 'completed'], true)) {
                return $return;
            }
            if ($return->status !== 'draft') {
                $this->invalid('return', __('Retur sudah dibatalkan.'));
            }
            $return->update(['status' => 'approved', 'approved_by' => auth()->id()]);
            $this->audit->record('supplier_return.approved', $return, ['status' => 'draft'], ['status' => 'approved']);

            return $return;
        });
    }

    public function complete(int $id, string $refundMethod = 'bank-transfer', ?string $reference = null): SupplierReturn
    {
        $this->permit('supplier-returns-approve');
        if (! in_array($refundMethod, ['cash', 'bank-transfer'], true) || mb_strlen($reference ?? '') > 160) {
            $this->invalid('refundMethod', __('Metode refund tidak valid.'));
        }

        return DB::transaction(function () use ($id, $refundMethod, $reference) {
            $source = SupplierReturn::query()->findOrFail($id);
            GoodsReceiving::query()->lockForUpdate()->findOrFail($source->goods_receiving_id);
            $return = SupplierReturn::query()->with(['items' => fn ($q) => $q->orderBy('product_id')->orderBy('id'), 'warehouse'])->lockForUpdate()->findOrFail($id);
            $this->assertWarehouse($return->warehouse);
            if ($return->status === 'completed') {
                return $return;
            }
            if ($return->status !== 'approved') {
                $this->invalid('return', __('Retur harus disetujui sebelum diselesaikan.'));
            }
            $payable = Payable::query()->where('goods_receiving_id', $return->goods_receiving_id)->lockForUpdate()->first();
            $total = FixedDecimal::money((string) $return->total);
            $outstanding = $payable ? FixedDecimal::money((string) $payable->amount) - FixedDecimal::money((string) $payable->paid_amount) : 0;
            $offset = min($total, max(0, $outstanding));
            $remaining = $total - $offset;
            if ($return->resolution === 'refund' && $remaining > 0) {
                $this->permit('payables-pay');
                if ($refundMethod === 'bank-transfer' && ! filled($reference)) {
                    $this->invalid('reference', __('Referensi refund transfer wajib diisi.'));
                }
            }
            foreach ($return->items as $item) {
                $product = Product::withTrashed()->findOrFail($item->product_id);
                if ($product->track_batches && ! $item->product_batch_id) {
                    $this->invalid('items', __('Produk batch harus diretur dari batch penerimaan asal.'));
                }
                $this->stock->mutate($item->product_id, $return->warehouse_id, '-'.$item->quantity, 'supplier_return', 'supplier-return:'.$return->id.':'.$item->id, $return->reason, $item->product_batch_id, SupplierReturn::class, $return->id);
            }
            if ($offset > 0) {
                $this->settleWithCredit($payable, $offset, 'supplier-return:'.$return->id.':offset', $return->number);
            }
            if ($remaining > 0 && $return->resolution === 'credit') {
                SupplierCreditNote::create(['supplier_id' => $return->supplier_id, 'supplier_return_id' => $return->id, 'amount' => FixedDecimal::moneyText($remaining), 'available_amount' => FixedDecimal::moneyText($remaining)]);
            } elseif ($remaining > 0) {
                $refund = SupplierRefund::create(['supplier_return_id' => $return->id, 'warehouse_id' => $return->warehouse_id, 'user_id' => auth()->id(), 'method' => $refundMethod, 'amount' => FixedDecimal::moneyText($remaining), 'reference' => $reference]);
                if ($refundMethod === 'cash') {
                    $shift = CashierShift::query()->where('open_user_id', auth()->id())->where('warehouse_id', $return->warehouse_id)->where('outlet_id', session('active_outlet_id'))->where('status', 'open')->lockForUpdate()->first();
                    if (! $shift) {
                        $this->invalid('return', __('Buka shift pada gudang retur sebelum menerima refund tunai.'));
                    }
                    ShiftCashMovement::create(['cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'type' => 'in', 'amount' => $refund->amount, 'reference' => 'supplier-refund:'.$refund->id, 'note' => $return->number]);
                    $this->cash->append($return->warehouse_id, $shift->id, 'supplier_refund', $remaining, SupplierRefund::class, $refund->id, $return->number);
                    $shift->update(['expected_cash' => FixedDecimal::moneyText(app(CashierShiftService::class)->expectedCashCents($shift))]);
                }
            }
            $return->update(['status' => 'completed', 'completed_at' => now()]);
            $this->audit->record('supplier_return.completed', $return, ['status' => 'approved'], ['status' => 'completed', 'payable_offset' => FixedDecimal::moneyText($offset), 'residual' => FixedDecimal::moneyText($remaining), 'resolution' => $return->resolution]);

            return $return;
        }, attempts: 3);
    }

    public function applyCredit(int $creditId, int $payableId, string $amount, string $key): PayablePayment
    {
        $this->permit('payables-pay');
        $cents = FixedDecimal::money($amount);
        if ($cents <= 0 || $key === '' || strlen($key) > 96) {
            $this->invalid('amount', __('Jumlah kredit tidak valid.'));
        }

        return DB::transaction(function () use ($creditId, $payableId, $cents, $key) {
            $note = SupplierCreditNote::query()->with('supplierReturn.warehouse')->lockForUpdate()->findOrFail($creditId);
            $this->assertWarehouse($note->supplierReturn->warehouse);
            $payable = Payable::query()->with('receiving.warehouse')->lockForUpdate()->findOrFail($payableId);
            $this->assertWarehouse($payable->receiving?->warehouse ?? throw new ModelNotFoundException);
            $existing = PayablePayment::query()->where('idempotency_key', $key)->first();
            $reference = 'supplier-credit:'.$note->id;
            if ($existing) {
                if ($existing->payable_id !== $payableId || $existing->method !== 'supplier-credit' || $existing->reference !== $reference || FixedDecimal::money((string) $existing->amount) !== $cents) {
                    $this->invalid('amount', __('Kunci kredit sudah dipakai untuk pembayaran berbeda.'));
                }

                return $existing;
            }
            $available = FixedDecimal::money((string) $note->available_amount);
            $outstanding = FixedDecimal::money((string) $payable->amount) - FixedDecimal::money((string) $payable->paid_amount);
            if ($note->supplier_id !== $payable->supplier_id || $cents > min($available, $outstanding)) {
                $this->invalid('amount', __('Kredit harus milik pemasok yang sama dan tidak melebihi saldo kredit maupun hutang.'));
            }
            $payment = $this->settleWithCredit($payable, $cents, $key, $reference);
            $note->update(['available_amount' => FixedDecimal::moneyText($available - $cents)]);
            $this->audit->record('supplier_credit.applied', $note, ['available_amount' => FixedDecimal::moneyText($available)], ['available_amount' => $note->available_amount, 'payable_id' => $payableId, 'payment_id' => $payment->id]);

            return $payment;
        }, attempts: 3);
    }

    public function cancel(int $id): void
    {
        $this->permit('supplier-returns-create');
        DB::transaction(function () use ($id) {
            $return = SupplierReturn::query()->with('warehouse')->lockForUpdate()->findOrFail($id);
            $this->assertWarehouse($return->warehouse);
            if ($return->status === 'cancelled') {
                return;
            }
            if ($return->status !== 'draft') {
                $this->invalid('return', __('Hanya retur draft dapat dibatalkan.'));
            }
            $return->update(['status' => 'cancelled']);
            $this->audit->record('supplier_return.cancelled', $return, ['status' => 'draft'], ['status' => 'cancelled']);
        });
    }

    private function settleWithCredit(Payable $payable, int $cents, string $key, string $reference): PayablePayment
    {
        $payment = PayablePayment::create(['payable_id' => $payable->id, 'user_id' => auth()->id(), 'method' => 'supplier-credit', 'amount' => FixedDecimal::moneyText($cents), 'reference' => $reference, 'idempotency_key' => $key, 'paid_at' => now()]);
        $paid = FixedDecimal::money((string) $payable->paid_amount) + $cents;
        $payable->update(['paid_amount' => FixedDecimal::moneyText($paid), 'status' => $paid >= FixedDecimal::money((string) $payable->amount) ? 'paid' : 'partial']);

        return $payment;
    }

    private function assertWarehouse(Warehouse $warehouse): void
    {
        abort_unless($warehouse->is_active, 404);
        if ($warehouse->outlet_id) {
            abort_unless((int) session('active_outlet_id') === $warehouse->outlet_id, 404);
            app(OutletAccess::class)->require(auth()->user(), $warehouse->outlet_id);
        } else {
            abort_unless($warehouse->type === 'central' && (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')), 404);
        }
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
