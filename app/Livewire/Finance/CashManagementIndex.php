<?php

namespace App\Livewire\Finance;

use App\Exports\MasterDataExcelExport;
use App\Models\BankAccount;
use App\Models\BankAccountLedger;
use App\Models\CashHandover;
use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Services\Cashier\CashHandoverService;
use App\Services\Cashier\CashierShiftService;
use App\Services\Finance\BankAccountLedgerService;
use App\Services\Finance\CashDepositService;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use App\Support\PasswordStepUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

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

    public string $depositAccountId = '';

    public string $depositAmount = '';

    public string $depositNote = '';

    #[Locked]
    public string $depositKey = '';

    public function mount(): void
    {
        $this->movementKey = (string) Str::uuid();
        $this->depositKey = (string) Str::uuid();
    }

    public function depositCash(CashDepositService $service): void
    {
        abort_unless(auth()->user()?->canAny(['cash-management-create', 'bank-accounts-access']), 403);
        PasswordStepUp::requireRecent();
        $data = $this->validate([
            'depositAccountId' => ['required', 'integer', 'exists:bank_accounts,id'],
            'depositAmount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'depositNote' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $source = $this->currentWarehouses()->where('code', 'PUSAT')->whereNull('outlet_id')->where('type', 'central')->where('is_active', true)->firstOrFail();
        $account = BankAccount::query()->whereKey($data['depositAccountId'])->where('is_active', true)->firstOrFail();
        $service->deposit($source, $account, $data['depositAmount'], $data['depositNote'], $this->depositKey);
        $this->depositKey = (string) Str::uuid();
        $this->reset(['depositAmount', 'depositNote']);
        $this->dispatch('toast', type: 'success', message: __('Setoran kas ke rekening berhasil dicatat.'));
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

        $data = $rows->map(fn ($row) => [$row->created_at->format('Y-m-d H:i:s'), $row->warehouse?->name, $row->cashier_shift_id, $row->type, $row->amount, $row->balance_before, $row->balance_after, $row->reference_type.':'.$row->reference_id, $row->note])->all();

        return Excel::download(new MasterDataExcelExport(['Tanggal', 'Gudang', 'Shift', 'Tipe', 'Jumlah', 'Saldo sebelum', 'Saldo sesudah', 'Referensi', 'Catatan'], $data, 'Ledger Kas'), 'cash-ledger-'.today()->toDateString().'.xlsx');
    }

    public function render()
    {
        $this->permit('cash-management-access');
        $currentIds = $this->currentWarehouses()->pluck('id');
        $cashierOnly = auth()->user()->hasRole('cashier') && ! auth()->user()->hasRole('super-admin');
        $shift = CashierShift::where('open_user_id', auth()->id())->where('outlet_id', $this->outletId())->where('status', 'open')->first();
        $shiftCashPositionCents = $shift ? app(CashierShiftService::class)->cashPositionCents($shift) : null;
        $outletIds = app(OutletAccess::class)->outletsFor(auth()->user())->pluck('id');
        $destinationOutletIds = ! $cashierOnly && auth()->user()->hasAnyRole(['super-admin', 'warehouse', 'finance'])
            ? $outletIds
            : collect([$this->outletId()]);
        $destinations = Warehouse::where('is_active', true)->where(function ($q) use ($destinationOutletIds) {
            $q->whereIn('outlet_id', $destinationOutletIds);
            if (auth()->user()->hasRole('super-admin') || auth()->user()->canAny(['warehouses-access', 'cash-management-create'])) {
                $q->orWhere(fn ($c) => $c->whereNull('outlet_id')->where('type', 'central'));
            }
        })->with('outlet:id,name')->orderBy('name')->get();
        $bankAccounts = BankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('bank_name')->get();
        $centralWarehouse = $this->currentWarehouses()->where('code', 'PUSAT')->whereNull('outlet_id')->where('type', 'central')->where('is_active', true)->first();
        $canViewAllOutletCash = ! $cashierOnly && auth()->user()->hasAnyRole(['super-admin', 'warehouse', 'finance']);
        $outletCashSummary = $this->outletCashSummary($canViewAllOutletCash, $centralWarehouse);
        $centralLatestBalance = $centralWarehouse
            ? WarehouseCashLedger::query()->where('warehouse_id', $centralWarehouse->id)->latest('id')->value('balance_after')
            : null;
        $centralCashBalanceCents = $centralLatestBalance !== null ? FixedDecimal::money((string) $centralLatestBalance) : 0;

        return view('livewire.finance.cash-management-index', [
            'handovers' => CashHandover::where(fn ($q) => $q->whereIn('source_warehouse_id', $currentIds)->orWhereIn('destination_warehouse_id', $currentIds))
                ->when($cashierOnly, fn ($q) => $q->where(fn ($mine) => $mine->where('requested_by', auth()->id())
                    ->orWhere('confirmed_by', auth()->id())
                    ->orWhere(fn ($incoming) => $incoming->where('destination_warehouse_id', $shift?->warehouse_id ?? 0)->where('status', 'approved'))))
                ->with(['source:id,name', 'destination:id,name', 'requester:id,name', 'approver:id,name', 'receiver:id,name'])->latest()->paginate(20, ['*'], 'handoverPage'),
            'ledger' => $this->ledgerQuery()->with(['warehouse:id,name', 'user:id,name'])->latest('id')->paginate(25, ['*'], 'ledgerPage'),
            'sources' => $this->currentWarehouses()->where('is_active', true)->when($cashierOnly, fn ($q) => $q->whereKey($shift?->warehouse_id ?? 0))->orderBy('name')->get(), 'destinations' => $destinations,
            'warehouses' => $this->currentWarehouses()->orderBy('name')->get(), 'currentIds' => $currentIds,
            'shift' => $shift,
            'shiftCashPositionCents' => $shiftCashPositionCents,
            'bankAccounts' => $bankAccounts,
            'bankLedger' => auth()->user()->can('bank-accounts-access')
                ? BankAccountLedger::query()->with(['account:id,bank_name,account_name,account_number', 'user:id,name'])->latest('id')->paginate(20, ['*'], 'bankPage')
                : BankAccountLedger::query()->whereRaw('1 = 0')->paginate(20, ['*'], 'bankPage'),
            'bankBalances' => auth()->user()->can('bank-accounts-access')
                ? $bankAccounts->mapWithKeys(fn (BankAccount $account) => [$account->id => app(BankAccountLedgerService::class)->balanceCents($account)])
                : collect(),
            'centralWarehouse' => auth()->user()->can('bank-accounts-access') ? $centralWarehouse : null,
            'outletCashSummary' => $outletCashSummary,
            'outletCashBalanceCents' => $outletCashSummary->sum('outlet_balance_cents'),
            'collectedOutletCashCents' => $outletCashSummary->sum('collected_cents'),
            'centralCashBalanceCents' => $centralCashBalanceCents,
            'canViewAllOutletCash' => $canViewAllOutletCash,
        ])->layout('layouts.dashboard', ['title' => __('Manajemen kas')]);
    }

    private function outletCashSummary(bool $allOutlets, ?Warehouse $centralWarehouse)
    {
        $outletIds = $allOutlets
            ? Outlet::query()->where('is_active', true)->select('id')
            : Outlet::query()->whereKey($this->outletId())->select('id');

        $latestCashIds = DB::table('warehouse_cash_ledgers')
            ->selectRaw('warehouse_id, MAX(id) as latest_id')
            ->groupBy('warehouse_id');

        $cashByOutlet = DB::table('warehouse_cash_ledgers as cash')
            ->joinSub($latestCashIds, 'latest_cash', function ($join) {
                $join->on('latest_cash.warehouse_id', '=', 'cash.warehouse_id')
                    ->on('latest_cash.latest_id', '=', 'cash.id');
            })
            ->join('warehouses as cash_warehouse', 'cash_warehouse.id', '=', 'cash.warehouse_id')
            ->whereNotNull('cash_warehouse.outlet_id')
            ->whereIn('cash_warehouse.outlet_id', $outletIds)
            ->selectRaw('cash_warehouse.outlet_id, SUM(cash.balance_after) as outlet_balance')
            ->groupBy('cash_warehouse.outlet_id');

        $collectedByOutlet = DB::table('cash_handovers')
            ->join('warehouses as source_warehouse', 'source_warehouse.id', '=', 'cash_handovers.source_warehouse_id')
            ->where('cash_handovers.status', 'confirmed')
            ->when($centralWarehouse, fn ($query) => $query->where('cash_handovers.destination_warehouse_id', $centralWarehouse->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotNull('source_warehouse.outlet_id')
            ->whereIn('source_warehouse.outlet_id', $outletIds)
            ->selectRaw('source_warehouse.outlet_id, SUM(cash_handovers.amount) as collected_amount')
            ->groupBy('source_warehouse.outlet_id');

        return Outlet::query()
            ->whereIn('outlets.id', $outletIds)
            ->leftJoinSub($cashByOutlet, 'outlet_cash', fn ($join) => $join->on('outlet_cash.outlet_id', '=', 'outlets.id'))
            ->leftJoinSub($collectedByOutlet, 'collected_cash', fn ($join) => $join->on('collected_cash.outlet_id', '=', 'outlets.id'))
            ->selectRaw('outlets.id, outlets.name, COALESCE(outlet_cash.outlet_balance, 0) as outlet_balance, COALESCE(collected_cash.collected_amount, 0) as collected_amount')
            ->orderBy('outlets.name')
            ->get()
            ->map(fn ($outlet) => (object) [
                'id' => $outlet->id,
                'name' => $outlet->name,
                'outlet_balance_cents' => FixedDecimal::money((string) $outlet->outlet_balance),
                'collected_cents' => FixedDecimal::money((string) $outlet->collected_amount),
            ]);
    }

    private function ledgerQuery()
    {
        $ids = $this->currentWarehouses()->pluck('id');
        if ($this->ledgerWarehouse !== '' && ! $ids->contains((int) $this->ledgerWarehouse)) {
            abort(404);
        }

        return WarehouseCashLedger::whereIn('warehouse_id', $ids)
            ->when(auth()->user()->hasRole('cashier') && ! auth()->user()->hasRole('super-admin'), fn ($q) => $q->whereIn('cashier_shift_id', CashierShift::query()->select('id')->where('open_user_id', auth()->id())))
            ->when($this->ledgerWarehouse !== '', fn ($q) => $q->where('warehouse_id', (int) $this->ledgerWarehouse));
    }

    private function currentWarehouses()
    {
        $outletId = $this->outletId();

        return Warehouse::where(function ($q) use ($outletId) {
            $q->where('outlet_id', $outletId);
            if ($this->canAccessCentralCash()) {
                $q->orWhere(fn ($c) => $c->whereNull('outlet_id')->where('type', 'central'));
            }
        });
    }

    private function canAccessCentralCash(): bool
    {
        $user = auth()->user();

        return $user->hasRole('super-admin')
            || (! $user->hasRole('cashier') && ($user->hasAnyRole(['warehouse', 'finance']) || $user->can('bank-accounts-access')));
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
