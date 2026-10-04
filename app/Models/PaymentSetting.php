<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = ['outlet_id', 'scope_key', 'provider', 'mode', 'is_enabled', 'credentials'];

    protected function casts(): array { return ['is_enabled' => 'boolean', 'credentials' => 'encrypted:array']; }
}
