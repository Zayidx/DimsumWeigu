<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    protected $fillable = ['customer_id', 'transaction_id', 'reference', 'amount', 'paid_amount', 'due_at', 'status', 'notes'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_at' => 'date']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
    public function payments(): HasMany { return $this->hasMany(ReceivablePayment::class); }
}
