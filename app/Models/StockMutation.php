<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'user_id', 'type', 'quantity', 'stock_before', 'stock_after', 'reason', 'reference_type', 'reference_id', 'idempotency_key'];

    protected function casts(): array { return ['quantity' => 'decimal:3', 'stock_before' => 'decimal:3', 'stock_after' => 'decimal:3']; }
}
