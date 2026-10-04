<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $startedAt = (int) $request->session()->get('auth.authenticated_at', 0);
            if ($startedAt === 0) {
                $startedAt = now()->timestamp;
                $request->session()->put('auth.authenticated_at', $startedAt);
            }
            $lifetime = max(1, (int) config('auth.absolute_session_lifetime', 720)) * 60;

            if (now()->timestamp - $startedAt >= $lifetime) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => __('Sesi berakhir. Silakan masuk kembali.')]);
            }
        }

        return $next($request);
    }
}
