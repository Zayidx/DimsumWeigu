<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiving extends Model
{
    protected $fillable = ['number', 'purchase_order_id', 'supplier_id', 'warehouse_id', 'received_by', 'status', 'received_at', 'idempotency_key', 'notes'];

    protected function casts(): array { return ['received_at' => 'datetime']; }

    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function items(): HasMany { return $this->hasMany(GoodsReceivingItem::class); }
}
