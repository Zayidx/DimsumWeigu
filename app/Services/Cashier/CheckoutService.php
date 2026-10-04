<?php

namespace App\Services\Cashier;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Profit;
use App\Models\Receivable;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionTender;
use App\Services\Inventory\StockMutationService;
use App\Support\AuditLogger;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private readonly StockMutationService $stock, private readonly AuditLogger $audit, private readonly WarehouseCashLedgerService $cashLedger) {}

    public function cash(Cart $cart, string $received): Transaction
    {
        return $this->complete($cart, 'cash', $received);
    }

    public function payLater(Cart $cart): Transaction
    {
        return $this->complete($cart, 'pay-later', '0');
    }

    private function complete(Cart $cart, string $method, string $received): Transaction
    {
        if (! in_array($method, ['cash', 'pay-later'], true)) {
            throw ValidationException::withMessages(['payment' => __('Metode pembayaran belum didukung.')]);
        }
        $receivedCents = FixedDecimal::money($received);
        if ($receivedCents < 0) {
            throw ValidationException::withMessages(['received' => __('Jumlah pembayaran tidak valid.')]);
        }

        return DB::transaction(function () use ($cart, $receivedCents, $method) {
            $cart = Cart::query()->with('items')->whereKey($cart->id)->lockForUpdate()->firstOrFail();
            abort_unless($cart->user_id === auth()->id() && auth()->user()?->can('pos-access') && auth()->user()?->can('pos-checkout'), 403);
            app(OutletAccess::class)->require(auth()->user(), $cart->outlet_id);
            $existing = Transaction::where('client_uuid', $cart->uuid)->first();
            if ($existing) {
                $tender = $existing->tenders()->where('method', $method)->first();
                if (! $tender || ($method === 'cash' && FixedDecimal::money((string) ($tender->metadata['received'] ?? '0')) !== $receivedCents)) {
                    throw ValidationException::withMessages(['payment' => __('Keranjang sudah dibayar dengan rincian pembayaran berbeda.')]);
                }

                return $existing;
            }
            $shift = CashierShift::query()->whereKey($cart->cashier_shift_id)->lockForUpdate()->firstOrFail();
            abort_unless($cart->status === 'active' && $shift->status === 'open' && $shift->open_user_id === auth()->id()
                && $shift->outlet_id === $cart->outlet_id && $shift->warehouse_id === $cart->warehouse_id
                && auth()->user()?->can('pos-checkout'), 403);
            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('Keranjang masih kosong.')]);
            }
            $paymentMethod = PaymentMethod::query()->where('code', $method)->where('is_enabled', true)->first();
            if (! $paymentMethod) {
                throw ValidationException::withMessages(['payment' => __('Metode pembayaran belum diaktifkan.')]);
            }
            $creditCustomer = null;
            $creditBefore = 0;
            if ($method === 'pay-later') {
                if (! $cart->customer_id) {
                    throw ValidationException::withMessages(['customer' => __('Pilih pelanggan sebelum menggunakan pembayaran nanti.')]);
                }
                $creditCustomer = Customer::query()->whereKey($cart->customer_id)->where('is_active', true)->lockForUpdate()->firstOrFail();
                $creditBefore = Receivable::query()->where('customer_id', $creditCustomer->id)->whereIn('status', ['unpaid', 'partial'])
                    ->get(['amount', 'paid_amount'])->sum(fn (Receivable $row) => FixedDecimal::money((string) $row->amount) - FixedDecimal::money((string) $row->paid_amount));
            }

            $prepared = [];
            $subtotal = $taxTotal = $total = 0;
            $defaultTaxRate = (float) Setting::value('default_tax_rate', 0, $cart->outlet_id);
            $defaultTaxInclusive = Setting::value('tax_mode', 'exclusive', $cart->outlet_id) === 'inclusive';
            foreach ($cart->items as $item) {
                $product = Product::query()->whereKey($item->product_id)->where('is_active', true)->where('is_sellable', true)->firstOrFail();
                $qtyMilli = FixedDecimal::quantity((string) $item->quantity);
                if ($qtyMilli <= 0) {
                    throw ValidationException::withMessages(['cart' => __('Jumlah produk pada keranjang tidak valid.')]);
                }
                $unit = $product->unit_id === $item->unit_id ? null : ProductUnit::query()
                    ->where('product_id', $product->id)->where('unit_id', $item->unit_id)->firstOrFail();
                $conversion = (float) ($unit?->conversion_factor ?? 1);
                $stockQtyMilli = (int) round($qtyMilli * $conversion);
                $basePrice = FixedDecimal::money((string) $product->sell_price);
                $price = $unit?->sell_price !== null ? FixedDecimal::money((string) $unit->sell_price) : (int) round($basePrice * $conversion);
                $gross = (int) round($price * $qtyMilli / 1000);
                $discount = FixedDecimal::money((string) $item->manual_discount);
                if ($discount < 0 || $discount > $gross) {
                    throw ValidationException::withMessages(['discount' => __('Diskon produk tidak valid.')]);
                }
                $net = $gross - $discount;
                $rate = (int) round((float) ($product->tax_rate ?? $defaultTaxRate) * 100);
                $inclusive = $product->tax_rate === null ? $defaultTaxInclusive : $product->tax_inclusive;
                $tax = $inclusive ? (int) round($net * $rate / (10000 + $rate)) : (int) round($net * $rate / 10000);
                $lineTotal = $inclusive ? $net : $net + $tax;
                $cost = (int) round(FixedDecimal::money((string) $product->buy_price) * $stockQtyMilli / 1000);
                $prepared[] = compact('item', 'product', 'unit', 'qtyMilli', 'stockQtyMilli', 'price', 'gross', 'discount', 'net', 'rate', 'tax', 'lineTotal', 'cost', 'inclusive');
                $subtotal += $net;
                $taxTotal += $tax;
                $total += $lineTotal;
            }
            if ($method === 'cash' && $receivedCents < $total) {
                throw ValidationException::withMessages(['received' => __('Uang diterima kurang dari total transaksi.')]);
            }
            if ($method === 'pay-later' && FixedDecimal::money((string) $creditCustomer->credit_limit) - $creditBefore < $total) {
                throw ValidationException::withMessages(['customer' => __('Batas kredit pelanggan tidak mencukupi untuk transaksi ini.')]);
            }

            $transaction = Transaction::query()->create([
                'number' => 'WG-'.now()->format('ymdHis').'-'.Str::upper(Str::random(6)),
                'outlet_id' => $cart->outlet_id, 'warehouse_id' => $cart->warehouse_id,
                'cashier_shift_id' => $shift->id, 'user_id' => auth()->id(), 'customer_id' => $cart->customer_id,
                'source' => 'pos', 'status' => 'completed', 'payment_status' => $method === 'cash' ? 'paid' : 'unpaid',
                'subtotal' => FixedDecimal::moneyText($subtotal), 'tax_total' => FixedDecimal::moneyText($taxTotal),
                'grand_total' => FixedDecimal::moneyText($total), 'paid_total' => FixedDecimal::moneyText($method === 'cash' ? $total : 0),
                'change_total' => FixedDecimal::moneyText($method === 'cash' ? $receivedCents - $total : 0), 'notes' => $cart->notes,
                'client_uuid' => $cart->uuid, 'idempotency_key' => 'checkout:'.$cart->uuid,
                'occurred_at' => now(), 'paid_at' => $method === 'cash' ? now() : null,
            ]);

            foreach ($prepared as $index => $line) {
                $detail = TransactionDetail::query()->create([
                    'transaction_id' => $transaction->id, 'product_id' => $line['product']->id,
                    'product_name' => $line['product']->title, 'product_sku' => $line['product']->sku,
                    'unit_name' => $line['unit']?->unit?->symbol ?? $line['product']->unit?->symbol ?? 'unit',
                    'quantity' => FixedDecimal::quantityText($line['qtyMilli']),
                    'unit_price' => FixedDecimal::moneyText($line['price']), 'discount_total' => FixedDecimal::moneyText($line['discount']),
                    'tax_rate' => number_format($line['rate'] / 100, 4, '.', ''), 'tax_total' => FixedDecimal::moneyText($line['tax']),
                    'line_total' => FixedDecimal::moneyText($line['lineTotal']), 'unit_cost' => $line['product']->buy_price,
                    'notes' => $line['item']->notes,
                ]);
                if ($line['product']->track_stock) {
                    $this->stock->mutate($line['product']->id, $cart->warehouse_id, '-'.FixedDecimal::quantityText($line['stockQtyMilli']), 'sale', 'sale:'.$transaction->id.':'.$index,
                        null, null, Transaction::class, $transaction->id);
                }
                Profit::query()->create([
                    'transaction_id' => $transaction->id, 'transaction_detail_id' => $detail->id,
                    'outlet_id' => $cart->outlet_id,
                    'revenue' => FixedDecimal::moneyText($line['inclusive'] ? $line['net'] - $line['tax'] : $line['net']),
                    'cost' => FixedDecimal::moneyText($line['cost']),
                    'profit' => FixedDecimal::moneyText(($line['inclusive'] ? $line['net'] - $line['tax'] : $line['net']) - $line['cost']),
                    'occurred_at' => now(),
                ]);
            }

            TransactionTender::query()->create([
                'transaction_id' => $transaction->id, 'method' => $method, 'amount' => FixedDecimal::moneyText($total),
                'status' => $method === 'cash' ? 'paid' : 'pending', 'idempotency_key' => $method.':'.$cart->uuid,
                'confirmed_at' => $method === 'cash' ? now() : null,
                'metadata' => $method === 'cash' ? ['received' => FixedDecimal::moneyText($receivedCents), 'change' => FixedDecimal::moneyText($receivedCents - $total)] : ['settlement' => 'receivable'],
            ]);
            if ($method === 'pay-later') {
                Receivable::query()->create([
                    'customer_id' => $creditCustomer->id, 'transaction_id' => $transaction->id,
                    'reference' => 'AR-'.$transaction->number, 'amount' => FixedDecimal::moneyText($total),
                    'paid_amount' => 0, 'status' => 'unpaid', 'due_at' => today()->addDays(30),
                ]);
                CustomerCredit::query()->create([
                    'customer_id' => $creditCustomer->id, 'transaction_id' => $transaction->id,
                    'user_id' => auth()->id(), 'type' => 'sale_credit', 'amount' => FixedDecimal::moneyText($total),
                    'balance_before' => FixedDecimal::moneyText($creditBefore), 'balance_after' => FixedDecimal::moneyText($creditBefore + $total),
                    'idempotency_key' => 'credit:'.$cart->uuid, 'note' => __('Penjualan dengan pembayaran nanti.'),
                ]);
            }
            $cart->forceFill(['status' => 'completed'])->save();
            if ($method === 'cash') {
                $this->cashLedger->append($cart->warehouse_id, $shift->id, 'sale_cash', $total, Transaction::class, $transaction->id, __('Penjualan :number', ['number' => $transaction->number]));
            }
            $shift->expected_cash = FixedDecimal::moneyText(app(CashierShiftService::class)->expectedCashCents($shift));
            $shift->save();
            $this->audit->record('transaction.completed', $transaction, null, [
                'number' => $transaction->number, 'grand_total' => $transaction->grand_total, 'payment_method' => $method,
                'item_count' => count($prepared), 'warehouse_id' => $cart->warehouse_id,
            ]);

            return $transaction->refresh();
        }, attempts: 3);
    }
}
