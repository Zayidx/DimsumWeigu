<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditNote extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'available_amount' => 'decimal:2']; }
    public function supplierReturn(): BelongsTo { return $this->belongsTo(SupplierReturn::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
}
