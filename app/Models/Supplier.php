<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'email', 'phone', 'tax_number', 'address', 'city', 'notes', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }
}
