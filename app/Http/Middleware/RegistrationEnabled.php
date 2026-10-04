<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('auth.public_registration'), 404);

        return $next($request);
    }
}
