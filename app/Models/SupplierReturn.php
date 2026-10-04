<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['total' => 'decimal:2', 'completed_at' => 'datetime']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function receiving(): BelongsTo { return $this->belongsTo(GoodsReceiving::class, 'goods_receiving_id'); }
    public function items(): HasMany { return $this->hasMany(SupplierReturnItem::class); }
}
