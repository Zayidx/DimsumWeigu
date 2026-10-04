<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'unit_id', 'quantity', 'manual_discount', 'notes'];

    protected function casts(): array { return ['quantity' => 'decimal:3', 'manual_discount' => 'decimal:2']; }

    public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
}
