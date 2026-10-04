<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferBatchItem extends Model
{
    protected $fillable = ['stock_transfer_item_id', 'product_batch_id', 'quantity_sent', 'quantity_received'];
    protected function casts(): array { return ['quantity_sent' => 'decimal:3', 'quantity_received' => 'decimal:3']; }
    public function transferItem(): BelongsTo { return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(ProductBatch::class, 'product_batch_id'); }
}
