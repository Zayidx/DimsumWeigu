<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'product_id', 'quantity_sent', 'quantity_received'];
    protected function casts(): array { return ['quantity_sent' => 'decimal:3', 'quantity_received' => 'decimal:3']; }
    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function batches(): HasMany { return $this->hasMany(StockTransferBatchItem::class); }
}
