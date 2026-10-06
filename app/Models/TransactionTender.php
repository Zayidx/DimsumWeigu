<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionTender extends Model
{
    protected $fillable = ['transaction_id', 'bank_account_id', 'method', 'amount', 'status', 'provider_reference', 'idempotency_key', 'confirmed_at', 'metadata'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
