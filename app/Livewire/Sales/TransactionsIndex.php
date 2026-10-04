<?php

namespace App\Livewire\Sales;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $from = '';
    public string $until = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingFrom(): void { $this->resetPage(); }
    public function updatingUntil(): void { $this->resetPage(); }

    public function render()
    {
        abort_unless(auth()->user()?->can('transactions-access'), 403);
        $outletId = (int) session('active_outlet_id');
        $transactions = Transaction::query()->with(['customer:id,name', 'user:id,name', 'warehouse:id,name'])
            ->where('outlet_id', $outletId)
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('number', 'like', '%'.$this->search.'%')->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$this->search.'%'))))
            ->when($this->status !== '', fn (Builder $query) => $query->where('payment_status', $this->status))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('occurred_at', '>=', $this->from))
            ->when($this->until !== '', fn (Builder $query) => $query->whereDate('occurred_at', '<=', $this->until))
            ->latest('occurred_at')->paginate(25);

        return view('livewire.sales.transactions-index', compact('transactions'))
            ->layout('layouts.dashboard', ['title' => __('Transaksi')]);
    }
}
