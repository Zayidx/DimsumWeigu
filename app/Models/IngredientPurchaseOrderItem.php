<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientPurchaseOrderItem extends Model
{
    protected $guarded = ['id'];

    public function order()
    {
        return $this->belongsTo(IngredientPurchaseOrder::class, 'ingredient_purchase_order_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
