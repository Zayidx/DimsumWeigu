<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftCashMovement extends Model
{
    protected $fillable = ['cashier_shift_id', 'user_id', 'type', 'amount', 'reference', 'note', 'idempotency_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }
}
