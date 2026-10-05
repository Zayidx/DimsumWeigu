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

    public string $notes = '';

    public array $lines = [];

    public function create(): void
    {
        $this->authorizePermission('recipes-create');
        $this->productId = '';
        $this->notes = '';
        $this->lines = [['ingredient_id' => '', 'quantity' => '1', 'waste_percent' => '0']];
        $this->showForm = true;
    }

    public function addLine(): void
    {
        $this->authorizePermission('recipes-create');
        $this->lines[] = ['ingredient_id' => '', 'quantity' => '', 'waste_percent' => '0'];
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'lines.*.waste_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $ids = collect($data['lines'])->pluck('ingredient_id');
        if ($ids->unique()->count() !== $ids->count()) {
            throw ValidationException::withMessages(['lines' => __('Bahan yang sama tidak boleh ditambahkan dua kali; gabungkan takarannya.')]);
        }
        $ingredients = Ingredient::query()->whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');
        if ($ingredients->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['lines' => __('Resep hanya dapat memakai bahan aktif.')]);
        }

        DB::transaction(function () use ($data, $audit): void {
            $product = Product::query()->where('is_active', true)->where('is_sellable', true)->lockForUpdate()->findOrFail($data['productId']);
            $active = RecipeVersion::query()->where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->first();
            $version = (int) RecipeVersion::query()->where('product_id', $product->id)->max('version') + 1;
            if ($active) {
                $active->update(['is_active' => false, 'ends_at' => now()]);
            }
            $recipe = RecipeVersion::query()->create([
                'product_id' => $product->id, 'unit_id' => $product->unit_id, 'version' => $version,
                'starts_at' => now(), 'is_active' => true, 'yield_quantity' => 1,
                'notes' => $data['notes'] ?: null, 'created_by' => auth()->id(),
            ]);
            foreach ($data['lines'] as $line) {
                $ingredient = Ingredient::query()->findOrFail($line['ingredient_id']);
                $recipe->items()->create([
                    'ingredient_id' => $ingredient->id, 'unit_id' => $ingredient->unit_id,
                    'quantity' => $line['quantity'], 'base_quantity' => $line['quantity'], 'waste_percent' => $line['waste_percent'],
                ]);
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
        $recipes = RecipeVersion::query()->with(['product.unit', 'items.ingredient.unit'])->where('is_active', true)->latest('id')->paginate(20);
        foreach ($recipes as $recipe) {
            $recipe->setAttribute('estimated_hpp', $costs->estimate($recipe));
        }

        return view('livewire.inventory.recipes-index', [
            'recipes' => $recipes,
            'products' => Product::query()->where('is_active', true)->where('is_sellable', true)->orderBy('title')->get(['id', 'title', 'sku', 'unit_id']),
            'ingredients' => Ingredient::query()->with('unit')->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.dashboard', ['title' => __('Resep & HPP')]);
    }
}
