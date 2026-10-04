<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profit extends Model
{
    protected $fillable = ['transaction_id', 'transaction_detail_id', 'outlet_id', 'revenue', 'cost', 'profit', 'occurred_at'];

    protected function casts(): array { return ['revenue' => 'decimal:2', 'cost' => 'decimal:2', 'profit' => 'decimal:2', 'occurred_at' => 'datetime']; }

    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }

    public function detail(): BelongsTo { return $this->belongsTo(TransactionDetail::class, 'transaction_detail_id'); }

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
}
