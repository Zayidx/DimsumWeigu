<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'outlet_id', 'event', 'subject_type', 'subject_id', 'before', 'after', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array { return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
}
