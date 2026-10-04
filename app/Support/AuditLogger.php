<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    private const REDACTED_KEYS = ['password', 'password_confirmation', 'remember_token', 'token', 'secret', 'credential', 'api_key', 'private_key', 'webhook_secret', 'qr_session'];

    public function record(string $event, ?Model $subject = null, ?array $before = null, ?array $after = null, array $context = []): AuditLog
    {
        $request = app(Request::class);

        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'outlet_id' => $context['outlet_id'] ?? $request->attributes->get('api_outlet_id') ?? ($request->hasSession() ? $request->session()->get('active_outlet_id') : null),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => self::redact($before),
            'after' => self::redact($after),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    private static function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)
                || preg_match('/(?:^|_)(?:password|secret|token|credentials?|private_key|api_key|server_key|client_key)(?:$|_)/i', (string) $key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}
