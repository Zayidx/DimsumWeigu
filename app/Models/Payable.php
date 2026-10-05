<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    protected $fillable = ['supplier_id', 'goods_receiving_id', 'ingredient_goods_receiving_id', 'reference', 'amount', 'paid_amount', 'due_at', 'status', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_at' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiving::class, 'goods_receiving_id');
    }

    public function ingredientReceiving(): BelongsTo
    {
        return $this->belongsTo(IngredientGoodsReceiving::class, 'ingredient_goods_receiving_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayablePayment::class);
    }
}
