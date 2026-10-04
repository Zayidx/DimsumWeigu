<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeItem extends Model
{
    protected $guarded = ['id'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
