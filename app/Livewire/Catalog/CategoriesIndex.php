<?php

namespace App\Livewire\Catalog;

use App\Models\Category;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CategoriesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $categoryId = null;
    public ?int $parentId = null;
    public string $name = '';
    public string $description = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    public function updatingSearch(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->authorizePermission('categories-create');
        $this->clearForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('categories-edit');
        $category = Category::query()->findOrFail($id);
        $this->categoryId = $category->id;
        $this->parentId = $category->parent_id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->sortOrder = $category->sort_order;
        $this->isActive = $category->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void { $this->clearForm(); }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission($this->categoryId ? 'categories-edit' : 'categories-create');
        $this->name = trim($this->name);
        $slug = Str::slug($this->name);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'parentId' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn(array_filter([$this->categoryId]))],
            'description' => ['nullable', 'string', 'max:2000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
        ]);
        validator(['slug' => $slug], ['slug' => ['required', 'max:140', Rule::unique('categories', 'slug')->ignore($this->categoryId)]])->validate();
        $category = $this->categoryId ? Category::query()->findOrFail($this->categoryId) : new Category;
        $before = $this->categoryId ? $category->only(['parent_id', 'name', 'slug', 'sort_order', 'is_active']) : null;
        $category->fill([
            'parent_id' => $data['parentId'] ?? null, 'name' => $data['name'], 'slug' => $slug,
            'description' => $data['description'] ?: null, 'sort_order' => $data['sortOrder'], 'is_active' => $data['isActive'],
        ])->save();
        $audit->record($before ? 'category.updated' : 'category.created', $category, $before, $category->only(['parent_id', 'name', 'slug', 'sort_order', 'is_active']));
        $this->clearForm();
        $this->dispatch('toast', type: 'success', message: __('Kategori berhasil disimpan.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('categories-delete');
        $category = Category::query()->findOrFail($id);
        if ($category->products()->where('is_active', true)->exists() || $category->children()->where('is_active', true)->exists()) {
            $this->addError('category', __('Kategori masih dipakai produk atau subkategori aktif.'));
            return;
        }
        $category->forceFill(['is_active' => false])->save();
        $audit->record('category.deactivated', $category, ['is_active' => true], ['is_active' => false]);
    }

    public function render()
    {
        $this->authorizePermission('categories-access');
        return view('livewire.catalog.categories-index', [
            'categories' => Category::query()->with('parent:id,name')->withCount('products')
                ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%')->orWhere('slug', 'like', '%'.$this->search.'%'))
                ->orderBy('sort_order')->orderBy('name')->paginate(20),
            'parents' => Category::query()->where('is_active', true)->when($this->categoryId, fn (Builder $query) => $query->whereKeyNot($this->categoryId))->orderBy('name')->get(['id', 'name', 'parent_id']),
        ])->layout('layouts.dashboard', ['title' => __('Kategori')]);
    }

    private function clearForm(): void
    {
        $this->showForm = false; $this->categoryId = null; $this->parentId = null;
        $this->name = ''; $this->description = ''; $this->sortOrder = 0; $this->isActive = true;
        $this->resetValidation();
    }

    private function authorizePermission(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
