<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reconciled aggregate snapshot. Operational stock remains product_warehouses.stock. */
class InventoryBalance extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'calculated_quantity', 'reconciled_at'];

    protected function casts(): array { return ['calculated_quantity' => 'decimal:3', 'reconciled_at' => 'datetime']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
