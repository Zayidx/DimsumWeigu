<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierRefund extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount' => 'decimal:2']; }
}
