<?php

namespace App\Livewire\Finance;

use App\Models\FinanceCashLedger;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Models\WarehouseFinanceCashHandover;
use App\Services\Finance\WarehouseFinanceHandoverService;
use App\Support\FixedDecimal;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WarehouseFinanceHandoversIndex extends Component
{
    public string $amount = '';

    public string $notes = '';

    #[Locked]
    public string $requestKey = '';

    public array $actualAmounts = [];

    public array $verificationNotes = [];

    public function mount(): void
    {
        $this->requestKey = (string) Str::uuid();
    }

    public function requestHandover(WarehouseFinanceHandoverService $service): void
    {
        abort_unless(auth()->user()?->can('warehouse-finance-handovers-create'), 403);
        $this->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $service->request($this->amount, $this->notes, $this->requestKey);
        $this->amount = '';
        $this->notes = '';
        $this->requestKey = (string) Str::uuid();
        $this->dispatch('toast', type: 'success', message: __('Pengajuan kas ke Finance dibuat. Dana tercatat sedang dalam proses.'));
    }

    public function confirm(int $id, WarehouseFinanceHandoverService $service): void
    {
        abort_unless(auth()->user()?->can('warehouse-finance-handovers-confirm'), 403);
        $this->validate([
            'actualAmounts.'.$id => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'verificationNotes.'.$id => ['nullable', 'string', 'max:2000'],
        ]);
        $service->confirm($id, (string) $this->actualAmounts[$id], $this->verificationNotes[$id] ?? null);
        unset($this->actualAmounts[$id], $this->verificationNotes[$id]);
        $this->dispatch('toast', type: 'success', message: __('Finance menerima uang fisik dan ledger kas diperbarui.'));
    }

    public function cancel(int $id, WarehouseFinanceHandoverService $service): void
    {
        abort_unless(auth()->user()?->can('warehouse-finance-handovers-create'), 403);
        $service->cancel($id);
        $this->dispatch('toast', type: 'success', message: __('Pengajuan dibatalkan. Dana kembali tersedia di kas Warehouse.'));
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('warehouse-finance-handovers-access')
            && auth()->user()?->hasAnyRole(['warehouse', 'finance', 'super-admin']), 403);
        $warehouse = Warehouse::query()->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->firstOrFail();
        $balance = WarehouseCashLedger::query()->where('warehouse_id', $warehouse->id)->latest('id')->value('balance_after') ?? '0.00';
        $handovers = WarehouseFinanceCashHandover::query()->with(['requester:id,name', 'confirmer:id,name'])
            ->where('warehouse_id', $warehouse->id)->latest()->limit(100)->get();
        foreach ($handovers->where('status', 'pending') as $handover) {
            $this->actualAmounts[$handover->id] ??= (string) $handover->requested_amount;
        }
        $pendingCents = WarehouseFinanceCashHandover::query()->where('warehouse_id', $warehouse->id)->where('status', 'pending')
            ->get(['requested_amount'])->sum(fn ($row) => FixedDecimal::money((string) $row->requested_amount));
        $balanceCents = FixedDecimal::money((string) $balance);
        $availableCents = max(0, $balanceCents - $pendingCents);
        $financeBalance = FinanceCashLedger::query()->latest('id')->value('balance_after') ?? '0.00';

        return view('livewire.finance.warehouse-finance-handovers-index', compact('handovers', 'balanceCents', 'pendingCents', 'availableCents', 'financeBalance'))
            ->layout('layouts.dashboard', ['title' => __('Kas Warehouse ke Finance')]);
    }
}
