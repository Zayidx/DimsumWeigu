<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseFinanceCashHandover extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_amount' => 'decimal:2', 'actual_amount' => 'decimal:2', 'variance_amount' => 'decimal:2', 'confirmed_at' => 'datetime'];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
