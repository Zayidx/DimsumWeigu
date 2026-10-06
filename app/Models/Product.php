<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['category_id', 'unit_id', 'sku', 'barcode', 'title', 'description', 'image_path', 'buy_price', 'sell_price', 'tax_rate', 'tax_inclusive', 'stock', 'min_stock', 'max_stock', 'reorder_point', 'is_active', 'is_sellable', 'is_semi_finished', 'allow_single_unit_sale', 'track_stock', 'track_batches'];

    protected function casts(): array
    {
        return ['buy_price' => 'decimal:2', 'sell_price' => 'decimal:2', 'tax_rate' => 'decimal:4', 'tax_inclusive' => 'boolean', 'stock' => 'decimal:3', 'min_stock' => 'decimal:3', 'max_stock' => 'decimal:3', 'reorder_point' => 'decimal:3', 'is_active' => 'boolean', 'is_sellable' => 'boolean', 'is_semi_finished' => 'boolean', 'allow_single_unit_sale' => 'boolean', 'track_stock' => 'boolean', 'track_batches' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouse::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function recipeVersions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }

    public function scopeSellableIn(Builder $query, int $warehouseId): Builder
    {
        return $query->where('products.is_active', true)->where('products.is_sellable', true)
            ->whereHas('warehouseStocks', fn (Builder $stocks) => $stocks->where('warehouse_id', $warehouseId)->whereColumn('stock', '>', 'reserved_stock'));
    }
}
