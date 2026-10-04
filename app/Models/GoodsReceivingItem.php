<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivingItem extends Model
{
    protected $fillable = ['goods_receiving_id', 'purchase_order_item_id', 'product_id', 'product_batch_id', 'quantity', 'accepted_quantity', 'rejected_quantity', 'unit_cost', 'qc_status', 'qc_note'];

    protected function casts(): array { return ['quantity' => 'decimal:3', 'accepted_quantity' => 'decimal:3', 'rejected_quantity' => 'decimal:3', 'unit_cost' => 'decimal:2']; }

    public function receiving(): BelongsTo { return $this->belongsTo(GoodsReceiving::class, 'goods_receiving_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batch(): BelongsTo { return $this->belongsTo(ProductBatch::class, 'product_batch_id'); }
}
