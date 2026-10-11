<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceCashLedger extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function handover()
    {
        return $this->belongsTo(WarehouseFinanceCashHandover::class, 'warehouse_finance_cash_handover_id');
    }
}
