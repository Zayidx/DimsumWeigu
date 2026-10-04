<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'symbol', 'dimension', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function productUnits(): HasMany { return $this->hasMany(ProductUnit::class); }
}
