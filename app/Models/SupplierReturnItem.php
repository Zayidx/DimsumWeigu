<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class)->withTrashed(); }
    public function supplierReturn(): BelongsTo { return $this->belongsTo(SupplierReturn::class); }
}
