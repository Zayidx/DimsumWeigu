<?php

namespace App\Livewire\Sales;

use App\Models\Transaction;
use App\Support\OutletAccess;
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

    public string $outletId = '';

    public function updatingOutletId(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingUntil(): void
    {
        $this->resetPage();
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()?->can('transactions-access'), 403);
        $outlets = $access->outletsFor(auth()->user())->get(['id', 'name']);
        abort_if($this->outletId !== '' && ! $outlets->contains('id', (int) $this->outletId), 404);
        $outletIds = $this->outletId !== '' ? [(int) $this->outletId] : $outlets->pluck('id')->all();
        $transactions = Transaction::query()->with(['customer:id,name', 'user:id,name', 'warehouse:id,name', 'outlet:id,name'])
            ->whereIn('outlet_id', $outletIds)
            ->when(auth()->user()->hasRole('cashier') && ! auth()->user()->hasRole('super-admin'), fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('number', 'like', '%'.$this->search.'%')->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$this->search.'%'))))
            ->when($this->status !== '', fn (Builder $query) => $query->where('payment_status', $this->status))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('occurred_at', '>=', $this->from))
            ->when($this->until !== '', fn (Builder $query) => $query->whereDate('occurred_at', '<=', $this->until))
            ->latest('occurred_at')->paginate(25);

        return view('livewire.sales.transactions-index', compact('transactions', 'outlets'))
            ->layout('layouts.dashboard', ['title' => __('Transaksi')]);
    }
}
