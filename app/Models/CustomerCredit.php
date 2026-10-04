<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    protected $fillable = ['customer_id', 'transaction_id', 'user_id', 'type', 'amount', 'balance_before', 'balance_after', 'idempotency_key', 'note'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
