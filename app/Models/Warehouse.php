<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = ['outlet_id', 'code', 'name', 'type', 'is_active', 'is_sales_enabled'];

    protected function casts(): array { return ['is_active' => 'boolean', 'is_sales_enabled' => 'boolean']; }

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }

    public function productStocks(): HasMany { return $this->hasMany(ProductWarehouse::class); }
}
