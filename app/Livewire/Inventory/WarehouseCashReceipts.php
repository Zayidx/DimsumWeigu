<?php

namespace App\Livewire\Inventory;

use App\Models\CashHandover;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Services\Cashier\CashHandoverService;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Livewire\Component;

class WarehouseCashReceipts extends Component
{
    public array $actualAmounts = [];

    public array $verificationNotes = [];

    public function confirmPhysical(int $id, CashHandoverService $service): void
    {
        abort_unless(auth()->user()?->can('cash-handovers-confirm') && auth()->user()?->hasAnyRole(['warehouse', 'super-admin']), 403);
        $this->validate([
            'actualAmounts.'.$id => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'verificationNotes.'.$id => ['nullable', 'string', 'max:2000'],
        ]);
        $service->confirmPhysical($id, (string) $this->actualAmounts[$id], $this->verificationNotes[$id] ?? null);
        unset($this->actualAmounts[$id], $this->verificationNotes[$id]);
        $this->dispatch('toast', type: 'success', message: __('Uang fisik diterima dan saldo kas kedua lokasi diperbarui.'));
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('cash-handovers-confirm') && auth()->user()?->hasAnyRole(['warehouse', 'super-admin']), 403);
        $central = Warehouse::query()->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->firstOrFail();
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');
        $handovers = CashHandover::query()->with(['source.outlet:id,name', 'requester:id,name', 'receiver:id,name'])
            ->where('destination_warehouse_id', $central->id)
            ->whereHas('source', fn ($source) => $source->whereIn('outlet_id', $outletIds))
            ->whereIn('status', ['pending', 'confirmed'])->latest()->limit(100)->get();
        foreach ($handovers->where('status', 'pending') as $handover) {
            $this->actualAmounts[$handover->id] ??= (string) $handover->amount;
        }
        $centralBalance = WarehouseCashLedger::query()->where('warehouse_id', $central->id)->latest('id')->value('balance_after') ?? '0.00';
        $pendingCents = $handovers->where('status', 'pending')->sum(fn ($handover) => FixedDecimal::money((string) $handover->amount));

        return view('livewire.inventory.warehouse-cash-receipts', compact('handovers', 'centralBalance', 'pendingCents'))
            ->layout('layouts.dashboard', ['title' => __('Penerimaan kas outlet')]);
    }
}
