<?php

namespace App\Livewire\Finance;

use App\Models\Receivable;
use App\Services\Finance\SettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ReceivablesIndex extends Component
{
    use WithPagination;
    public bool $showForm = false;
    public ?int $receivableId = null;
    public string $amount = '';
    public string $method = 'bank-transfer';
    public string $reference = '';
    public string $idempotencyKey = '';

    public function openPayment(int $id): void
    {
        $this->permit('receivables-collect');
        $receivable = $this->query()->whereKey($id)->firstOrFail();
        $this->receivableId = $id;
        $this->amount = number_format((float)$receivable->amount - (float)$receivable->paid_amount, 2, '.', '');
        $this->method = 'bank-transfer'; $this->reference = ''; $this->idempotencyKey = (string) Str::uuid(); $this->showForm = true;
    }

    public function closeForm(): void { $this->showForm = false; $this->receivableId = null; $this->amount = ''; $this->reference = ''; $this->resetValidation(); }

    public function collect(SettlementService $settlements): void
    {
        $this->permit('receivables-collect');
        $data = $this->validate(['receivableId' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'], 'method' => ['required', 'in:cash,bank-transfer,qris'], 'reference' => ['nullable', 'string', 'max:160'], 'idempotencyKey' => ['required', 'string', 'max:96']]);
        $settlements->collectReceivable($data['receivableId'], $data['amount'], $data['method'], $data['idempotencyKey'], $data['reference'] ?: null);
        $this->closeForm();
        $this->dispatch('toast', type: 'success', message: __('Pembayaran piutang berhasil dicatat.'));
    }

    public function render()
    {
        $this->permit('receivables-access');
        return view('livewire.finance.receivables-index', ['receivables' => $this->query()->with('customer:id,name,phone')->withCount('payments')->latest()->paginate(25)])
            ->layout('layouts.dashboard', ['title' => __('Piutang pelanggan')]);
    }

    private function query(): Builder
    {
        return Receivable::query()->whereHas('transaction', fn (Builder $query) => $query->where('outlet_id', (int) session('active_outlet_id')));
    }
    private function permit(string $permission): void { abort_unless(auth()->user()?->can($permission), 403); }
}
