<?php

namespace App\Livewire\Finance;

use App\Models\Payable;
use App\Services\Finance\SettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class PayablesIndex extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $payableId = null;

    public string $amount = '';

    public string $method = 'bank-transfer';

    public string $reference = '';

    public string $idempotencyKey = '';

    public function openPayment(int $id): void
    {
        $this->permit('payables-pay');
        $payable = $this->query()->whereKey($id)->firstOrFail();
        $this->payableId = $id;
        $this->amount = \App\Support\DisplayNumber::format((float) $payable->amount - (float) $payable->paid_amount, 2, '.', '');
        $this->method = 'bank-transfer';
        $this->reference = '';
        $this->idempotencyKey = (string) Str::uuid();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->payableId = null;
        $this->amount = '';
        $this->reference = '';
        $this->resetValidation();
    }

    public function pay(SettlementService $settlements): void
    {
        $this->permit('payables-pay');
        $data = $this->validate(['payableId' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'], 'method' => ['required', 'in:cash,bank-transfer,qris'], 'reference' => ['nullable', 'string', 'max:160'], 'idempotencyKey' => ['required', 'string', 'max:96']]);
        $settlements->payPayable($data['payableId'], $data['amount'], $data['method'], $data['idempotencyKey'], $data['reference'] ?: null);
        $this->closeForm();
        $this->dispatch('toast', type: 'success', message: __('Pembayaran hutang pemasok berhasil dicatat.'));
    }

    public function render()
    {
        $this->permit('payables-access');

        return view('livewire.finance.payables-index', ['payables' => $this->query()->with('supplier:id,name')->withCount('payments')->latest()->paginate(25)])
            ->layout('layouts.dashboard', ['title' => __('Hutang pemasok')]);
    }

    private function query(): Builder
    {
        return Payable::query()->where(function (Builder $payables) {
            foreach (['receiving.warehouse', 'ingredientReceiving.warehouse'] as $relation) {
                $payables->orWhereHas($relation, function (Builder $warehouse) {
                    $warehouse->where('outlet_id', (int) session('active_outlet_id'));
                    if (auth()->user()->hasRole('super-admin') || auth()->user()->can('warehouses-access')) {
                        $warehouse->orWhereNull('outlet_id');
                    }
                });
            }
        });
    }

    private function permit(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
