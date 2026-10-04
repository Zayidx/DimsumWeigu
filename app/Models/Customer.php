<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'email', 'phone', 'birth_date', 'notes', 'credit_limit', 'loyalty_points', 'member_tier', 'marketing_consent', 'is_active'];

    protected function casts(): array { return ['birth_date' => 'date', 'credit_limit' => 'decimal:2', 'loyalty_points' => 'decimal:2', 'marketing_consent' => 'boolean', 'is_active' => 'boolean']; }

    public function addresses(): HasMany { return $this->hasMany(CustomerAddress::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }
}
