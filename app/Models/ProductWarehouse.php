<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWarehouse extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'stock', 'reserved_stock', 'min_stock', 'reorder_point'];

    protected function casts(): array { return ['stock' => 'decimal:3', 'reserved_stock' => 'decimal:3', 'min_stock' => 'decimal:3', 'reorder_point' => 'decimal:3']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
