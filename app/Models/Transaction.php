<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = ['number', 'outlet_id', 'warehouse_id', 'cashier_shift_id', 'user_id', 'customer_id', 'source', 'status', 'payment_status', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total', 'paid_total', 'change_total', 'notes', 'client_uuid', 'idempotency_key', 'public_token_hash', 'paid_at', 'occurred_at', 'metadata'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'shipping_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'paid_total' => 'decimal:2', 'change_total' => 'decimal:2', 'paid_at' => 'datetime', 'occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(TransactionTender::class);
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(TransactionCorrectionRequest::class);
    }
}
