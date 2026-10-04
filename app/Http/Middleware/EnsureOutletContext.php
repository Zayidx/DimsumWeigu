<?php

namespace App\Http\Middleware;

use App\Support\OutletAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOutletContext
{
    public function __construct(private readonly OutletAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $outlet = $this->access->current($request);

        if (! $outlet) {
            return redirect()->route('outlet.select');
        }

        $request->attributes->set('activeOutlet', $outlet);

        return $next($request);
    }
}
