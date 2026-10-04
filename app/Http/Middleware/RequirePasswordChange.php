<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('profile.password.force', 'profile.password.update', 'logout')) {
            return redirect()->route('profile.password.force');
        }

        return $next($request);
    }
}
