<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'conversion_factor', 'sell_price', 'barcode', 'is_default', 'buy_price', 'is_base', 'sku_suffix'];

    protected function casts(): array { return ['conversion_factor' => 'decimal:8', 'sell_price' => 'decimal:2', 'is_default' => 'boolean']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
}
