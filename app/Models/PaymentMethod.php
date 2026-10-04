<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'provider', 'is_enabled', 'requires_confirmation'];

    protected function casts(): array { return ['is_enabled' => 'boolean', 'requires_confirmation' => 'boolean']; }
}
