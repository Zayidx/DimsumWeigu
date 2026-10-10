<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CashierShiftService;
use App\Services\Cashier\CheckoutService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function shift(Request $request)
    {
        $outletId = $this->outlet($request);
        $shift = CashierShift::where('open_user_id', $request->user()->id)->where('outlet_id', $outletId)->where('status', 'open')->first();

        return response()->json(['data' => $shift?->only(['id', 'outlet_id', 'warehouse_id', 'status', 'opening_cash', 'expected_cash', 'opened_at'])]);
    }

    public function openShift(Request $request, CashierShiftService $service)
    {
        $data = $request->validate(['warehouse_id' => ['required', 'integer']]);
        $outletId = $this->outlet($request);
        $warehouse = Warehouse::where('outlet_id', $outletId)->where('is_active', true)->findOrFail($data['warehouse_id']);
        $shift = $service->open($outletId, $warehouse->id);

        return response()->json(['data' => $shift->only(['id', 'outlet_id', 'warehouse_id', 'status', 'opening_cash', 'expected_cash', 'opened_at'])], 201);
    }

    public function closeShift(Request $request, CashierShiftService $service)
    {
        $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'], 'note' => ['nullable', 'string', 'max:2000'], 'password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('Kata sandi tidak sesuai.')]);
        }
        $request->attributes->set('api_password_confirmed_user_id', $request->user()->id);
        $shift = $this->activeShift($request);
        $closed = $service->close($shift->id, (string) $data['actual_cash'], $data['note'] ?? null);

        return response()->json(['data' => $closed->only(['id', 'status', 'expected_cash', 'actual_cash', 'cash_difference', 'closed_at'])]);
    }

    public function catalog(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'barcode' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $shift = $this->activeShift($request);
        $products = Product::sellableIn($shift->warehouse_id)->with(['unit:id,name,symbol', 'productUnits.unit:id,name,symbol', 'warehouseStocks' => fn ($q) => $q->where('warehouse_id', $shift->warehouse_id)])
            ->when(isset($data['barcode']), fn ($q) => $q->where('barcode', $data['barcode']))
            ->when(($data['search'] ?? '') !== '', fn ($q) => $q->where(fn ($s) => $s->where('title', 'like', '%'.$data['search'].'%')->orWhere('sku', 'like', '%'.$data['search'].'%')))
            ->orderBy('title')->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => $products->through(fn ($p) => [
            'id' => $p->id, 'name' => $p->title, 'sku' => $p->sku, 'barcode' => $p->barcode,
            'sell_price' => $p->sell_price, 'tax_rate' => $p->tax_rate, 'tax_inclusive' => $p->tax_inclusive,
            'unit' => $p->unit?->only(['id', 'name', 'symbol']), 'stock' => $p->warehouseStocks->first()?->stock,
            'units' => $p->productUnits->map(fn ($u) => ['unit_id' => $u->unit_id, 'symbol' => $u->unit?->symbol, 'conversion_factor' => $u->conversion_factor, 'sell_price' => $u->sell_price]),
        ])]);
    }

    public function cart(Request $request, CartService $service)
    {
        return $this->cartResponse($service->activeFor($this->activeShift($request)));
    }

    public function updateCart(Request $request, int $cart, CartService $service)
    {
        $data = $request->validate(['customer_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $record = $this->ownedCart($request, $cart);
        $service->updateDetails($record, isset($data['customer_id']) ? (int) $data['customer_id'] : null, $data['notes'] ?? null);

        return $this->cartResponse($record);
    }

    public function addItem(Request $request, int $cart, CartService $service)
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'unit_id' => ['nullable', 'integer']]);
        $record = $this->ownedCart($request, $cart);
        $service->add($record, $data['product_id'], $data['unit_id'] ?? null);

        return $this->cartResponse($record);
    }

    public function updateItem(Request $request, int $cart, int $item, CartService $service)
    {
        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999999.999']]);
        $record = $this->ownedCart($request, $cart);
        $service->setQuantity($record, $item, (string) $data['quantity']);

        return $this->cartResponse($record);
    }

    public function removeItem(Request $request, int $cart, int $item, CartService $service)
    {
        $record = $this->ownedCart($request, $cart);
        $record->items()->findOrFail($item);
        $service->remove($record, $item);

        return $this->cartResponse($record);
    }

    public function held(Request $request)
    {
        $shift = $this->activeShift($request);

        return response()->json(['data' => Cart::where('user_id', $request->user()->id)->where('cashier_shift_id', $shift->id)->where('status', 'held')->latest()->get(['id', 'uuid', 'hold_label', 'held_at'])]);
    }

    public function hold(Request $request, int $cart, CartService $service)
    {
        $this->permit('pos-hold');
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);

        return $this->cartResponse($service->hold($this->ownedCart($request, $cart), $data['label']));
    }

    public function resume(Request $request, int $cart, CartService $service)
    {
        $this->permit('pos-hold');

        return $this->cartResponse($service->resume($this->ownedCart($request, $cart)));
    }

    public function discard(Request $request, int $cart, CartService $service)
    {
        $this->permit('pos-hold');
        $service->discard($this->ownedCart($request, $cart));

        return response()->json(['message' => __('Keranjang hold dihapus.')]);
    }

    public function checkout(Request $request, int $cart, CheckoutService $service)
    {
        $this->permit('pos-checkout');
        $data = $request->validate(['method' => ['required', 'in:cash,pay-later'], 'received' => ['required_if:method,cash', 'nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'], 'client_uuid' => ['required', 'uuid']]);
        $record = $this->ownedCart($request, $cart);
        if ($record->uuid !== $data['client_uuid']) {
            throw ValidationException::withMessages(['client_uuid' => __('UUID harus berasal dari keranjang yang dibuat server.')]);
        }
        $transaction = $data['method'] === 'cash' ? $service->cash($record, (string) $data['received']) : $service->payLater($record);

        return response()->json(['data' => $this->transactionData($transaction)]);
    }

    public function sync(Request $request, CheckoutService $service)
    {
        $data = $request->validate(['cart_id' => ['required', 'integer']]);

        return $this->checkout($request, (int) $data['cart_id'], $service);
    }

    public function history(Request $request)
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $rows = Transaction::where('outlet_id', $this->outlet($request))->where('user_id', $request->user()->id)
            ->when(isset($data['from']), fn ($q) => $q->whereDate('occurred_at', '>=', $data['from']))
            ->when(isset($data['to']), fn ($q) => $q->whereDate('occurred_at', '<=', $data['to']))->latest('occurred_at')->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => $rows->through(fn ($t) => $t->only(['id', 'number', 'outlet_id', 'warehouse_id', 'status', 'payment_status', 'grand_total', 'paid_total', 'occurred_at']))]);
    }

    public function transaction(Request $request, int $transaction)
    {
        $record = Transaction::where('outlet_id', $this->outlet($request))->where('user_id', $request->user()->id)->findOrFail($transaction);

        return response()->json(['data' => $this->transactionData($record)]);
    }

    private function outlet(Request $request): int
    {
        $data = $request->validate(['outlet_id' => ['required', 'integer']]);
        $outlet = app(OutletAccess::class)->require($request->user(), (int) $data['outlet_id']);
        $request->attributes->set('api_outlet_id', $outlet->id);

        return $outlet->id;
    }

    private function activeShift(Request $request): CashierShift
    {
        $shift = CashierShift::where('open_user_id', $request->user()->id)->where('outlet_id', $this->outlet($request))->where('status', 'open')->first();
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => __('Buka shift pada outlet ini terlebih dahulu.')]);
        }

        return $shift;
    }

    private function ownedCart(Request $request, int $id): Cart
    {
        return Cart::where('user_id', $request->user()->id)->where('outlet_id', $this->outlet($request))->findOrFail($id);
    }

    private function cartResponse(Cart $cart)
    {
        $cart->refresh()->load('items.product:id,title,sku,sell_price', 'items.unit:id,symbol');

        return response()->json(['data' => $cart->only(['id', 'uuid', 'cashier_shift_id', 'outlet_id', 'warehouse_id', 'customer_id', 'notes', 'status', 'hold_label']) + [
            'items' => $cart->items->map(fn ($i) => ['id' => $i->id, 'product_id' => $i->product_id, 'product_name' => $i->product?->title, 'unit_id' => $i->unit_id, 'unit' => $i->unit?->symbol, 'quantity' => $i->quantity, 'notes' => $i->notes]),
        ]]);
    }

    private function transactionData(Transaction $transaction): array
    {
        $transaction->load('details', 'tenders');

        return $transaction->only(['id', 'number', 'client_uuid', 'outlet_id', 'warehouse_id', 'status', 'payment_status', 'subtotal', 'tax_total', 'discount_total', 'grand_total', 'paid_total', 'change_total', 'occurred_at']) + [
            'items' => $transaction->details->map(fn ($d) => $d->only(['id', 'product_id', 'product_name', 'product_sku', 'unit_name', 'quantity', 'unit_price', 'discount_total', 'tax_total', 'line_total'])),
            'payments' => $transaction->tenders->map(fn ($t) => $t->only(['method', 'status', 'amount', 'confirmed_at'])),
        ];
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
