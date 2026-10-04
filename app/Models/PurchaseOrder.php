<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = ['number', 'supplier_id', 'warehouse_id', 'created_by', 'status', 'ordered_at', 'expected_at', 'subtotal', 'tax_total', 'fees_total', 'grand_total', 'notes'];

    protected function casts(): array { return ['ordered_at' => 'date', 'expected_at' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'fees_total' => 'decimal:2', 'grand_total' => 'decimal:2']; }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function receivings(): HasMany { return $this->hasMany(GoodsReceiving::class); }
}
