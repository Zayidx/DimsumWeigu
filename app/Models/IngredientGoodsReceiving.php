<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientGoodsReceiving extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(IngredientGoodsReceivingItem::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(IngredientPurchaseOrder::class, 'ingredient_purchase_order_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
