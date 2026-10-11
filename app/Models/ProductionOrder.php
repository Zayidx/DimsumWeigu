<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function request()
    {
        return $this->belongsTo(ProductionRequest::class, 'production_request_id');
    }

    public function shift()
    {
        return $this->belongsTo(ProductionShift::class, 'production_shift_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
