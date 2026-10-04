<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseCashLedger extends Model
{
    protected $fillable = ['warehouse_id', 'cashier_shift_id', 'user_id', 'type', 'amount', 'balance_before', 'balance_after', 'reference_type', 'reference_id', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
