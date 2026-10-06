<?php

namespace App\Services\Cashier;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\ProductWarehouse;
use App\Models\RecipeVersion;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function activeFor(CashierShift $shift): Cart
    {
        $this->assertShift($shift);

        return DB::transaction(function () use ($shift) {
            $locked = CashierShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            $this->assertShift($locked);

            return Cart::query()->firstOrCreate([
                'user_id' => auth()->id(), 'cashier_shift_id' => $locked->id, 'status' => 'active',
            ], [
                'uuid' => (string) Str::uuid(), 'outlet_id' => $locked->outlet_id, 'warehouse_id' => $locked->warehouse_id,
            ]);
        }, attempts: 3);
    }

    public function add(Cart $cart, int $productId, ?int $unitId = null): CartItem
    {
        return DB::transaction(function () use ($cart, $productId, $unitId) {
            $cart = $this->lockActive($cart->id);
            $product = Product::query()->whereKey($productId)->where('is_active', true)->where('is_sellable', true)->firstOrFail();
            $stock = ProductWarehouse::query()->where('product_id', $product->id)->where('warehouse_id', $cart->warehouse_id)->first();
            $unitId ??= $product->unit_id;
            if ($unitId === $product->unit_id && ! $product->allow_single_unit_sale) {
                throw ValidationException::withMessages(['unit' => __('Penjualan wajib memilih satuan paket.')]);
            }
            $productUnit = ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $unitId)
                ->where('is_base', $unitId === $product->unit_id)->firstOrFail();
            $this->assertAssemblyRecipeAvailable($product, $unitId);
            $assembledAtSale = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $unitId)->where('is_active', true)->whereHas('productItems')->exists();
            $item = CartItem::query()->firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id, 'unit_id' => $unitId]);
            $nextQty = (float) $item->quantity + 1;
            $stockQuantity = $nextQty * (float) ($productUnit?->conversion_factor ?? 1);
            if ($product->track_stock && ! $assembledAtSale && (! $stock || $stockQuantity > (float) $stock->stock - (float) $stock->reserved_stock)) {
                throw ValidationException::withMessages(['product' => __('Produk tidak tersedia di gudang aktif.')]);
            }
            $item->quantity = number_format($nextQty, 3, '.', '');
            $item->save();

            return $item->refresh();
        }, attempts: 3);
    }

    public function setQuantity(Cart $cart, int $itemId, string $quantity): void
    {
        try {
            $qtyMilli = FixedDecimal::quantity($quantity);
        } catch (\InvalidArgumentException) {
            $qtyMilli = 0;
        }
        $qty = $qtyMilli / 1000;
        if ($qtyMilli <= 0 || $qtyMilli > 999999999999999) {
            throw ValidationException::withMessages(['quantity' => __('Jumlah harus lebih besar dari nol.')]);
        }
        DB::transaction(function () use ($cart, $itemId, $qty) {
            $cart = $this->lockActive($cart->id);
            $item = CartItem::query()->where('cart_id', $cart->id)->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $product = Product::query()->findOrFail($item->product_id);
            if ($product->unit_id === $item->unit_id && ! $product->allow_single_unit_sale) {
                throw ValidationException::withMessages(['unit' => __('Penjualan wajib memilih satuan paket.')]);
            }
            $productUnit = ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $item->unit_id)
                ->where('is_base', $item->unit_id === $product->unit_id)->firstOrFail();
            $this->assertAssemblyRecipeAvailable($product, (int) $item->unit_id);
            $assembledAtSale = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $item->unit_id)->where('is_active', true)->whereHas('productItems')->exists();
            $stockQty = $qty * (float) ($productUnit?->conversion_factor ?? 1);
            $stock = ProductWarehouse::query()->where('product_id', $product->id)->where('warehouse_id', $cart->warehouse_id)->first();
            if ($product->track_stock && ! $assembledAtSale && (! $stock || $stockQty > (float) $stock->stock - (float) $stock->reserved_stock)) {
                throw ValidationException::withMessages(['quantity' => __('Jumlah melebihi stok tersedia.')]);
            }
            $item->quantity = number_format($qty, 3, '.', '');
            $item->save();
        }, attempts: 3);
    }

    public function remove(Cart $cart, int $itemId): void
    {
        DB::transaction(function () use ($cart, $itemId) {
            $cart = $this->lockActive($cart->id);
            CartItem::query()->where('cart_id', $cart->id)->whereKey($itemId)->delete();
        });
    }

    public function updateDetails(Cart $cart, ?int $customerId, ?string $notes): void
    {
        if ($notes !== null && mb_strlen($notes) > 2000) {
            throw ValidationException::withMessages(['notes' => __('Catatan maksimal 2.000 karakter.')]);
        }
        DB::transaction(function () use ($cart, $customerId, $notes) {
            $cart = $this->lockActive($cart->id);
            if ($customerId !== null) {
                Customer::query()->whereKey($customerId)->where('is_active', true)->firstOrFail();
            }
            $cart->forceFill(['customer_id' => $customerId, 'notes' => $notes])->save();
        });
    }

    public function hold(Cart $cart, string $label): Cart
    {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 120) {
            throw ValidationException::withMessages(['holdLabel' => __('Nama hold wajib diisi (maksimal 120 karakter).')]);
        }

        return DB::transaction(function () use ($cart, $label) {
            $cart = $this->lockActive($cart->id);
            if (! $cart->items()->exists()) {
                throw ValidationException::withMessages(['cart' => __('Keranjang kosong tidak dapat ditahan.')]);
            }
            $cart->forceFill(['status' => 'held', 'hold_label' => $label, 'held_at' => now()])->save();

            return $cart->refresh();
        });
    }

    public function resume(Cart $held): Cart
    {
        return DB::transaction(function () use ($held) {
            $held = Cart::query()->whereKey($held->id)->lockForUpdate()->firstOrFail();
            $this->assertShiftId($held->cashier_shift_id, $held->warehouse_id, $held->outlet_id);
            if ($held->status !== 'held') {
                throw ValidationException::withMessages(['cart' => __('Keranjang ini tidak lagi berstatus hold.')]);
            }
            foreach ($held->items()->get() as $item) {
                $product = Product::where('is_active', true)->where('is_sellable', true)->findOrFail($item->product_id);
                if ($product->unit_id === $item->unit_id && ! $product->allow_single_unit_sale) {
                    throw ValidationException::withMessages(['cart' => __('Keranjang memuat satuan jual yang tidak diizinkan.')]);
                }
                $unit = $product->unit_id === $item->unit_id ? null : ProductUnit::where('product_id', $product->id)->where('unit_id', $item->unit_id)->firstOrFail();
                $this->assertAssemblyRecipeAvailable($product, (int) $item->unit_id);
                $needed = (float) $item->quantity * (float) ($unit?->conversion_factor ?? 1);
                $assembledAtSale = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $item->unit_id)
                    ->where('is_active', true)->whereHas('productItems')->exists();
                if ($product->track_stock && ! $assembledAtSale) {
                    $stock = ProductWarehouse::where('product_id', $product->id)->where('warehouse_id', $held->warehouse_id)->first();
                    $available = $stock ? (float) $stock->stock - (float) $stock->reserved_stock : 0;
                    if ($product->track_batches) {
                        $usable = ProductBatch::where('product_id', $product->id)->where('warehouse_id', $held->warehouse_id)->where('status', 'active')
                            ->where(fn ($dates) => $dates->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->sum('quantity_remaining');
                        $available = min($available, (float) $usable);
                    }
                    if ($needed > $available) {
                        throw ValidationException::withMessages(['cart' => __('Stok :product berubah. Keranjang hold tidak dapat dilanjutkan.', ['product' => $product->title])]);
                    }
                }
            }
            $active = Cart::query()->where('user_id', auth()->id())->where('cashier_shift_id', $held->cashier_shift_id)->where('status', 'active')->lockForUpdate()->first();
            if ($active) {
                if ($active->items()->exists()) {
                    throw ValidationException::withMessages(['cart' => __('Tahan keranjang aktif sebelum melanjutkan keranjang lain.')]);
                }
                $active->forceFill(['status' => 'discarded'])->save();
            }
            $held->forceFill(['status' => 'active', 'held_at' => null])->save();

            return $held->refresh();
        }, attempts: 3);
    }

    public function discard(Cart $held): void
    {
        DB::transaction(function () use ($held) {
            $held = Cart::query()->whereKey($held->id)->where('status', 'held')->lockForUpdate()->firstOrFail();
            $this->assertShiftId($held->cashier_shift_id, $held->warehouse_id, $held->outlet_id);
            $held->forceFill(['status' => 'discarded'])->save();
        });
    }

    private function lockActive(int $id): Cart
    {
        $cart = Cart::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        $this->assertShiftId($cart->cashier_shift_id, $cart->warehouse_id, $cart->outlet_id);
        if ($cart->status !== 'active') {
            throw ValidationException::withMessages(['cart' => __('Keranjang sudah tidak aktif.')]);
        }

        return $cart;
    }

    private function assertShift(CashierShift $shift): void
    {
        $this->assertShiftId($shift->id, $shift->warehouse_id, $shift->outlet_id);
    }

    private function assertShiftId(int $shiftId, int $warehouseId, int $outletId): void
    {
        abort_unless(auth()->user()?->can('pos-access'), 403);
        $shift = CashierShift::query()->whereKey($shiftId)->where('open_user_id', auth()->id())->where('status', 'open')->firstOrFail();
        abort_unless($shift->warehouse_id === $warehouseId && $shift->outlet_id === $outletId, 404);
        app(OutletAccess::class)->require(auth()->user(), $outletId);
    }

    private function assertAssemblyRecipeAvailable(Product $product, int $unitId): void
    {
        $hasAssemblyRecipes = RecipeVersion::query()->where('product_id', $product->id)->where('is_active', true)->whereHas('productItems')->exists();
        $hasUnitRecipe = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $unitId)->where('is_active', true)->whereHas('productItems')->exists();
        if ($hasAssemblyRecipes && ! $hasUnitRecipe) {
            throw ValidationException::withMessages(['product' => __('Komposisi menu ini belum disiapkan untuk satuan yang dipilih.')]);
        }
    }
}
