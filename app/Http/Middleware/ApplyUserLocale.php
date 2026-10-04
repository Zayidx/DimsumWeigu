<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? $request->getPreferredLanguage(['id', 'en'])
            ?? config('app.locale', 'id');

        App::setLocale(in_array($locale, ['id', 'en'], true) ? $locale : 'id');

        return $next($request);
    }
}
