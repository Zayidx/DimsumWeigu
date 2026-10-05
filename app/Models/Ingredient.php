<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    protected $guarded = ['id'];

    use SoftDeletes;

    protected function casts(): array
    {
        return ['buy_price' => 'decimal:8', 'stock' => 'decimal:3', 'reorder_point' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }
}
