<?php

namespace App\Livewire\Inventory;

use App\Models\ProductionOrder;
use App\Models\ProductionRequest;
use App\Models\ProductionShift;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Production\ProductionOrderService;
use App\Support\OutletAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProductionOrdersIndex extends Component
{
    public bool $showForm = false;

    public ?int $selectedOrderId = null;

    public array $materialLines = [];

    public array $outputLines = [];

    public ?int $shiftWarehouseId = null;

    public string $productionDate = '';

    public function mount(): void
    {
        $this->productionDate = today()->toDateString();
    }

    public function updatedProductionDate(): void
    {
        $this->validateOnly('productionDate', ['productionDate' => ['required', 'date_format:Y-m-d']]);
    }

    public function openShift(): void
    {
        abort_unless(auth()->user()->can('production-orders-create'), 403);
        $this->validate(['shiftWarehouseId' => ['required', 'integer', 'exists:warehouses,id']]);
        $user = User::query()->findOrFail(auth()->id());
        $outletIds = app(OutletAccess::class)->outletsFor($user)->select('outlets.id');
        DB::transaction(function () use ($user, $outletIds): void {
            $warehouse = Warehouse::query()->whereKey($this->shiftWarehouseId)->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('outlet_id')->orWhereIn('outlet_id', $outletIds))->lockForUpdate()->firstOrFail();
            abort_unless($warehouse->type === 'central' && $warehouse->outlet_id === null, 403, __('Shift produksi hanya dapat dibuka di Gudang Bersama.'));
            if (ProductionShift::query()->where('user_id', $user->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['shiftWarehouseId' => __('Tutup shift produksi yang sedang berjalan terlebih dahulu.')]);
            }
            $outletId = $warehouse->outlet_id ?? session('active_outlet_id');
            if (! $outletId) {
                $outletId = app(OutletAccess::class)->outletsFor($user)->value('outlets.id');
            }
            app(OutletAccess::class)->require($user, (int) $outletId);
            ProductionShift::query()->create([
                'outlet_id' => $outletId, 'warehouse_id' => $warehouse->id, 'user_id' => $user->id,
                'status' => 'open', 'opened_at' => now(),
            ]);
        });
        $this->dispatch('toast', type: 'success', message: __('Shift produksi dibuka. Catatan produksi hari ini akan masuk ke shift ini.'));
    }

    public function closeShift(int $id): void
    {
        abort_unless(auth()->user()->can('production-orders-create'), 403);
        $user = User::query()->findOrFail(auth()->id());
        $outletIds = app(OutletAccess::class)->outletsFor($user)->select('outlets.id');
        DB::transaction(function () use ($user, $outletIds, $id): void {
            $shift = ProductionShift::query()->where('user_id', $user->id)->whereIn('outlet_id', $outletIds)->where('status', 'open')->lockForUpdate()->findOrFail($id);
            if ($shift->orders()->where('status', 'in_production')->exists()) {
                throw ValidationException::withMessages(['shift' => __('Selesaikan semua order produksi yang sedang berjalan sebelum menutup shift.')]);
            }
            $shift->update(['status' => 'closed', 'closed_at' => now()]);
        });
        $this->dispatch('toast', type: 'success', message: __('Shift produksi ditutup.'));
    }

    public function start(int $requestId, ProductionOrderService $service): void
    {
        abort_unless(auth()->user()->can('production-orders-create'), 403);
        $this->visibleRequest($requestId);
        $request = ProductionRequest::query()->findOrFail($requestId);
        $shift = ProductionShift::query()->where('user_id', auth()->id())->where('warehouse_id', $request->warehouse_id)->where('outlet_id', $request->outlet_id)->where('status', 'open')->first();
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => __('Buka shift produksi untuk gudang permintaan ini terlebih dahulu.')]);
        }
        $order = $service->startFromRequest(User::query()->findOrFail(auth()->id()), $requestId, $shift->id);
        if (! $order->production_shift_id) {
            $order->update(['production_shift_id' => $shift->id]);
        }
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
            'actual_output' => (string) $item->planned_quantity, 'variance_note' => '',
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
            'outputLines.*.actual_output' => ['required', 'numeric', 'min:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'outputLines.*.variance_note' => ['nullable', 'string', 'max:2000'],
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
        abort_if(auth()->user()->hasRole('finance') && ! auth()->user()->hasRole('super-admin'), 403);
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');

        return view('livewire.inventory.production-orders-index', [
            'warehouses' => Warehouse::query()->where('is_active', true)->where('type', 'central')->whereNull('outlet_id')->orderBy('name')->get(),
            'openShifts' => ProductionShift::query()->with(['warehouse', 'orders.items.materials.ingredient.unit'])->whereIn('outlet_id', $outletIds)->where('user_id', auth()->id())->where('status', 'open')->latest('opened_at')->get(),
            'todayShifts' => ProductionShift::query()->with(['warehouse', 'user:id,name', 'orders.items.product.unit', 'orders.items.materials.ingredient.unit'])->whereIn('outlet_id', $outletIds)->whereHas('warehouse', fn ($warehouse) => $warehouse->where('type', 'central')->whereNull('outlet_id'))->whereDate('opened_at', $this->productionDate ?: today()->toDateString())->latest('opened_at')->get(),
            'requests' => ProductionRequest::query()->with(['items.product'])->whereIn('outlet_id', $outletIds)->whereHas('warehouse', fn ($warehouse) => $warehouse->where('type', 'central')->whereNull('outlet_id'))->whereIn('status', ['approved', 'received'])->whereDoesntHave('productionOrder')->latest()->get(),
            'orders' => ProductionOrder::query()->with(['items.product.unit', 'items.materials.ingredient.unit'])->whereIn('outlet_id', $outletIds)->whereHas('warehouse', fn ($warehouse) => $warehouse->where('type', 'central')->whereNull('outlet_id'))->latest()->limit(100)->get(),
        ])->layout('layouts.dashboard', ['title' => __('Produksi')]);
    }
}
