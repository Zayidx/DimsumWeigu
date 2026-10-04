<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_active, 403, __('Akun ini tidak aktif.'));
        abort_if($request->user()?->must_change_password, 403, __('Ubah kata sandi melalui halaman profil sebelum menggunakan API.'));

        return $next($request);
    }
}
