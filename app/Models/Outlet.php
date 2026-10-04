<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'slug', 'name', 'phone', 'email', 'address', 'city', 'province', 'postal_code', 'map_url', 'opening_hours', 'is_active', 'is_sales_enabled'];

    protected function casts(): array
    {
        return ['opening_hours' => 'array', 'is_active' => 'boolean', 'is_sales_enabled' => 'boolean'];
    }

    public function warehouses(): HasMany { return $this->hasMany(Warehouse::class); }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_outlets')->withPivot('is_default')->withTimestamps();
    }
}
