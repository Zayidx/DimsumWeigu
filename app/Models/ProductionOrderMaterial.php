<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderMaterial extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_quantity' => 'decimal:3', 'actual_quantity' => 'decimal:3', 'remaining_quantity' => 'decimal:3', 'unit_cost_snapshot' => 'decimal:8', 'actual_unit_cost' => 'decimal:8', 'actual_cost' => 'decimal:2'];
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
