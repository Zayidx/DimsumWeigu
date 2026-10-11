<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'decimal:3', 'produced_quantity' => 'decimal:3', 'variance_quantity' => 'decimal:3', 'yield_percent' => 'decimal:2', 'unit_cost' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function recipe()
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    public function materials()
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }
}
