<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $customerId = null;
    public string $code = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $birthDate = '';
    public string $creditLimit = '0';
    public string $notes = '';
    public string $memberTier = 'regular';
    public bool $marketingConsent = false;
    public bool $isActive = true;

    public function updatingSearch(): void { $this->resetPage(); }
    public function create(): void { $this->permit('customers-create'); $this->clearForm(); $this->showForm = true; }

    public function edit(int $id): void
    {
        $this->permit('customers-edit');
        $customer = Customer::query()->findOrFail($id);
        $this->customerId = $customer->id; $this->code = $customer->code; $this->name = $customer->name;
        $this->email = $customer->email ?? ''; $this->phone = $customer->phone ?? ''; $this->birthDate = $customer->birth_date?->format('Y-m-d') ?? '';
        $this->creditLimit = (string) $customer->credit_limit; $this->notes = $customer->notes ?? '';
        $this->memberTier = $customer->member_tier; $this->marketingConsent = $customer->marketing_consent; $this->isActive = $customer->is_active; $this->showForm = true;
    }

    public function closeForm(): void { $this->clearForm(); }

    public function save(AuditLogger $audit): void
    {
        $this->permit($this->customerId ? 'customers-edit' : 'customers-create');
        $this->code = strtoupper(trim($this->code));
        $data = $this->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('customers', 'code')->ignore($this->customerId)],
            'name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'], 'birthDate' => ['nullable', 'date', 'before_or_equal:today'],
            'creditLimit' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'notes' => ['nullable', 'string', 'max:3000'], 'memberTier' => ['required', Rule::in(['regular', 'silver', 'gold', 'platinum'])],
            'marketingConsent' => ['boolean'], 'isActive' => ['boolean'],
        ]);
        $customer = $this->customerId ? Customer::query()->findOrFail($this->customerId) : new Customer;
        $before = $this->customerId ? $customer->only(['code', 'name', 'email', 'phone', 'credit_limit', 'member_tier', 'marketing_consent', 'is_active']) : null;
        $customer->fill([
            'code' => $data['code'], 'name' => trim($data['name']), 'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null, 'birth_date' => $data['birthDate'] ?: null,
            'credit_limit' => $data['creditLimit'], 'notes' => $data['notes'] ?: null,
            'member_tier' => $data['memberTier'], 'marketing_consent' => $data['marketingConsent'], 'is_active' => $data['isActive'],
        ])->save();
        $audit->record($before ? 'customer.updated' : 'customer.created', $customer, $before, $customer->only(['code', 'name', 'email', 'phone', 'credit_limit', 'member_tier', 'marketing_consent', 'is_active']));
        $this->clearForm();
        $this->dispatch('toast', type: 'success', message: __('Pelanggan berhasil disimpan.'));
    }

    public function deactivate(int $id, AuditLogger $audit): void
    {
        $this->permit('customers-delete');
        $customer = Customer::query()->findOrFail($id);
        $customer->forceFill(['is_active' => false, 'marketing_consent' => false])->save();
        $audit->record('customer.deactivated', $customer, ['is_active' => true], ['is_active' => false, 'marketing_consent' => false]);
    }

    public function render()
    {
        $this->permit('customers-access');
        return view('livewire.customers.customers-index', ['customers' => Customer::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('phone', 'like', '%'.$this->search.'%')))
            ->orderBy('name')->paginate(20)])->layout('layouts.dashboard', ['title' => __('Pelanggan')]);
    }

    private function clearForm(): void
    {
        $this->showForm = false; $this->customerId = null; $this->code = $this->name = $this->email = $this->phone = $this->birthDate = $this->notes = ''; $this->creditLimit = '0'; $this->memberTier = 'regular'; $this->marketingConsent = false; $this->isActive = true; $this->resetValidation();
    }
    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
