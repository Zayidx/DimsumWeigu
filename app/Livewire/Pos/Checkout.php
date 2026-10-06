<?php

namespace App\Livewire\Pos;

use App\Models\BankAccount;
use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CheckoutService;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Checkout extends Component
{
    use WithPagination;

    public string $search = '';

    public string $cashReceived = '';

    public string $qrisReference = '';

    public string $holdLabel = '';

    public ?int $customerId = null;

    public string $notes = '';

    public ?string $lastTransaction = null;

    public ?int $lastTransactionId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(CartService $carts): void
    {
        $this->authorizePos();
        if ($shift = $this->activeShiftOrNull()) {
            $cart = $carts->activeFor($shift);
            $this->customerId = $cart->customer_id;
            $this->notes = $cart->notes ?? '';
        }
    }

    public function addProduct(int $productId, CartService $carts): void
    {
        $this->authorizePos();
        $carts->add($this->activeCart($carts), $productId);
        $this->search = '';
    }

    public function addProductUnit(int $productId, int $unitId, CartService $carts): void
    {
        $this->authorizePos();
        $carts->add($this->activeCart($carts), $productId, $unitId);
        $this->search = '';
    }

    public function setQuantity(int $itemId, string $quantity, CartService $carts): void
    {
        $this->authorizePos();
        $carts->setQuantity($this->activeCart($carts), $itemId, $quantity);
    }

    public function removeItem(int $itemId, CartService $carts): void
    {
        $this->authorizePos();
        $carts->remove($this->activeCart($carts), $itemId);
    }

    public function saveDetails(CartService $carts): void
    {
        $this->authorizePos();
        $this->persistDetails($carts);
        $this->dispatch('toast', type: 'success', message: __('Detail keranjang diperbarui.'));
    }

    public function hold(CartService $carts): void
    {
        $this->authorizePos('pos-hold');
        $data = $this->validate(['holdLabel' => ['required', 'string', 'min:1', 'max:120']]);
        $this->persistDetails($carts);
        $carts->hold($this->activeCart($carts), $data['holdLabel']);
        $this->holdLabel = '';
        $this->customerId = null;
        $this->notes = '';
        $this->dispatch('toast', type: 'success', message: __('Keranjang disimpan untuk dilanjutkan.'));
    }

    public function resume(int $cartId, CartService $carts): void
    {
        $this->authorizePos('pos-hold');
        $shift = $this->activeShift();
        $cart = Cart::query()->whereKey($cartId)->where('cashier_shift_id', $shift->id)->where('user_id', auth()->id())->firstOrFail();
        $carts->resume($cart);
        $this->customerId = $cart->customer_id;
        $this->notes = $cart->notes ?? '';
        $this->dispatch('pos-cart-open');
    }

    public function discard(int $cartId, CartService $carts): void
    {
        $this->authorizePos('pos-hold');
        $shift = $this->activeShift();
        $cart = Cart::query()->whereKey($cartId)->where('cashier_shift_id', $shift->id)->where('user_id', auth()->id())->firstOrFail();
        $carts->discard($cart);
    }

    public function pay(CheckoutService $checkout, CartService $carts): void
    {
        $this->authorizePos('pos-checkout');
        $this->validate(['cashReceived' => ['required', 'numeric', 'min:0', 'max:9999999999999.99']]);
        $this->persistDetails($carts);
        $transaction = $checkout->cash($this->activeCart($carts), $this->cashReceived);
        $this->lastTransaction = $transaction->number;
        $this->lastTransactionId = $transaction->id;
        $this->cashReceived = '';
        $this->customerId = null;
        $this->notes = '';
        $this->dispatch('toast', type: 'success', message: __('Pembayaran berhasil. Transaksi :number', ['number' => $transaction->number]));
    }

    public function payLater(CheckoutService $checkout, CartService $carts): void
    {
        $this->authorizePos('pos-checkout');
        $this->persistDetails($carts);
        $transaction = $checkout->payLater($this->activeCart($carts));
        $this->lastTransaction = $transaction->number;
        $this->lastTransactionId = $transaction->id;
        $this->customerId = null;
        $this->notes = '';
        $this->dispatch('toast', type: 'success', message: __('Transaksi dicatat sebagai piutang.'));
    }

    public function payQris(CheckoutService $checkout, CartService $carts): void
    {
        $this->authorizePos('pos-checkout');
        $data = $this->validate(['qrisReference' => ['nullable', 'string', 'max:160']]);
        $bankAccount = BankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->firstOrFail();
        $this->persistDetails($carts);
        $transaction = $checkout->qris($this->activeCart($carts), $bankAccount->id, $data['qrisReference'] ?? null);
        $this->lastTransaction = $transaction->number;
        $this->lastTransactionId = $transaction->id;
        $this->qrisReference = '';
        $this->customerId = null;
        $this->notes = '';
        $this->dispatch('toast', type: 'success', message: __('Pembayaran QRIS tercatat ke rekening finance. Transaksi :number', ['number' => $transaction->number]));
    }

    public function render(CartService $carts)
    {
        $this->authorizePos();
        $shift = $this->activeShiftOrNull();
        if (! $shift) {
            $outlets = app(OutletAccess::class)->outletsFor(auth()->user())->orderBy('name')->get(['id', 'name']);

            return view('livewire.pos.checkout', ['shift' => null, 'products' => collect(), 'cart' => null, 'heldCarts' => collect(), 'customers' => collect(), 'previewTotal' => 0, 'previewLines' => [], 'payLaterEnabled' => false, 'qrisEnabled' => false, 'bankAccounts' => collect(), 'outlets' => $outlets])
                ->layout('layouts.dashboard', ['title' => __('Kasir')]);
        }
        $cart = $carts->activeFor($shift)->load('items.product.unit', 'items.product.productUnits', 'items.unit');
        $warehouseId = $shift->warehouse_id;
        $products = Product::query()->with(['unit:id,name,symbol', 'productUnits.unit:id,name,symbol', 'warehouseStocks' => fn ($query) => $query->where('warehouse_id', $warehouseId)])
            ->where('is_active', true)->where('is_sellable', true)
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')->orWhere('barcode', 'like', '%'.$this->search.'%')->orWhereHas('productUnits', fn (Builder $unit) => $unit->where('barcode', 'like', '%'.$this->search.'%'))))
            ->where(function (Builder $query) use ($warehouseId) {
                $query->where('track_stock', false)
                    ->orWhereHas('warehouseStocks', fn (Builder $stock) => $stock->where('warehouse_id', $warehouseId)->whereColumn('stock', '>', 'reserved_stock'))
                    ->orWhereHas('recipeVersions', fn (Builder $recipe) => $recipe->where('is_active', true)->whereHas('productItems'));
            })->orderBy('title')->paginate(12);
        $heldCarts = Cart::query()->withCount('items')->where('user_id', auth()->id())->where('cashier_shift_id', $shift->id)->where('status', 'held')->orderByDesc('held_at')->get();
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'phone']);
        $payLaterEnabled = PaymentMethod::query()->where('code', 'pay-later')->where('is_enabled', true)->exists();
        $qrisEnabled = PaymentMethod::query()->where('code', 'qris')->where('is_enabled', true)->exists();
        $bankAccounts = $qrisEnabled ? BankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('bank_name')->get() : collect();
        $defaultTaxRate = (float) Setting::value('default_tax_rate', 0, $shift->outlet_id);
        $defaultTaxInclusive = Setting::value('tax_mode', 'exclusive', $shift->outlet_id) === 'inclusive';
        $previewLines = [];
        $previewTotal = $cart->items->sum(function ($item) use ($defaultTaxRate, $defaultTaxInclusive, &$previewLines) {
            $product = $item->product;
            $unit = $product->unit_id === $item->unit_id ? null : $product->productUnits->firstWhere('unit_id', $item->unit_id);
            $price = $unit?->sell_price !== null ? FixedDecimal::money((string) $unit->sell_price)
                : (int) round(FixedDecimal::money((string) $product->sell_price) * (float) ($unit?->conversion_factor ?? 1));
            $gross = (int) round($price * FixedDecimal::quantity((string) $item->quantity) / 1000);
            $net = max(0, $gross - FixedDecimal::money((string) $item->manual_discount));
            $rate = (int) round((float) ($product->tax_rate ?? $defaultTaxRate) * 100);
            $inclusive = $product->tax_rate === null ? $defaultTaxInclusive : $product->tax_inclusive;
            $tax = $inclusive ? (int) round($net * $rate / (10000 + $rate)) : (int) round($net * $rate / 10000);
            $previewLines[$item->id] = $inclusive ? $net : $net + $tax;

            return $previewLines[$item->id];
        });

        $outlets = app(OutletAccess::class)->outletsFor(auth()->user())->orderBy('name')->get(['id', 'name']);

        return view('livewire.pos.checkout', compact('shift', 'products', 'cart', 'heldCarts', 'customers', 'previewTotal', 'previewLines', 'payLaterEnabled', 'qrisEnabled', 'bankAccounts', 'outlets'))
            ->layout('layouts.dashboard', ['title' => __('Kasir')]);
    }

    private function activeCart(CartService $carts): Cart
    {
        return $carts->activeFor($this->activeShift());
    }

    private function persistDetails(CartService $carts): void
    {
        $data = $this->validate(['customerId' => ['nullable', 'integer', 'exists:customers,id'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $carts->updateDetails($this->activeCart($carts), $data['customerId'] ?? null, $data['notes'] ?? null);
    }

    private function activeShift(): CashierShift
    {
        return $this->activeShiftOrNull() ?? throw ValidationException::withMessages(['shift' => __('Buka shift kasir sebelum menggunakan POS.')]);
    }

    private function activeShiftOrNull(): ?CashierShift
    {
        $outletId = (int) session('active_outlet_id');
        if (! $outletId) {
            return null;
        }

        return CashierShift::query()->where('open_user_id', auth()->id())->where('outlet_id', $outletId)->where('status', 'open')->first();
    }

    private function authorizePos(string $permission = 'pos-access'): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
