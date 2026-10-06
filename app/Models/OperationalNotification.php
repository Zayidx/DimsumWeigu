<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalNotification extends Model
{
    protected $fillable = ['outlet_id', 'user_id', 'type', 'title', 'body', 'reference_type', 'reference_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(User $user): string
    {
        if ($this->type === 'stock-low' && $user->can('inventory-access')) {
            $stock = ProductWarehouse::query()->with('product:id,sku')->whereHas('warehouse', fn ($query) => $query->where('outlet_id', $this->outlet_id))->find($this->reference_id);
            if ($stock) {
                return route('inventory.index', ['warehouse' => $stock->warehouse_id, 'search' => $stock->product?->sku]);
            }
        }

        if ($this->type === 'payable-overdue' && $user->can('payables-access')) {
            return route('payables.index');
        }
        if ($this->type === 'receivable-overdue' && $user->can('receivables-access')) {
            return route('receivables.index');
        }

        return route('notifications.index');
    }
}
