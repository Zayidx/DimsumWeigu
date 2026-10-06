<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeVersion;
use App\Services\Production\RecipeCostService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class RecipesIndex extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $productId = '';

    public string $recipeUnitId = '';

    public string $notes = '';

    public array $lines = [];

    public function create(): void
    {
        $this->authorizePermission('recipes-create');
        $this->productId = '';
        $this->recipeUnitId = '';
        $this->notes = '';
        $this->lines = [['type' => 'ingredient', 'ingredient_id' => '', 'component_product_id' => '', 'quantity' => '1', 'waste_percent' => '0']];
        $this->showForm = true;
    }

    public function addLine(): void
    {
        $this->authorizePermission('recipes-create');
        $this->lines[] = ['type' => 'ingredient', 'ingredient_id' => '', 'component_product_id' => '', 'quantity' => '', 'waste_percent' => '0'];
    }

    public function removeLine(int $index): void
    {
        $this->authorizePermission('recipes-create');
        if (count($this->lines) > 1 && array_key_exists($index, $this->lines)) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission('recipes-create');
        $data = $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'recipeUnitId' => ['required', 'integer', 'exists:units,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.type' => ['required', 'in:ingredient,product'],
            'lines.*.ingredient_id' => ['nullable', 'required_if:lines.*.type,ingredient', 'integer', 'exists:ingredients,id'],
            'lines.*.component_product_id' => ['nullable', 'required_if:lines.*.type,product', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'lines.*.waste_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $ingredientIds = collect($data['lines'])->where('type', 'ingredient')->pluck('ingredient_id');
        $componentIds = collect($data['lines'])->where('type', 'product')->pluck('component_product_id');
        if ($ingredientIds->filter()->unique()->count() !== $ingredientIds->filter()->count() || $componentIds->filter()->unique()->count() !== $componentIds->filter()->count()) {
            throw ValidationException::withMessages(['lines' => __('Komponen yang sama tidak boleh ditambahkan dua kali; gabungkan takarannya.')]);
        }
        $ingredients = Ingredient::query()->whereIn('id', $ingredientIds)->where('is_active', true)->get()->keyBy('id');
        $components = Product::query()->whereIn('id', $componentIds)->where('is_active', true)->where('is_semi_finished', true)->get()->keyBy('id');
        if ($ingredients->count() !== $ingredientIds->filter()->unique()->count() || $components->count() !== $componentIds->filter()->unique()->count()) {
            throw ValidationException::withMessages(['lines' => __('Resep hanya dapat memakai bahan aktif dan produk setengah jadi aktif.')]);
        }
        $output = Product::query()->whereKey($data['productId'])->firstOrFail();
        if ($output->is_semi_finished && $componentIds->filter()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => __('Resep produk setengah jadi harus langsung memakai bahan baku. Produk setengah jadi digunakan pada resep menu outlet.')]);
        }

        DB::transaction(function () use ($data, $audit): void {
            $product = Product::query()->where('is_active', true)->where(fn ($query) => $query->where('is_sellable', true)->orWhere('is_semi_finished', true))->with('productUnits')->lockForUpdate()->findOrFail($data['productId']);
            abort_unless((int) $data['recipeUnitId'] === (int) $product->unit_id || $product->productUnits->contains(fn ($unit) => (int) $unit->unit_id === (int) $data['recipeUnitId']), 422);
            $active = RecipeVersion::query()->where('product_id', $product->id)->where('unit_id', $data['recipeUnitId'])->where('is_active', true)->lockForUpdate()->first();
            $version = (int) RecipeVersion::query()->where('product_id', $product->id)->max('version') + 1;
            if ($active) {
                $active->update(['is_active' => false, 'ends_at' => now()]);
            }
            $recipe = RecipeVersion::query()->create([
                'product_id' => $product->id, 'unit_id' => $data['recipeUnitId'], 'version' => $version,
                'starts_at' => now(), 'is_active' => true, 'yield_quantity' => 1,
                'notes' => $data['notes'] ?: null, 'created_by' => auth()->id(),
            ]);
            foreach ($data['lines'] as $line) {
                if ($line['type'] === 'ingredient') {
                    $ingredient = Ingredient::query()->findOrFail($line['ingredient_id']);
                    $recipe->items()->create([
                        'ingredient_id' => $ingredient->id, 'unit_id' => $ingredient->unit_id,
                        'quantity' => $line['quantity'], 'base_quantity' => $line['quantity'], 'waste_percent' => $line['waste_percent'],
                    ]);
                } else {
                    $component = Product::query()->with('productUnits')->findOrFail($line['component_product_id']);
                    $componentUnitId = $component->unit_id;
                    $recipe->productItems()->create([
                        'product_id' => $component->id, 'unit_id' => $componentUnitId,
                        'quantity' => $line['quantity'], 'waste_percent' => $line['waste_percent'],
                    ]);
                }
            }
            $audit->record('recipe.version_created', $recipe, $active?->only(['id', 'version', 'is_active']), ['version' => $version, 'line_count' => count($data['lines'])]);
        });

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: __('Versi resep per satu unit berhasil disimpan.'));
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render(RecipeCostService $costs)
    {
        $recipes = RecipeVersion::query()->with(['product.unit', 'unit', 'items.ingredient.unit', 'productItems.product.unit'])->where('is_active', true)->latest('id')->paginate(20);
        foreach ($recipes as $recipe) {
            $recipe->setAttribute('estimated_hpp', $costs->estimate($recipe));
        }

        $products = Product::query()->with(['unit', 'productUnits.unit'])->where('is_active', true)->where(fn ($q) => $q->where('is_sellable', true)->orWhere('is_semi_finished', true))->orderBy('title')->get();

        return view('livewire.inventory.recipes-index', [
            'recipes' => $recipes,
            'products' => $products,
            'selectedProduct' => $products->firstWhere('id', (int) $this->productId),
            'semiFinishedProducts' => Product::query()->with('unit')->where('is_active', true)->where('is_semi_finished', true)->orderBy('title')->get(['id', 'title', 'sku', 'unit_id']),
            'ingredients' => Ingredient::query()->with('unit')->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.dashboard', ['title' => __('Resep & HPP')]);
    }
}
