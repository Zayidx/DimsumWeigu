<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active'];

    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }

    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }

    public function products(): HasMany { return $this->hasMany(Product::class); }
}
