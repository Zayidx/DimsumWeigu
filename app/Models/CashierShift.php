<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = ['user_id', 'open_user_id', 'outlet_id', 'warehouse_id', 'status', 'opening_cash', 'expected_cash', 'actual_cash', 'cash_difference', 'opened_at', 'closed_at', 'closed_by', 'closing_note', 'is_force_closed'];

    protected function casts(): array
    {
        return ['opening_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'actual_cash' => 'decimal:2', 'cash_difference' => 'decimal:2', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'is_force_closed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(ShiftCashMovement::class);
    }
}
