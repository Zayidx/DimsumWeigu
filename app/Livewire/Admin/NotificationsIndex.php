<?php

namespace App\Livewire\Admin;

use App\Models\OperationalNotification;
use App\Support\OutletAccess;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationsIndex extends Component
{
    use WithPagination;
    public string $filter = 'unread';

    public function markRead(int $id): void
    {
        $this->authorizeNotifications();
        $notice = $this->scopedQuery()->whereKey($id)->firstOrFail();
        if (! $notice->read_at) $notice->forceFill(['read_at' => now()])->save();
    }

    public function markAllRead(): void
    {
        $this->authorizeNotifications();
        $this->scopedQuery()->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: __('Notifikasi ditandai sudah dibaca.'));
    }

    public function render(OutletAccess $access)
    {
        $this->authorizeNotifications();
        $query = $this->scopedQuery();
        $unreadCount = (clone $query)->whereNull('read_at')->count();
        if ($this->filter === 'unread') $query->whereNull('read_at');
        elseif ($this->filter === 'read') $query->whereNotNull('read_at');
        return view('livewire.admin.notifications-index', [
            'notifications' => $query->latest()->paginate(25),
            'unreadCount' => $unreadCount,
            'outlet' => $access->current(request()),
        ])->layout('layouts.dashboard', ['title' => __('Notifikasi')]);
    }

    private function scopedQuery()
    {
        $outletId = session('active_outlet_id');
        return OperationalNotification::query()->where(function ($scope) use ($outletId): void {
            $scope->where(function ($personal) use ($outletId): void {
                $personal->where('user_id', auth()->id())->where(function ($outlet) use ($outletId): void {
                    if ($outletId) $outlet->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                    else $outlet->whereNull('outlet_id');
                });
            });
            if ($outletId) $scope->orWhere(fn ($shared) => $shared->whereNull('user_id')->where('outlet_id', $outletId));
        });
    }

    private function authorizeNotifications(): void { abort_unless(auth()->user()?->can('notifications-access'), 403); }
}
