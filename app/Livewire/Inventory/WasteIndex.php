<?php

namespace App\Livewire\Inventory;

use App\Exports\MasterDataExcelExport;
use App\Models\OutletWasteRecord;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Warehouse;
use App\Services\Finance\OperatingCostService;
use App\Support\OutletAccess;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class WasteIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $from = '';

    public string $to = '';

    public string $search = '';

    public bool $showForm = false;

    public array $form = [];

    public function mount(): void
    {
        $this->from = today()->startOfMonth()->toDateString();
        $this->to = today()->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->permit('waste-create');
        $this->form = ['warehouse_id' => '', 'product_id' => '', 'product_batch_id' => null, 'quantity' => '', 'reason' => 'damaged', 'notes' => '', 'idempotency_key' => (string) Str::uuid()];
        $this->search = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function updatedForm($value, $key): void
    {
        if ($key === 'warehouse_id') {
            $this->form['product_id'] = '';
        }
        if (in_array($key, ['warehouse_id', 'product_id'], true)) {
            $this->form['product_batch_id'] = null;
        }
    }

    public function save(OperatingCostService $service): void
    {
        $this->permit('waste-create');
        $this->resetValidation();
        try {
            $service->waste($this->form);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('form.'.$key, $messages[0]);
            }

            return;
        }
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: __('Waste dan pengurangan stok dicatat.'));
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function export()
    {
        $this->permit('waste-access');
        $this->permit('reports-export');
        $rows = $this->query()->with(['product:id,title,sku', 'warehouse:id,name', 'batch:id,batch_number', 'user:id,name'])->orderBy('created_at')->get();

        $data = $rows->map(fn ($row) => [$row->created_at->format('Y-m-d H:i:s'), $row->warehouse?->name, $row->product?->title, $row->product?->sku, $row->batch?->batch_number, $row->quantity, $row->unit_cost, $row->cost_total, $row->reason, $row->notes, $row->user?->name])->all();

        return Excel::download(new MasterDataExcelExport(['Tanggal', 'Gudang', 'Produk', 'SKU', 'Batch', 'Jumlah', 'HPP satuan', 'Nilai waste', 'Alasan', 'Catatan', 'Petugas'], $data, 'Waste'), 'waste-'.today()->toDateString().'.xlsx');
    }

    public function render()
    {
        $this->permit('waste-access');
        $warehouses = Warehouse::where('outlet_id', $this->outletId())->where('is_active', true)->orderBy('name')->get();
        $warehouseId = (int) ($this->form['warehouse_id'] ?? 0);
        if ($warehouseId && ! $warehouses->contains('id', $warehouseId)) {
            abort(404);
        }
        $products = Product::withTrashed()->where('track_stock', true)->whereHas('warehouseStocks', fn ($q) => $q->where('warehouse_id', $warehouseId)->where('stock', '>', 0))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($s) => $s->where('title', 'like', '%'.$this->search.'%')->orWhere('sku', 'like', '%'.$this->search.'%')))
            ->orderBy('title')->limit(50)->get();
        $productId = (int) ($this->form['product_id'] ?? 0);
        $selected = $productId ? Product::withTrashed()->whereHas('warehouseStocks', fn ($q) => $q->where('warehouse_id', $warehouseId))->findOrFail($productId) : null;
        if ($selected && ! $products->contains('id', $selected->id)) {
            $products->push($selected);
        }
        $query = $this->query();

        return view('livewire.inventory.waste-index', [
            'rows' => (clone $query)->with(['product:id,title,sku', 'warehouse:id,name', 'batch:id,batch_number', 'user:id,name'])->latest()->paginate(20),
            'total' => (clone $query)->sum('cost_total'), 'warehouses' => $warehouses, 'products' => $products, 'selected' => $selected,
            'batches' => $selected?->track_batches ? ProductBatch::where('product_id', $selected->id)->where('warehouse_id', $warehouseId)->whereIn('status', ['active', 'expired'])->where('quantity_remaining', '>', 0)->orderBy('expires_at')->get() : collect(),
        ])->layout('layouts.dashboard', ['title' => __('Waste outlet')]);
    }

    private function query()
    {
        $this->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return OutletWasteRecord::where('outlet_id', $this->outletId())->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59']);
    }

    private function outletId(): int
    {
        return app(OutletAccess::class)->current(request())?->id ?? abort(404);
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
