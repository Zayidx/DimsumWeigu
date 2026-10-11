<?php

namespace App\Livewire\Reports;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionShift;
use Livewire\Component;
use Livewire\WithPagination;

class WarehouseProductionReport extends Component
{
    use WithPagination;

    public string $fromDate = '';

    public string $toDate = '';

    public string $productId = '';

    public string $shiftId = '';

    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['fromDate', 'toDate', 'productId', 'shiftId', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('production-orders-access')
            && auth()->user()?->hasAnyRole(['finance', 'super-admin']), 403);
        $this->validate([
            'fromDate' => ['nullable', 'date_format:Y-m-d'], 'toDate' => ['nullable', 'date_format:Y-m-d'],
            'productId' => ['nullable', 'integer', 'exists:products,id'],
            'shiftId' => ['nullable', 'integer', 'exists:production_shifts,id'],
            'status' => ['nullable', 'in:in_production,completed'],
        ]);

        $orders = ProductionOrder::query()->with(['shift.user:id,name', 'items.product.unit', 'items.materials.ingredient.unit'])
            ->whereHas('warehouse', fn ($query) => $query->where('type', 'central')->whereNull('outlet_id'))
            ->when($this->fromDate !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->toDate))
            ->when($this->productId !== '', fn ($query) => $query->whereHas('items', fn ($items) => $items->where('product_id', $this->productId)))
            ->when($this->shiftId !== '', fn ($query) => $query->where('production_shift_id', $this->shiftId))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest()->paginate(20);

        return view('livewire.reports.warehouse-production-report', [
            'orders' => $orders,
            'products' => Product::query()->where('is_semi_finished', true)->orderBy('title')->get(['id', 'title']),
            'shifts' => ProductionShift::query()->with('user:id,name')->latest()->limit(100)->get(),
        ])->layout('layouts.dashboard', ['title' => __('Laporan Produksi Warehouse')]);
    }
}
