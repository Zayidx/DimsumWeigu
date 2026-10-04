<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = ['stock_opname_id', 'product_id', 'system_quantity', 'counted_quantity', 'variance', 'notes'];
    protected function casts(): array { return ['system_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'variance' => 'decimal:3']; }
    public function opname(): BelongsTo { return $this->belongsTo(StockOpname::class, 'stock_opname_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
