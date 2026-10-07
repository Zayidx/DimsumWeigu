<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRequest extends Model
{
    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(ProductionRequestItem::class);
    }

    public function materials()
    {
        return $this->hasMany(ProductionRequestMaterial::class);
    }

    public function productionOrder()
    {
        return $this->hasOne(ProductionOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
