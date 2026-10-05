<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class IngredientsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $ingredientId = null;

    public string $code = '';

    public string $name = '';

    public string $unitId = '';

    public string $categoryId = '';

    public string $buyPrice = '0';

    public string $description = '';

    public bool $isActive = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePermission('ingredients-create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('ingredients-update');
        $item = Ingredient::query()->findOrFail($id);
        $this->ingredientId = $item->id;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->unitId = (string) $item->unit_id;
        $this->categoryId = (string) ($item->ingredient_category_id ?? '');
        $this->buyPrice = (string) $item->buy_price;
        $this->description = (string) ($item->description ?? '');
        $this->isActive = (bool) $item->is_active;
        $this->showForm = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission($this->ingredientId ? 'ingredients-update' : 'ingredients-create');
        $data = $this->validate([
            'code' => ['required', 'string', 'max:48', Rule::unique('ingredients', 'code')->ignore($this->ingredientId)],
            'name' => ['required', 'string', 'max:150'],
            'unitId' => ['required', 'integer', 'exists:units,id'],
            'categoryId' => ['nullable', 'integer', 'exists:ingredient_categories,id'],
            'buyPrice' => ['required', 'numeric', 'min:0', 'max:9999999999.99999999', 'regex:/^\d+(?:\.\d{1,8})?$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ]);

        DB::transaction(function () use ($data, $audit): void {
            $item = $this->ingredientId ? Ingredient::query()->lockForUpdate()->findOrFail($this->ingredientId) : new Ingredient;
            if ($item->exists && strtoupper(trim($item->code)) !== strtoupper(trim($data['code']))) {
                throw ValidationException::withMessages(['code' => __('Kode bahan tidak dapat diubah setelah dibuat.')]);
            }
            if ($item->exists && (int) $item->unit_id !== (int) $data['unitId'] && $item->recipeItems()->exists()) {
                throw ValidationException::withMessages(['unitId' => __('Satuan tidak dapat diubah setelah bahan dipakai pada resep.')]);
            }
            $before = $item->exists ? $item->only(['code', 'name', 'unit_id', 'ingredient_category_id', 'buy_price', 'is_active']) : null;
            $item->fill([
                'code' => strtoupper(trim($data['code'])), 'name' => trim($data['name']), 'unit_id' => $data['unitId'],
                'ingredient_category_id' => $data['categoryId'] ?: null, 'buy_price' => $this->decimal8((string) $data['buyPrice']),
                'description' => $data['description'] ?: null, 'is_active' => $data['isActive'],
            ])->save();
            $audit->record($before ? 'ingredient.updated' : 'ingredient.created', $item, $before, $item->only(['code', 'name', 'unit_id', 'ingredient_category_id', 'buy_price', 'is_active']));
        });

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Master bahan berhasil disimpan.'));
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'ingredientId', 'code', 'name', 'unitId', 'categoryId', 'description']);
        $this->buyPrice = '0';
        $this->isActive = true;
        $this->resetValidation();
    }

    private function decimal8(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ltrim($whole, '0') === '' ? '0.'.str_pad($fraction, 8, '0') : ltrim($whole, '0').'.'.str_pad($fraction, 8, '0');
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render()
    {
        return view('livewire.inventory.ingredients-index', [
            'ingredients' => Ingredient::query()->with(['unit', 'category'])->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%')))->orderBy('name')->paginate(20),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => IngredientCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.dashboard', ['title' => __('Master bahan')]);
    }
}
