<?php

namespace App\Livewire\Purchasing;

use App\Models\GoodsReceiving;
use App\Models\Payable;
use App\Models\SupplierCreditNote;
use App\Models\SupplierReturn;
use App\Services\Purchasing\SupplierReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierReturnsIndex extends Component
{
    use WithPagination;
    public bool $showDraft = false;
    public string $receivingSearch = '';
    public ?int $receivingId = null;
    public array $quantities = [];
    public string $reason = '';
    public string $resolution = 'credit';
    public string $draftKey = '';
    public ?int $completionId = null;
    public string $refundMethod = 'bank-transfer';
    public string $reference = '';
    public ?int $creditId = null;
    public ?int $payableId = null;
    public string $creditAmount = '';
    public string $creditKey = '';

    public function create(): void
    {
        $this->permit('supplier-returns-create');
        $this->reset(['receivingId', 'quantities', 'reason', 'resolution']);
        $this->draftKey = (string) Str::uuid(); $this->showDraft = true; $this->resetValidation();
    }

    public function updatedReceivingId(): void
    {
        $this->permit('supplier-returns-create'); $this->quantities = [];
        if ($this->receivingId) {
            $receipt = $this->receivings()->with('items')->findOrFail($this->receivingId);
            $this->quantities = $receipt->items->mapWithKeys(fn ($item) => [$item->id => '0'])->all();
        }
    }

    public function saveDraft(SupplierReturnService $service): void
    {
        $this->permit('supplier-returns-create');
        $data = $this->validate(['receivingId' => ['required', 'integer'], 'quantities' => ['required', 'array'], 'quantities.*' => ['required', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999999.999'], 'reason' => ['required', 'string', 'max:2000'], 'resolution' => ['required', 'in:credit,refund'], 'draftKey' => ['required', 'string', 'max:96']]);
        $lines = collect($data['quantities'])->filter(fn ($qty) => (float) $qty > 0)->map(fn ($qty, $id) => ['receiving_item_id' => (int) $id, 'quantity' => (string) $qty])->values()->all();
        $service->draft($data['receivingId'], $lines, $data['reason'], $data['resolution'], $data['draftKey']);
        $this->showDraft = false; $this->dispatch('toast', type: 'success', message: __('Draft retur dibuat untuk diperiksa.'));
    }

    public function approve(int $id, SupplierReturnService $service): void { $service->approve($id); $this->dispatch('toast', type: 'success', message: __('Retur disetujui.')); }
    public function cancel(int $id, SupplierReturnService $service): void { $service->cancel($id); }

    public function beginCompletion(int $id): void
    {
        $this->permit('supplier-returns-approve'); $this->returns()->where('status', 'approved')->findOrFail($id);
        $this->completionId = $id; $this->refundMethod = 'bank-transfer'; $this->reference = ''; $this->resetValidation();
    }

    public function complete(SupplierReturnService $service): void
    {
        $data = $this->validate(['completionId' => ['required', 'integer'], 'refundMethod' => ['required', 'in:cash,bank-transfer'], 'reference' => ['nullable', 'string', 'max:160']]);
        $service->complete($data['completionId'], $data['refundMethod'], $data['reference'] ?: null);
        $this->completionId = null; $this->dispatch('toast', type: 'success', message: __('Retur selesai; stok dan penyelesaian finansial tercatat.'));
    }

    public function beginCredit(int $id): void
    {
        $this->permit('payables-pay'); $note = $this->credits()->where('available_amount', '>', 0)->findOrFail($id);
        $this->creditId = $id; $this->payableId = null; $this->creditAmount = (string) $note->available_amount; $this->creditKey = (string) Str::uuid(); $this->resetValidation();
    }

    public function applyCredit(SupplierReturnService $service): void
    {
        $this->permit('payables-pay');
        $data = $this->validate(['creditId' => ['required', 'integer'], 'payableId' => ['required', 'integer'], 'creditAmount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'creditKey' => ['required', 'string', 'max:96']]);
        $service->applyCredit($data['creditId'], $data['payableId'], $data['creditAmount'], $data['creditKey']);
        $this->creditId = null; $this->dispatch('toast', type: 'success', message: __('Kredit pemasok diterapkan ke hutang.'));
    }

    public function closeForms(): void { $this->showDraft = false; $this->completionId = $this->creditId = null; $this->resetValidation(); }

    public function render()
    {
        $this->permit('supplier-returns-access');
        $selected = $this->receivingId ? $this->receivings()->with('items.product:id,title,sku')->findOrFail($this->receivingId) : null;
        $credit = $this->creditId ? $this->credits()->findOrFail($this->creditId) : null;
        return view('livewire.purchasing.supplier-returns-index', [
            'returns' => $this->returns()->with(['supplier:id,name', 'warehouse:id,name', 'receiving:id,number', 'items.product:id,title,sku'])->latest()->paginate(20),
            'receivings' => $this->receivings()->with('supplier:id,name')->when($this->receivingSearch !== '', fn ($q) => $q->where('number', 'like', '%'.$this->receivingSearch.'%'))->latest()->limit(50)->get(),
            'selected' => $selected,
            'completion' => $this->completionId ? $this->returns()->findOrFail($this->completionId) : null,
            'credits' => $this->credits()->with(['supplier:id,name', 'supplierReturn:id,number'])->where('available_amount', '>', 0)->get(),
            'credit' => $credit,
            'payables' => $credit ? Payable::query()->where('supplier_id', $credit->supplier_id)->whereIn('status', ['unpaid', 'partial'])->whereHas('receiving.warehouse', fn ($q) => $this->warehouseScope($q))->get() : collect(),
        ])->layout('layouts.dashboard', ['title' => __('Retur pemasok')]);
    }

    private function receivings(): Builder { return GoodsReceiving::query()->where('status', 'completed')->whereHas('warehouse', fn ($q) => $this->warehouseScope($q)); }
    private function returns(): Builder { return SupplierReturn::query()->whereHas('warehouse', fn ($q) => $this->warehouseScope($q)); }
    private function credits(): Builder { return SupplierCreditNote::query()->whereHas('supplierReturn.warehouse', fn ($q) => $this->warehouseScope($q)); }
    private function warehouseScope(Builder $query): void
    {
        $query->where('is_active', true)->where(function ($scope) {
            $scope->where('outlet_id', (int) session('active_outlet_id'));
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) $scope->orWhere(fn ($central) => $central->whereNull('outlet_id')->where('type', 'central'));
        });
    }
    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
