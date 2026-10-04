<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['uuid', 'user_id', 'cashier_shift_id', 'outlet_id', 'warehouse_id', 'customer_id', 'status', 'hold_label', 'notes', 'held_at'];

    protected function casts(): array { return ['held_at' => 'datetime']; }

    public function items(): HasMany { return $this->hasMany(CartItem::class); }

    public function shift(): BelongsTo { return $this->belongsTo(CashierShift::class, 'cashier_shift_id'); }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
