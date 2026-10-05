<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientGoodsReceivingItem extends Model
{
    protected $guarded = ['id'];

    public function receiving()
    {
        return $this->belongsTo(IngredientGoodsReceiving::class, 'ingredient_goods_receiving_id');
    }

    public function purchaseItem()
    {
        return $this->belongsTo(IngredientPurchaseOrderItem::class, 'ingredient_purchase_order_item_id');
    }
}
