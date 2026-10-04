<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    protected $fillable = ['number', 'warehouse_id', 'created_by', 'reviewed_by', 'finalized_by', 'status', 'snapshot_at', 'finalized_at', 'notes'];
    protected function casts(): array { return ['snapshot_at' => 'datetime', 'finalized_at' => 'datetime']; }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function items(): HasMany { return $this->hasMany(StockOpnameItem::class); }
}
