<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutletContextController extends Controller
{
    public function select(Request $request, OutletAccess $access): View
    {
        $outlets = $access->outletsFor($request->user())->get();

        return view('outlets.select', compact('outlets'));
    }

    public function switch(Request $request, OutletAccess $access): RedirectResponse
    {
        $data = $request->validate(['outlet_id' => ['required', 'integer']]);
        $outlet = $access->require($request->user(), (int) $data['outlet_id']);

        if (CashierShift::query()->where('user_id', $request->user()->id)->where('status', 'open')->exists()) {
            return back()->withErrors(['outlet_id' => __('Tutup shift aktif sebelum berpindah outlet.')]);
        }

        $warehouse = $outlet->warehouses()->where('is_active', true)->where('is_sales_enabled', true)->first();
        if (! $warehouse) {
            return back()->withErrors(['outlet_id' => __('Outlet ini belum memiliki gudang penjualan aktif.')]);
        }

        $request->session()->put('active_outlet_id', $outlet->id);
        $request->session()->forget('active_warehouse_id');

        return redirect()->route('dashboard')->with('status', __('Outlet aktif: :name', ['name' => $outlet->name]));
    }
}
