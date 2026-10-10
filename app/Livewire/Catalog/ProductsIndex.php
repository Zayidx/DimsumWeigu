<?php

namespace App\Livewire\Catalog;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductWarehouse;
use App\Models\RecipeVersion;
use App\Models\TransactionDetail;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\Warehouse;
use App\Services\Production\RecipeCostService;
use App\Support\AuditLogger;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $productId = null;

    public ?int $categoryId = null;

    public ?int $unitId = null;

    public string $sku = '';

    public string $barcode = '';

    public string $title = '';

    public string $description = '';

    public string $buyPrice = '0';

    public string $sellPrice = '0';

    public string $taxRate = '';

    public string $minStock = '0';

    public string $reorderPoint = '0';

    public bool $taxInclusive = false;

    public bool $isActive = true;

    public bool $isSellable = true;

    public bool $isSemiFinished = false;

    public bool $allowSingleUnitSale = false;

    public bool $trackStock = true;

    public bool $trackBatches = false;

    public array $alternateUnits = [];

    public array $recipeLines = [];

    public $image;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIsSemiFinished(bool $value): void
    {
        if ($value) {
            $this->isSellable = false;
            $this->trackStock = true;
            $this->allowSingleUnitSale = false;
        }
    }

    public function openCreate(): void
    {
        $this->authorizeAction('products-create');
        $this->resetForm();
        $this->isSemiFinished = true;
        $this->isSellable = false;
        $this->recipeLines = [['ingredient_id' => '', 'quantity' => '']];
        $this->showForm = true;
    }

    public function addRecipeLine(): void
    {
        $this->recipeLines[] = ['ingredient_id' => '', 'quantity' => ''];
    }

    public function removeRecipeLine(int $index): void
    {
        if (count($this->recipeLines) > 1 && array_key_exists($index, $this->recipeLines)) {
            unset($this->recipeLines[$index]);
            $this->recipeLines = array_values($this->recipeLines);
        }
    }

    public function edit(int $id): void
    {
        $this->authorizeAction('products-edit');
        $product = Product::query()->findOrFail($id);
        $this->productId = $product->id;
        $this->categoryId = $product->category_id;
        $this->unitId = $product->unit_id;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode ?? '';
        $this->title = $product->title;
        $this->description = $product->description ?? '';
        $this->buyPrice = (string) $product->buy_price;
        $this->sellPrice = (string) $product->sell_price;
        $this->taxRate = $product->tax_rate === null ? '' : (string) $product->tax_rate;
        $this->minStock = (string) $product->min_stock;
        $this->reorderPoint = (string) $product->reorder_point;
        $this->taxInclusive = $product->tax_inclusive;
        $this->isActive = $product->is_active;
        $this->isSellable = $product->is_sellable;
        $this->isSemiFinished = $product->is_semi_finished;
        $this->allowSingleUnitSale = $product->allow_single_unit_sale;
        $this->trackStock = $product->track_stock;
        $this->trackBatches = $product->track_batches;
        $this->alternateUnits = $product->productUnits()->where('unit_id', '!=', $product->unit_id)->with('unit:id,name,symbol')->get()->map(fn (ProductUnit $row) => [
            'unit_id' => (string) $row->unit_id, 'conversion_factor' => (string) $row->conversion_factor,
            'sell_price' => $row->sell_price === null ? '' : (string) $row->sell_price, 'barcode' => $row->barcode ?? '',
        ])->all();
        $this->showForm = true;
    }

    public function addAlternateUnit(): void
    {
        $this->authorizeAction($this->productId ? 'products-edit' : 'products-create');
        $this->alternateUnits[] = ['unit_id' => '', 'conversion_factor' => '1', 'sell_price' => '', 'barcode' => ''];
    }

    public function removeAlternateUnit(int $index): void
    {
        $this->authorizeAction($this->productId ? 'products-edit' : 'products-create');
        unset($this->alternateUnits[$index]);
        $this->alternateUnits = array_values($this->alternateUnits);
    }

    public function updatedAlternateUnits(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.unit_id') || ! $this->unitId || ! is_numeric($value)) {
            return;
        }
        $conversion = UnitConversion::query()->where('from_unit_id', (int) $value)->where('to_unit_id', $this->unitId)->value('factor');
        if ($conversion !== null) {
            $index = (int) strtok($key, '.');
            $this->alternateUnits[$index]['conversion_factor'] = (string) $conversion;
        }
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizeAction($this->productId ? 'products-edit' : 'products-create');
        if (! $this->productId) {
            $this->createSemiFinished($audit);

            return;
        }
        $this->sku = strtoupper(trim($this->sku));
        $this->barcode = trim($this->barcode);
        $validated = $this->validate([
            'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
            'unitId' => ['required', 'integer', 'exists:units,id'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->productId)],
            'barcode' => ['nullable', 'string', 'max:96', Rule::unique('products', 'barcode')->ignore($this->productId)],
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'],
            'buyPrice' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'sellPrice' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minStock' => ['required', 'numeric', 'min:0', 'max:999999999999.999'],
            'reorderPoint' => ['required', 'numeric', 'min:0', 'max:999999999999.999'],
            'taxInclusive' => ['boolean'], 'isActive' => ['boolean'], 'isSellable' => ['boolean'], 'isSemiFinished' => ['boolean'], 'allowSingleUnitSale' => ['boolean'],
            'trackStock' => ['boolean'], 'trackBatches' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'alternateUnits' => ['nullable', 'array'],
            'alternateUnits.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct'],
            'alternateUnits.*.conversion_factor' => ['required', 'numeric', 'gt:0', 'max:999999999999.99999999'],
            'alternateUnits.*.sell_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'alternateUnits.*.barcode' => ['nullable', 'string', 'max:96', 'distinct'],
        ]);
        $baseUnit = Unit::query()->findOrFail((int) $validated['unitId']);
        if ($validated['isSemiFinished'] && $validated['isSellable']) {
            throw ValidationException::withMessages(['isSemiFinished' => __('Produk setengah jadi adalah stok rakitan dan tidak dijual langsung.')]);
        }
        if ($validated['isSemiFinished'] && ! $validated['trackStock']) {
            throw ValidationException::withMessages(['isSemiFinished' => __('Stok produk setengah jadi harus dilacak per gudang.')]);
        }
        if ($validated['isSemiFinished'] && $validated['allowSingleUnitSale']) {
            throw ValidationException::withMessages(['allowSingleUnitSale' => __('Produk setengah jadi tidak dijual langsung.')]);
        }
        foreach ($validated['alternateUnits'] ?? [] as $index => $alternate) {
            $unit = Unit::query()->findOrFail((int) $alternate['unit_id']);
            if ($unit->dimension !== $baseUnit->dimension || $unit->id === $baseUnit->id) {
                $this->addError("alternateUnits.$index.unit_id", __('Satuan tambahan harus berbeda dan memiliki dimensi yang sama dengan satuan dasar.'));

                return;
            }
            if ($alternate['barcode'] ?? false) {
                $duplicate = ProductUnit::query()->where('barcode', $alternate['barcode'])
                    ->when($this->productId, fn (Builder $query) => $query->where('product_id', '!=', $this->productId))->exists();
                if ($duplicate) {
                    $this->addError("alternateUnits.$index.barcode", __('Barcode telah dipakai produk lain.'));

                    return;
                }
            }
        }

        $imagePath = $this->image?->storePublicly('product-images', 'public');
        $payload = [
            'category_id' => $validated['categoryId'], 'unit_id' => $validated['unitId'],
            'sku' => strtoupper(trim($validated['sku'])), 'barcode' => $validated['barcode'] ?: null,
            'title' => trim($validated['title']), 'description' => $validated['description'] ?: null,
            'buy_price' => $validated['buyPrice'], 'sell_price' => $validated['sellPrice'],
            'tax_rate' => ($validated['taxRate'] ?? '') === '' ? null : $validated['taxRate'],
            'tax_inclusive' => $validated['taxInclusive'], 'min_stock' => $validated['minStock'],
            'reorder_point' => $validated['reorderPoint'], 'is_active' => $validated['isActive'],
            'is_sellable' => $validated['isSellable'], 'track_stock' => $validated['trackStock'],
            'is_semi_finished' => $validated['isSemiFinished'],
            'allow_single_unit_sale' => $validated['allowSingleUnitSale'],
            'track_batches' => $validated['trackBatches'],
        ];
        if ($imagePath) {
            $payload['image_path'] = $imagePath;
        }

        DB::transaction(function () use ($payload, $imagePath, $audit, $validated) {
            if ($this->productId) {
                $product = Product::query()->lockForUpdate()->findOrFail($this->productId);
                if ((int) $product->unit_id !== (int) $payload['unit_id'] && (
                    RecipeVersion::query()->where('product_id', $product->id)->exists()
                    || TransactionDetail::query()->where('product_id', $product->id)->exists()
                    || InventoryLedger::query()->where('product_id', $product->id)->exists()
                    || ProductWarehouse::query()->where('product_id', $product->id)->where('stock', '<>', 0)->exists()
                )) {
                    throw ValidationException::withMessages(['unitId' => __('Satuan dasar tidak dapat diubah karena produk sudah memiliki resep, stok, atau riwayat. Buat produk baru untuk mengganti satuan dasar.')]);
                }
                $before = $product->only(['sku', 'barcode', 'title', 'sell_price', 'buy_price', 'tax_rate', 'is_active']);
                $oldImage = $product->image_path;
                $product->fill($payload)->save();
                $this->syncAlternateUnits($product, $validated['alternateUnits'] ?? []);
                $audit->record('product.updated', $product, $before, $product->only(array_keys($before)));
                if ($imagePath && $oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            } else {
                $product = Product::query()->create($payload + ['stock' => 0]);
                $this->syncAlternateUnits($product, $validated['alternateUnits'] ?? []);
                foreach (Warehouse::query()->where('is_active', true)->get(['id']) as $warehouse) {
                    ProductWarehouse::query()->create([
                        'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
                        'stock' => 0, 'reserved_stock' => 0, 'min_stock' => $payload['min_stock'], 'reorder_point' => $payload['reorder_point'],
                    ]);
                    InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'calculated_quantity' => 0]);
                }
                $audit->record('product.created', $product, null, $product->only(['sku', 'barcode', 'title', 'sell_price', 'buy_price', 'tax_rate', 'is_active']));
            }
        });

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Produk berhasil disimpan.'));
    }

    private function createSemiFinished(AuditLogger $audit): void
    {
        $this->sku = strtoupper(trim($this->sku));
        $data = $this->validate([
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')],
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'],
            'minStock' => ['required', 'numeric', 'min:0', 'max:999999999999.999'],
            'isActive' => ['boolean'], 'trackBatches' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'recipeLines' => ['required', 'array', 'min:1', 'max:100'],
            'recipeLines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'recipeLines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
        ]);
        $ingredientIds = collect($data['recipeLines'])->pluck('ingredient_id');
        if ($ingredientIds->unique()->count() !== $ingredientIds->count()) {
            throw ValidationException::withMessages(['recipeLines' => __('Bahan yang sama tidak boleh ditambahkan dua kali; gabungkan takarannya.')]);
        }
        $ingredients = Ingredient::query()->whereIn('id', $ingredientIds)->where('is_active', true)->get()->keyBy('id');
        if ($ingredients->count() !== $ingredientIds->unique()->count()) {
            throw ValidationException::withMessages(['recipeLines' => __('Resep hanya dapat memakai bahan mentah aktif.')]);
        }
        $parentId = Category::query()->where('slug', 'setengah-jadi')->value('id');
        if (! $parentId || ! Category::query()->whereKey($data['categoryId'])->where('parent_id', $parentId)->exists()) {
            throw ValidationException::withMessages(['categoryId' => __('Pilih kategori produk setengah jadi.')]);
        }
        $unit = Unit::query()->where('code', 'PCS')->firstOrFail();
        $imagePath = $this->image?->storePublicly('product-images', 'public');

        DB::transaction(function () use ($data, $unit, $imagePath, $audit): void {
            $product = Product::query()->create([
                'category_id' => $data['categoryId'], 'unit_id' => $unit->id, 'sku' => $this->sku,
                'title' => trim($data['title']), 'description' => $data['description'] ?: null,
                'image_path' => $imagePath, 'buy_price' => 0, 'sell_price' => 0, 'tax_rate' => null,
                'tax_inclusive' => false, 'stock' => 0, 'min_stock' => $data['minStock'], 'reorder_point' => 0,
                'is_active' => $data['isActive'], 'is_sellable' => false, 'is_semi_finished' => true,
                'allow_single_unit_sale' => false, 'track_stock' => true, 'track_batches' => $data['trackBatches'],
            ]);
            foreach (Warehouse::query()->where('is_active', true)->get(['id']) as $warehouse) {
                ProductWarehouse::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'stock' => 0, 'reserved_stock' => 0, 'min_stock' => $data['minStock'], 'reorder_point' => 0]);
                InventoryBalance::query()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'calculated_quantity' => 0]);
            }
            $recipe = RecipeVersion::query()->create([
                'product_id' => $product->id, 'unit_id' => $unit->id, 'version' => 1,
                'starts_at' => now(), 'is_active' => true, 'yield_quantity' => 1, 'created_by' => auth()->id(),
            ]);
            foreach ($data['recipeLines'] as $line) {
                $ingredient = Ingredient::query()->findOrFail($line['ingredient_id']);
                $recipe->items()->create(['ingredient_id' => $ingredient->id, 'unit_id' => $ingredient->unit_id, 'quantity' => $line['quantity'], 'base_quantity' => $line['quantity'], 'waste_percent' => 0]);
            }
            $product->forceFill(['buy_price' => app(RecipeCostService::class)->estimate($recipe)])->save();
            $audit->record('product.created', $product, null, $product->only(['sku', 'title', 'buy_price', 'is_active', 'is_semi_finished']));
        });

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Produk setengah jadi dan resep berhasil disimpan.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->authorizeAction('products-delete');
        $product = Product::query()->findOrFail($id);
        $product->forceFill(['is_active' => false, 'is_sellable' => false])->save();
        $audit->record('product.deactivated', $product, ['is_active' => true], ['is_active' => false]);
        $this->dispatch('toast', type: 'success', message: __('Produk dinonaktifkan.'));
    }

    public function render()
    {
        $this->authorizeAction('products-access');
        $outletAccess = app(OutletAccess::class);
        $outlets = $outletAccess->outletsFor(auth()->user())->get(['id', 'name']);
        $warehouseId = (int) session('active_warehouse_id');
        $products = Product::query()->with('category:id,name', 'unit:id,name,symbol')
            ->where('is_semi_finished', false)
            ->with(['warehouseStocks' => fn ($query) => $warehouseId ? $query->where('warehouse_id', $warehouseId) : $query])
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')->orWhere('barcode', 'like', '%'.$this->search.'%')))
            ->orderBy('title')->paginate(20);

        return view('livewire.catalog.products-index', [
            'products' => $products, 'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'semiFinishedCategories' => Category::query()->where('is_active', true)->where('parent_id', Category::query()->where('slug', 'setengah-jadi')->value('id'))->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(), 'outlets' => $outlets,
            'ingredients' => Ingredient::query()->with('unit')->where('is_active', true)->orderBy('name')->get(),
            'warehouseId' => $warehouseId,
        ])->layout('layouts.dashboard', ['title' => __('Produk')]);
    }

    private function authorizeAction(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'productId', 'categoryId', 'unitId', 'sku', 'barcode', 'title', 'description', 'taxRate', 'image', 'alternateUnits', 'recipeLines']);
        $this->buyPrice = '0';
        $this->sellPrice = '0';
        $this->minStock = '0';
        $this->reorderPoint = '0';
        $this->taxInclusive = false;
        $this->isActive = true;
        $this->isSellable = true;
        $this->isSemiFinished = false;
        $this->allowSingleUnitSale = false;
        $this->trackStock = true;
        $this->trackBatches = false;
    }

    private function syncAlternateUnits(Product $product, array $rows): void
    {
        $keep = [];
        foreach ($rows as $row) {
            $unitId = (int) $row['unit_id'];
            $unit = ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $unitId],
                ['conversion_factor' => $row['conversion_factor'], 'sell_price' => ($row['sell_price'] ?? '') === '' ? null : $row['sell_price'], 'barcode' => ($row['barcode'] ?? '') ?: null, 'is_default' => false],
            );
            $keep[] = $unit->id;
        }
        $removed = ProductUnit::query()->where('product_id', $product->id)->where('unit_id', '!=', $product->unit_id)->when($keep !== [], fn (Builder $query) => $query->whereNotIn('id', $keep))->get();
        foreach ($removed as $unit) {
            if (CartItem::query()->where('product_id', $product->id)->where('unit_id', $unit->unit_id)->whereHas('cart', fn (Builder $cart) => $cart->whereIn('status', ['active', 'held']))->exists()) {
                throw ValidationException::withMessages(['alternateUnits' => __('Satuan tidak dapat dihapus karena masih dipakai pada keranjang aktif atau tertahan.')]);
            }
            $unit->delete();
        }
    }
}
