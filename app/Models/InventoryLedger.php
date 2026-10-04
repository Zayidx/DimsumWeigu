<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLedger extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'warehouse_id', 'product_batch_id', 'user_id', 'type', 'quantity_delta', 'quantity_before', 'quantity_after', 'reference_type', 'reference_id', 'idempotency_key', 'metadata', 'created_at'];

    protected function casts(): array { return ['quantity_delta' => 'decimal:3', 'quantity_before' => 'decimal:3', 'quantity_after' => 'decimal:3', 'metadata' => 'array', 'created_at' => 'datetime']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
