<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRequestMaterial extends Model
{
    protected $guarded = ['id'];

    public function request()
    {
        return $this->belongsTo(ProductionRequest::class, 'production_request_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
