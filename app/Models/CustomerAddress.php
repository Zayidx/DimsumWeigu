<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = ['customer_id', 'label', 'recipient_name', 'phone', 'province', 'city', 'district', 'subdistrict', 'postal_code', 'address', 'is_default'];

    protected function casts(): array { return ['is_default' => 'boolean']; }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
