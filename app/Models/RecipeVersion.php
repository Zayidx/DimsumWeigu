<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeVersion extends Model
{
    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
