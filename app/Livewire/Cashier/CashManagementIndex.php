<?php

namespace App\Livewire\Cashier;

use App\Models\CashHandover;
use App\Models\CashierShift;
use App\Models\Warehouse;
use App\Models\WarehouseCashLedger;
use App\Services\Cashier\CashHandoverService;
use App\Services\Cashier\CashierShiftService;
use App\Support\OutletAccess;
use App\Support\PasswordStepUp;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CashManagementIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public bool $showForm = false;

    public string $amount = '';

    public string $notes = '';

    #[Locked]
    public string $requestKey = '';

    public string $movementType = 'in';

    public string $movementAmount = '';

    public string $movementNote = '';

    #[Locked]
    public string $movementKey = '';

    public function mount(): void
    {
        $this->requestKey = (string) Str::uuid();
        $this->movementKey = (string) Str::uuid();
    }

    public function create(): void
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();
        $this->activeShift();
        $this->reset(['amount', 'notes']);
        $this->requestKey = (string) Str::uuid();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function requestHandover(CashHandoverService $service): void
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();

        $data = $this->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
            'requestKey' => ['required', 'string', 'max:96'],
        ]);

        $shift = $this->activeShift();
        $centralWarehouse = $this->centralWarehouse();

        if (! $centralWarehouse) {
            throw ValidationException::withMessages(['handover' => __('Gudang bersama belum tersedia.')]);
        }

        $service->request($shift->warehouse_id, $centralWarehouse->id, $data['amount'], $data['notes'], $this->requestKey);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: __('Permintaan serah-terima ke gudang bersama dibuat.'));
    }

    public function recordMovement(CashierShiftService $service): void
    {
        $this->permit('cash-management-create');
        PasswordStepUp::requireRecent();

        $data = $this->validate([
            'movementType' => ['required', 'in:in,out'],
            'movementAmount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'movementNote' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $service->recordMovement($this->activeShift(), $data['movementType'], $data['movementAmount'], $data['movementNote'], $this->movementKey);
        $this->movementKey = (string) Str::uuid();
        $this->reset(['movementAmount', 'movementNote']);
        $this->dispatch('toast', type: 'success', message: __('Pergerakan kas dicatat.'));
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

    public function render()
    {
        $this->permit('cash-management-access');

        $outletId = $this->outletId();
        $shift = CashierShift::query()
            ->with('warehouse:id,name,outlet_id')
            ->where('open_user_id', auth()->id())
            ->where('outlet_id', $outletId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
        $userShiftIds = CashierShift::query()
            ->where('open_user_id', auth()->id())
            ->where('outlet_id', $outletId)
            ->select('id');
        $pettyCashPositionCents = $shift ? app(CashierShiftService::class)->cashPositionCents($shift) : null;

        return view('livewire.cashier.cash-management-index', [
            'shift' => $shift,
            'centralWarehouse' => $this->centralWarehouse(),
            'pettyCashPositionCents' => $pettyCashPositionCents,
            'handovers' => CashHandover::query()
                ->where('requested_by', auth()->id())
                ->whereIn('source_warehouse_id', Warehouse::query()->where('outlet_id', $outletId)->select('id'))
                ->with(['source:id,name', 'destination:id,name', 'requester:id,name'])
                ->latest()
                ->paginate(15, ['*'], 'handoverPage'),
            'ledger' => WarehouseCashLedger::query()
                ->whereIn('cashier_shift_id', $userShiftIds)
                ->with('warehouse:id,name')
                ->latest('id')
                ->paginate(25, ['*'], 'ledgerPage'),
        ])->layout('layouts.dashboard', ['title' => __('Kas outlet')]);
    }

    private function activeShift(): CashierShift
    {
        return CashierShift::query()
            ->where('open_user_id', auth()->id())
            ->where('outlet_id', $this->outletId())
            ->where('status', 'open')
            ->latest('opened_at')
            ->firstOrFail();
    }

    private function centralWarehouse(): ?Warehouse
    {
        return Warehouse::query()
            ->where('code', 'PUSAT')
            ->whereNull('outlet_id')
            ->where('type', 'central')
            ->where('is_active', true)
            ->first();
    }

    private function outletId(): int
    {
        return app(OutletAccess::class)->current(request())?->id ?? abort(404);
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->hasRole('cashier') && ! auth()->user()?->hasRole('super-admin'), 403);
        abort_unless(auth()->user()->can($permission), 403);
    }
}
