<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function request()
    {
        return $this->belongsTo(ProductionRequest::class, 'production_request_id');
    }
}
