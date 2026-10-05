<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionRequestItem extends Model
{
    protected $guarded = ['id'];

    public function request()
    {
        return $this->belongsTo(ProductionRequest::class, 'production_request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function recipe()
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }
}
