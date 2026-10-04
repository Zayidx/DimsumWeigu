<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDetail extends Model
{
    protected $fillable = ['transaction_id', 'product_id', 'product_name', 'product_sku', 'unit_name', 'quantity', 'unit_price', 'discount_total', 'tax_rate', 'tax_total', 'line_total', 'unit_cost', 'notes', 'metadata'];

    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_rate' => 'decimal:4', 'tax_total' => 'decimal:2', 'line_total' => 'decimal:2', 'unit_cost' => 'decimal:2', 'metadata' => 'array']; }

    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
