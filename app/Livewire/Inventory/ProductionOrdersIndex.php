<?php

namespace App\Livewire\Inventory;

use App\Models\ProductionOrder;
use App\Models\ProductionRequest;
use App\Models\User;
use App\Services\Production\ProductionOrderService;
use App\Support\OutletAccess;
use Livewire\Component;

class ProductionOrdersIndex extends Component
{
    public bool $showForm = false;

    public ?int $selectedOrderId = null;

    public array $materialLines = [];

    public array $outputLines = [];

    public function start(int $requestId, ProductionOrderService $service): void
    {
        abort_unless(auth()->user()->can('production-orders-create'), 403);
        $this->visibleRequest($requestId);
        $order = $service->startFromRequest(User::query()->findOrFail(auth()->id()), $requestId);
        $this->dispatch('toast', type: 'success', message: __('Produksi :number dimulai.', ['number' => $order->number]));
    }

    public function openComplete(int $id): void
    {
        abort_unless(auth()->user()->can('production-orders-complete'), 403);
        $order = $this->visibleOrder($id)->load(['items.product.unit', 'items.materials.ingredient.unit']);
        $this->selectedOrderId = $order->id;
        $this->materialLines = $order->items->flatMap->materials->map(fn ($material) => [
            'production_order_material_id' => $material->id, 'name' => $material->ingredient->name,
            'unit' => $material->ingredient->unit?->symbol, 'planned_quantity' => (string) $material->planned_quantity,
            'actual_quantity' => (string) $material->planned_quantity,
        ])->values()->all();
        $this->outputLines = $order->items->map(fn ($item) => [
            'production_order_item_id' => $item->id, 'name' => $item->product->title,
            'unit' => $item->product->unit?->symbol, 'planned_quantity' => (string) $item->planned_quantity,
            'actual_output' => (string) $item->planned_quantity,
        ])->all();
        $this->showForm = true;
    }

    public function complete(ProductionOrderService $service): void
    {
        abort_unless(auth()->user()->can('production-orders-complete'), 403);
        $this->validate([
            'selectedOrderId' => ['required', 'integer', 'exists:production_orders,id'],
            'materialLines' => ['required', 'array', 'min:1'],
            'materialLines.*.production_order_material_id' => ['required', 'integer'],
            'materialLines.*.actual_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'outputLines' => ['required', 'array', 'min:1'],
            'outputLines.*.production_order_item_id' => ['required', 'integer'],
            'outputLines.*.actual_output' => ['required', 'numeric', 'gt:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);
        $order = $this->visibleOrder($this->selectedOrderId);
        $service->complete(User::query()->findOrFail(auth()->id()), $order->id, $this->materialLines, $this->outputLines);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: __('Konsumsi bahan dan output aktual sudah dicatat ke ledger.'));
    }

    private function visibleRequest(int $id): ProductionRequest
    {
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');

        return ProductionRequest::query()->whereIn('outlet_id', $outletIds)->findOrFail($id);
    }

    private function visibleOrder(int $id): ProductionOrder
    {
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');

        return ProductionOrder::query()->whereIn('outlet_id', $outletIds)->findOrFail($id);
    }

    public function render()
    {
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');

        return view('livewire.inventory.production-orders-index', [
            'requests' => ProductionRequest::query()->with(['items.product'])->whereIn('outlet_id', $outletIds)->whereIn('status', ['approved', 'received'])->whereDoesntHave('productionOrder')->latest()->get(),
            'orders' => ProductionOrder::query()->with(['items.product.unit', 'items.materials.ingredient.unit'])->whereIn('outlet_id', $outletIds)->latest()->limit(100)->get(),
        ])->layout('layouts.dashboard', ['title' => __('Produksi')]);
    }
}
