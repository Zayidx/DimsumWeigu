<?php

namespace App\Livewire\Purchasing;

use App\Models\Supplier;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SuppliersIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $supplierId = null;
    public string $code = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $taxNumber = '';
    public string $address = '';
    public string $city = '';
    public string $notes = '';
    public bool $isActive = true;

    public function updatingSearch(): void { $this->resetPage(); }
    public function create(): void { $this->permit('suppliers-create'); $this->clearForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $this->permit('suppliers-update');
        $supplier = Supplier::query()->findOrFail($id);
        $this->supplierId = $supplier->id; $this->code = $supplier->code; $this->name = $supplier->name;
        $this->email = $supplier->email ?? ''; $this->phone = $supplier->phone ?? ''; $this->taxNumber = $supplier->tax_number ?? '';
        $this->address = $supplier->address ?? ''; $this->city = $supplier->city ?? ''; $this->notes = $supplier->notes ?? '';
        $this->isActive = $supplier->is_active; $this->showForm = true;
    }

    public function closeForm(): void { $this->clearForm(); }

    public function save(AuditLogger $audit): void
    {
        $this->permit($this->supplierId ? 'suppliers-update' : 'suppliers-create');
        $this->code = strtoupper(trim($this->code));
        $data = $this->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('suppliers', 'code')->ignore($this->supplierId)],
            'name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'], 'taxNumber' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'], 'city' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:3000'], 'isActive' => ['boolean'],
        ]);
        $supplier = $this->supplierId ? Supplier::query()->findOrFail($this->supplierId) : new Supplier;
        $before = $this->supplierId ? $supplier->only(['code', 'name', 'email', 'phone', 'tax_number', 'is_active']) : null;
        $supplier->fill([
            'code' => $data['code'], 'name' => trim($data['name']), 'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null, 'tax_number' => $data['taxNumber'] ?: null,
            'address' => $data['address'] ?: null, 'city' => $data['city'] ?: null,
            'notes' => $data['notes'] ?: null, 'is_active' => $data['isActive'],
        ])->save();
        $audit->record($before ? 'supplier.updated' : 'supplier.created', $supplier, $before, $supplier->only(['code', 'name', 'email', 'phone', 'tax_number', 'is_active']));
        $this->clearForm();
        $this->dispatch('toast', type: 'success', message: __('Pemasok berhasil disimpan.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->permit('suppliers-delete');
        $supplier = Supplier::query()->findOrFail($id);
        $supplier->forceFill(['is_active' => false])->save();
        $audit->record('supplier.deactivated', $supplier, ['is_active' => true], ['is_active' => false]);
    }

    public function render()
    {
        $this->permit('suppliers-access');
        return view('livewire.purchasing.suppliers-index', ['suppliers' => Supplier::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
            ->orderBy('name')->paginate(20)])->layout('layouts.dashboard', ['title' => __('Pemasok')]);
    }

    private function clearForm(): void
    {
        $this->showForm = false; $this->supplierId = null; $this->code = $this->name = $this->email = $this->phone = $this->taxNumber = $this->address = $this->city = $this->notes = ''; $this->isActive = true; $this->resetValidation();
    }
    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
