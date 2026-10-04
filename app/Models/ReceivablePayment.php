<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePayment extends Model
{
    protected $fillable = ['receivable_id', 'user_id', 'method', 'amount', 'reference', 'idempotency_key', 'paid_at'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'datetime']; }
    public function receivable(): BelongsTo { return $this->belongsTo(Receivable::class); }
}
