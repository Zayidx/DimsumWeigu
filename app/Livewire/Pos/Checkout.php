<?php

namespace App\Livewire\Pos;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Services\Cashier\CartService;
use App\Services\Cashier\CashierStockAvailability;
use App\Services\Cashier\CheckoutService;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Checkout extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $favoritesOnly = false;

    public string $cashReceived = '';

    public string $holdLabel = '';

    public ?int $customerId = null;

    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFavoritesOnly(): void
    {
        $this->resetPage();
    }

    public function toggleFavorite(int $productId): void
    {
        $this->authorizePos();
        Product::query()->whereKey($productId)->where('is_active', true)->where('is_sellable', true)->firstOrFail();

        $favorite = DB::table('cashier_favorite_products')->where('user_id', auth()->id())->where('product_id', $productId)->exists();
        if ($favorite) {
            DB::table('cashier_favorite_products')->where('user_id', auth()->id())->where('product_id', $productId)->delete();
        } else {
            DB::table('cashier_favorite_products')->insertOrIgnore([
                'user_id' => auth()->id(), 'product_id' => $productId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

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
    }

    public function addProductUnit(int $productId, int $unitId, CartService $carts): void
    {
        $this->authorizePos();
        $carts->add($this->activeCart($carts), $productId, $unitId);
    }

    public function scanProduct(CartService $carts): void
    {
        $this->authorizePos();
        $barcode = trim($this->search);

        if ($barcode === '' || mb_strlen($barcode) > 120) {
            return;
        }

        $unit = ProductUnit::query()
            ->where('barcode', $barcode)
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true)->where('is_sellable', true))
            ->first(['product_id', 'unit_id']);

        if ($unit) {
            $carts->add($this->activeCart($carts), (int) $unit->product_id, (int) $unit->unit_id);
            $this->search = '';

            return;
        }

        $product = Product::query()
            ->where(fn (Builder $query) => $query->where('barcode', $barcode)->orWhere('sku', $barcode))
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->first(['id', 'allow_single_unit_sale']);

        if ($product && $product->allow_single_unit_sale) {
            $carts->add($this->activeCart($carts), (int) $product->id);
            $this->search = '';

            return;
        }

        $this->dispatch('toast', type: 'warning', message: __('Produk tidak cocok. Pilih produk dari hasil pencarian.'));
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
        $this->redirectRoute('transactions.show', ['transaction' => $transaction->id, 'from' => 'pos'], navigate: true);
    }

    public function payLater(CheckoutService $checkout, CartService $carts): void
    {
        $this->authorizePos('pos-checkout');
        $this->persistDetails($carts);
        $transaction = $checkout->payLater($this->activeCart($carts));
        $this->redirectRoute('transactions.show', ['transaction' => $transaction->id, 'from' => 'pos'], navigate: true);
    }

    public function payQris(CheckoutService $checkout, CartService $carts): void
    {
        $this->authorizePos('pos-checkout');
        $this->persistDetails($carts);
        $transaction = $checkout->qris($this->activeCart($carts));
        $this->redirectRoute('transactions.show', ['transaction' => $transaction->id, 'from' => 'pos'], navigate: true);
    }

    public function render(CartService $carts)
    {
        $this->authorizePos();
        $shift = $this->activeShiftOrNull();
        if (! $shift) {
            $outlets = app(OutletAccess::class)->outletsFor(auth()->user())->orderBy('name')->get(['id', 'name']);

            return view('livewire.pos.checkout', ['shift' => null, 'products' => collect(), 'cart' => null, 'heldCarts' => collect(), 'customers' => collect(), 'previewTotal' => 0, 'previewLines' => [], 'payLaterEnabled' => false, 'qrisEnabled' => false, 'outlets' => $outlets, 'favoriteProductIds' => [], 'stockAvailability' => []])
                ->layout('layouts.dashboard', ['title' => __('Kasir')]);
        }
        $cart = $carts->activeFor($shift)->load('items.product.unit', 'items.product.productUnits', 'items.unit');
        $warehouseId = $shift->warehouse_id;
        $products = Product::query()->with(['unit:id,name,symbol', 'productUnits.unit:id,name,symbol', 'warehouseStocks' => fn ($query) => $query->where('warehouse_id', $warehouseId)])
            ->where('is_active', true)->where('is_sellable', true)
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')->orWhere('barcode', 'like', '%'.$this->search.'%')->orWhereHas('productUnits', fn (Builder $unit) => $unit->where('barcode', 'like', '%'.$this->search.'%'))))
            ->when($this->favoritesOnly, fn (Builder $query) => $query->whereIn('products.id', DB::table('cashier_favorite_products')->join('products as favorite_products', 'favorite_products.id', '=', 'cashier_favorite_products.product_id')->where('cashier_favorite_products.user_id', auth()->id())->whereNull('favorite_products.deleted_at')->where('favorite_products.is_active', true)->where('favorite_products.is_sellable', true)->select('cashier_favorite_products.product_id')))
            ->orderBy('title')->paginate(12);
        $favoriteProductIds = DB::table('cashier_favorite_products')->join('products as favorite_products', 'favorite_products.id', '=', 'cashier_favorite_products.product_id')
            ->where('cashier_favorite_products.user_id', auth()->id())->whereNull('favorite_products.deleted_at')->where('favorite_products.is_active', true)->where('favorite_products.is_sellable', true)
            ->pluck('cashier_favorite_products.product_id')->map(fn ($id) => (int) $id)->all();
        $stockAvailability = [];
        foreach ($products as $product) {
            $saleUnits = $product->productUnits->filter(fn (ProductUnit $unit) => $unit->unit_id !== $product->unit_id || $product->allow_single_unit_sale);
            foreach ($saleUnits as $unit) {
                $availability = app(CashierStockAvailability::class)->forSale($product, (int) $unit->unit_id, $warehouseId);
                $cartItem = $cart->items->first(fn ($item) => $item->product_id === $product->id && $item->unit_id === $unit->unit_id);
                $inCartMilli = $cartItem ? FixedDecimal::quantity((string) $cartItem->quantity) : 0;
                $availability['remaining_milli'] = $availability['quantity_milli'] === null
                    ? null
                    : max(0, $availability['quantity_milli'] - $inCartMilli);
                $stockAvailability[$product->id][$unit->unit_id] = $availability;
            }
        }
        $heldCarts = Cart::query()->withCount('items')->where('user_id', auth()->id())->where('cashier_shift_id', $shift->id)->where('status', 'held')->orderByDesc('held_at')->get();
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'phone']);
        $payLaterEnabled = PaymentMethod::query()->where('code', 'pay-later')->where('is_enabled', true)->exists();
        $qrisEnabled = PaymentMethod::query()->where('code', 'qris')->where('is_enabled', true)->exists();
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

        return view('livewire.pos.checkout', compact('shift', 'products', 'cart', 'heldCarts', 'customers', 'previewTotal', 'previewLines', 'payLaterEnabled', 'qrisEnabled', 'outlets', 'favoriteProductIds', 'stockAvailability'))
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
