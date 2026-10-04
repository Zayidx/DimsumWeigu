<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Support\OutletAccess;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogsIndex extends Component
{
    use WithPagination;
    public string $search = '';
    public string $from;
    public string $to;

    public function mount(): void { $this->from = today()->subDays(30)->toDateString(); $this->to = today()->toDateString(); }
    public function updatingSearch(): void { $this->resetPage(); }
    public function applyFilters(): void { $this->validate(['from' => ['required','date'], 'to' => ['required','date','after_or_equal:from']]); $this->resetPage(); }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('audit-logs-access'), 403);
        $this->validate(['from' => ['required','date'], 'to' => ['required','date','after_or_equal:from']]);
        abort_if(now()->parse($this->from)->diffInDays(now()->parse($this->to)) > 90, 422, __('Rentang audit maksimal 90 hari.'));
        $outlet = $access->current(request());
        $logs = AuditLog::query()->with(['user:id,name,email'])->where('outlet_id', $outlet?->id)
            ->when($this->search !== '', fn ($query) => $query->where(fn ($sub) => $sub->where('event','like','%'.$this->search.'%')->orWhere('subject_type','like','%'.$this->search.'%')->orWhereHas('user', fn ($user) => $user->where('name','like','%'.$this->search.'%'))))
            ->whereBetween('created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])->latest('created_at')->paginate(30);
        return view('livewire.admin.audit-logs-index', compact('logs','outlet'))->layout('layouts.dashboard', ['title' => __('Audit log')]);
    }
}
