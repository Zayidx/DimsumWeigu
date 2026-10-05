<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientPurchaseOrder extends Model
{
    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(IngredientPurchaseOrderItem::class);
    }

    public function request()
    {
        return $this->belongsTo(ProductionRequest::class, 'production_request_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
