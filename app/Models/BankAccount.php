<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = ['bank_name', 'account_name', 'account_number', 'sort_order', 'is_active'];

    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }
}
