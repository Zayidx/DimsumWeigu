<?php

namespace App\Http\Middleware;

use App\Models\CashierShift;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveShift
{
    public function handle(Request $request, Closure $next): Response
    {
        $shift = CashierShift::query()->where('open_user_id', $request->user()->id)
            ->where('outlet_id', $request->session()->get('active_outlet_id'))
            ->where('status', 'open')->first();

        if (! $shift) {
            return redirect()->route('shifts.create')->withErrors(['shift' => __('Buka shift pada outlet ini sebelum melanjutkan ke POS.')]);
        }

        $request->session()->put('active_warehouse_id', $shift->warehouse_id);
        $request->attributes->set('activeShift', $shift);

        return $next($request);
    }
}
