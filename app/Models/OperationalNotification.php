<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalNotification extends Model
{
    protected $fillable = ['outlet_id', 'user_id', 'type', 'title', 'body', 'reference_type', 'reference_id', 'read_at'];
    protected function casts(): array { return ['read_at' => 'datetime']; }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
