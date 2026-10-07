<?php

namespace App\Livewire\Inventory;

use App\Models\IngredientPurchaseOrder;
use App\Models\Product;
use App\Models\ProductionRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Production\ProductionRequestService;
use App\Support\OutletAccess;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductionRequestsIndex extends Component
{
    public bool $showForm = false;

    public bool $showApproval = false;

    public bool $showReceiving = false;

    public array $lines = [];

    public string $notes = '';

    public string $supplierId = '';

    public array $approvalMaterials = [];

    public array $unitCosts = [];

    public array $purchaseQuantities = [];

    public ?int $selectedRequestId = null;

    public ?int $selectedPurchaseId = null;

    public array $receiptLines = [];

    public string $invoiceReference = '';

    public string $idempotencyKey = '';

    public function create(): void
    {
        abort_unless(auth()->user()->can('production-requests-create'), 403);
        $this->lines = [['product_id' => '', 'quantity' => '']];
        $this->notes = '';
        $this->idempotencyKey = (string) Str::uuid();
        $this->showForm = true;
    }

    public function addLine(): void
    {
        abort_unless(auth()->user()->can('production-requests-create'), 403);
        $this->lines[] = ['product_id' => '', 'quantity' => ''];
    }

    public function removeLine(int $index): void
    {
        abort_unless(auth()->user()->can('production-requests-create'), 403);
        if (count($this->lines) > 1 && array_key_exists($index, $this->lines)) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function submitRequest(ProductionRequestService $service): void
    {
        abort_unless(auth()->user()->can('production-requests-create'), 403);
        $data = $this->validate([
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $actor = User::query()->findOrFail(auth()->id());
        $outletId = (int) session('active_outlet_id');
        $warehouse = Warehouse::query()->where('code', 'PUSAT')->where('type', 'central')->whereNull('outlet_id')->where('is_active', true)->firstOrFail();
        $request = $service->create($actor, $outletId, $warehouse->id, $data['lines'], $data['notes'] ?: null, $this->idempotencyKey);
        $this->showForm = false;
        $this->idempotencyKey = (string) Str::uuid();
        $message = $request->status === 'approved'
            ? __('Permintaan :number langsung disetujui karena bahan tersedia di gudang.', ['number' => $request->number])
            : __('Permintaan :number dikirim ke Finance karena ada bahan yang perlu dibeli.', ['number' => $request->number]);
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function openApproval(int $id): void
    {
        abort_unless(auth()->user()->can('production-requests-approve'), 403);
        $request = $this->visibleRequest($id)->load('materials.ingredient.unit');
        $this->selectedRequestId = $id;
        $this->supplierId = '';
        $this->approvalMaterials = $request->materials->map(fn ($line) => [
            'ingredient_id' => $line->ingredient_id, 'name' => $line->ingredient->name,
            'unit' => $line->ingredient->unit?->symbol, 'quantity' => (string) $line->purchase_quantity,
        ])->values()->all();
        $this->unitCosts = $request->materials->mapWithKeys(fn ($line) => [$line->ingredient_id => (string) $line->unit_cost])->all();
        $this->purchaseQuantities = $request->materials->mapWithKeys(fn ($line) => [$line->ingredient_id => (string) $line->purchase_quantity])->all();
        $this->showApproval = true;
    }

    public function approve(ProductionRequestService $service): void
    {
        abort_unless(auth()->user()->can('production-requests-approve'), 403);
        $this->validate([
            'selectedRequestId' => ['required', 'integer', 'exists:production_requests,id'],
            'supplierId' => [collect($this->purchaseQuantities)->sum(fn ($quantity) => (float) $quantity) <= 0 ? 'nullable' : 'required', 'integer', 'exists:suppliers,id'],
            'unitCosts' => ['required', 'array'], 'unitCosts.*' => ['required', 'numeric', 'min:0', 'max:9999999999.99999999', 'regex:/^\d+(?:\.\d{1,8})?$/'],
            'purchaseQuantities' => ['required', 'array'], 'purchaseQuantities.*' => ['required', 'numeric', 'min:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);
        $order = $service->approveAndPurchase(User::query()->findOrFail(auth()->id()), $this->selectedRequestId, $this->supplierId !== '' ? (int) $this->supplierId : null, null, $this->unitCosts, $this->purchaseQuantities);
        $this->showApproval = false;
        $message = $order ? __('Pembelian bahan :number dibuat.', ['number' => $order->number]) : __('Permintaan disetujui karena stok bahan sudah mencukupi.');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function getPurchaseTotalProperty(): float
    {
        if (! $this->selectedRequestId) {
            return 0;
        }

        $materials = ProductionRequest::query()->with('materials')->find($this->selectedRequestId)?->materials ?? collect();

        return $materials->sum(fn ($material) => (float) ($this->purchaseQuantities[$material->ingredient_id] ?? 0)
            * (float) ($this->unitCosts[$material->ingredient_id] ?? $material->unit_cost));
    }

    public function openReceiving(int $purchaseId): void
    {
        abort_unless(auth()->user()->can('goods-receivings-create'), 403);
        $order = IngredientPurchaseOrder::query()->with('items.ingredient.unit')->findOrFail($purchaseId);
        abort_unless($this->visibleRequest($order->production_request_id), 404);
        $this->selectedPurchaseId = $purchaseId;
        $this->idempotencyKey = (string) Str::uuid();
        $this->receiptLines = $order->items->map(fn ($item) => [
            'purchase_order_item_id' => $item->id, 'name' => $item->ingredient->name,
            'unit' => $item->ingredient->unit?->symbol, 'sent_quantity' => (string) $item->quantity,
            'accepted_quantity' => (string) $item->quantity, 'qc_status' => 'good', 'notes' => '',
        ])->all();
        $this->invoiceReference = (string) ($order->invoice_reference ?? '');
        $this->showReceiving = true;
    }

    public function receive(ProductionRequestService $service): void
    {
        abort_unless(auth()->user()->can('goods-receivings-create'), 403);
        $this->validate([
            'selectedPurchaseId' => ['required', 'integer', 'exists:ingredient_purchase_orders,id'],
            'receiptLines' => ['required', 'array', 'min:1'],
            'receiptLines.*.purchase_order_item_id' => ['required', 'integer'],
            'receiptLines.*.sent_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'receiptLines.*.accepted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'receiptLines.*.qc_status' => ['required', 'in:good,damaged,short,wrong_item,unusable'],
            'receiptLines.*.notes' => ['nullable', 'string', 'max:1000'],
            'invoiceReference' => ['nullable', 'string', 'max:100'],
        ]);
        $service->receive(User::query()->findOrFail(auth()->id()), $this->selectedPurchaseId, $this->receiptLines, $this->invoiceReference ?: null, null, $this->idempotencyKey);
        $this->showReceiving = false;
        $this->idempotencyKey = (string) Str::uuid();
        $this->dispatch('toast', type: 'success', message: __('Penerimaan tersimpan. Stok bertambah sesuai jumlah yang lolos QC.'));
    }

    private function visibleRequest(int $id): ProductionRequest
    {
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');

        return ProductionRequest::query()->whereIn('outlet_id', $outletIds)->findOrFail($id);
    }

    public function render()
    {
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->select('outlets.id');

        return view('livewire.inventory.production-requests-index', [
            'requests' => ProductionRequest::query()->with(['items.product', 'materials.ingredient.unit', 'items.recipe'])->whereIn('outlet_id', $outletIds)->whereHas('warehouse', fn ($warehouse) => $warehouse->where('type', 'central')->whereNull('outlet_id'))->latest()->limit(100)->get(),
            'purchases' => IngredientPurchaseOrder::query()->with(['request', 'items.ingredient.unit'])->whereHas('request', fn ($q) => $q->whereIn('outlet_id', $outletIds)->whereHas('warehouse', fn ($warehouse) => $warehouse->where('type', 'central')->whereNull('outlet_id')))->whereIn('status', ['ordered', 'partially_received'])->latest()->get(),
            'products' => Product::query()->where('is_active', true)->where('is_semi_finished', true)->whereHas('recipeVersions', fn ($q) => $q->whereColumn('recipe_versions.unit_id', 'products.unit_id')->where('is_active', true))->orderBy('title')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.dashboard', ['title' => __('Permintaan produksi')]);
    }
}
