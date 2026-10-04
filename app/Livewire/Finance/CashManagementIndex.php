<?php

namespace App\Livewire\Finance;

use App\Models\CashHandover;
use App\Models\CashierShift;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Services\Cashier\CashHandoverService;
use App\Services\Cashier\CashierShiftService;
use App\Support\CsvCell;
use App\Support\OutletAccess;
use App\Support\PasswordStepUp;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CashManagementIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public bool $showForm = false;

    public ?int $sourceId = null;

    public ?int $destinationId = null;

    public string $amount = '';

    public string $notes = '';

    public string $requestKey = '';

    public string $movementType = 'in';

    public string $movementAmount = '';

    public string $movementNote = '';

    #[Locked]
    public string $movementKey = '';

    public string $ledgerWarehouse = '';

    public function mount(): void
    {
        $this->movementKey = (string) Str::uuid();
    }

    public function create(): void
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();
        $this->reset(['sourceId', 'destinationId', 'amount', 'notes']);
        $this->requestKey = (string) Str::uuid();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function requestHandover(CashHandoverService $service): void
    {
        $this->permit('cash-management-create');
        $this->validate(['sourceId' => ['required', 'integer'], 'destinationId' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'notes' => ['required', 'string', 'min:3', 'max:2000'], 'requestKey' => ['required', 'string', 'max:96']]);
        $service->request($this->sourceId, $this->destinationId, $this->amount, $this->notes, $this->requestKey);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: __('Permintaan serah-terima dibuat. Kas belum berpindah.'));
    }

    public function approve(int $id, CashHandoverService $service): void
    {
        $service->approve($id);
        $this->dispatch('toast', type: 'success', message: __('Kas dikirim; menunggu konfirmasi penerimaan.'));
    }

    public function confirm(int $id, CashHandoverService $service): void
    {
        $service->confirm($id);
        $this->dispatch('toast', type: 'success', message: __('Penerimaan kas dicatat.'));
    }

    public function cancelHandover(int $id, CashHandoverService $service): void
    {
        $service->cancel($id);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function recordMovement(CashierShiftService $service): void
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();
        $data = $this->validate(['movementType' => ['required', 'in:in,out'], 'movementAmount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'], 'movementNote' => ['required', 'string', 'min:3', 'max:2000']]);
        $shift = CashierShift::where('open_user_id', auth()->id())->where('outlet_id', $this->outletId())->where('status', 'open')->firstOrFail();
        $service->recordMovement($shift, $data['movementType'], $data['movementAmount'], $data['movementNote'], $this->movementKey);
        $this->movementKey = (string) Str::uuid();
        $this->reset(['movementAmount', 'movementNote']);
        $this->dispatch('toast', type: 'success', message: __('Pergerakan kas dicatat.'));
    }

    public function updatedLedgerWarehouse(): void
    {
        $this->resetPage('ledgerPage');
    }

    public function export()
    {
        $this->permit('cash-management-access');
        $this->permit('reports-export');
        PasswordStepUp::requireRecent();
        $rows = $this->ledgerQuery()->with('warehouse:id,name')->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal', 'Gudang', 'Shift', 'Tipe', 'Jumlah', 'Saldo sebelum', 'Saldo sesudah', 'Referensi', 'Catatan']);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(CsvCell::safe(...), [$row->created_at->format('Y-m-d H:i:s'), $row->warehouse?->name, $row->cashier_shift_id, $row->type, $row->amount, $row->balance_before, $row->balance_after, $row->reference_type.':'.$row->reference_id, $row->note]));
            }
            fclose($handle);
        }, 'cash-ledger-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->permit('cash-management-access');
        $currentIds = $this->currentWarehouses()->pluck('id');
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->pluck('id');
        $destinations = Warehouse::where('is_active', true)->where(function ($q) use ($outletIds) {
            $q->whereIn('outlet_id', $outletIds);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) {
                $q->orWhere(fn ($c) => $c->whereNull('outlet_id')->where('type', 'central'));
            }
        })->with('outlet:id,name')->orderBy('name')->get();

        return view('livewire.finance.cash-management-index', [
            'handovers' => CashHandover::where(fn ($q) => $q->whereIn('source_warehouse_id', $currentIds)->orWhereIn('destination_warehouse_id', $currentIds))->with(['source:id,name', 'destination:id,name', 'requester:id,name', 'approver:id,name', 'receiver:id,name'])->latest()->paginate(20, ['*'], 'handoverPage'),
            'ledger' => $this->ledgerQuery()->with(['warehouse:id,name', 'user:id,name'])->latest('id')->paginate(25, ['*'], 'ledgerPage'),
            'sources' => $this->currentWarehouses()->where('is_active', true)->orderBy('name')->get(), 'destinations' => $destinations,
            'warehouses' => $this->currentWarehouses()->orderBy('name')->get(), 'currentIds' => $currentIds,
            'shift' => CashierShift::where('open_user_id', auth()->id())->where('outlet_id', $this->outletId())->where('status', 'open')->first(),
        ])->layout('layouts.dashboard', ['title' => __('Manajemen kas')]);
    }

    private function ledgerQuery()
    {
        $ids = $this->currentWarehouses()->pluck('id');
        if ($this->ledgerWarehouse !== '' && ! $ids->contains((int) $this->ledgerWarehouse)) {
            abort(404);
        }

        return WarehouseCashLedger::whereIn('warehouse_id', $ids)->when($this->ledgerWarehouse !== '', fn ($q) => $q->where('warehouse_id', (int) $this->ledgerWarehouse));
    }

    private function currentWarehouses()
    {
        $outletId = $this->outletId();

        return Warehouse::where(function ($q) use ($outletId) {
            $q->where('outlet_id', $outletId);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) {
                $q->orWhere(fn ($c) => $c->whereNull('outlet_id')->where('type', 'central'));
            }
        });
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
