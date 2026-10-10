<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrisReconciliation extends Model
{
    protected $fillable = [
        'outlet_id', 'sales_date', 'settlement_date', 'reported_gross', 'fee_amount',
        'net_received', 'pos_gross_at_reconciliation', 'merchant_reference', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'sales_date' => 'date',
            'settlement_date' => 'date',
            'reported_gross' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_received' => 'decimal:2',
            'pos_gross_at_reconciliation' => 'decimal:2',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
