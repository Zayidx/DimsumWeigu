<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Warehouse;
use App\Services\Cashier\CashierShiftService;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierShiftController extends Controller
{
    public function index(Request $request, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $query = CashierShift::query()->with(['user:id,name', 'warehouse:id,name'])->where('outlet_id', $outlet->id)->latest('opened_at');
        if (! $request->user()->can('cashier-shifts-close') && ! $request->user()->can('cashier-shifts-force-close')) {
            $query->where('user_id', $request->user()->id);
        }

        return view('shifts.index', ['shifts' => $query->paginate(20), 'openShift' => CashierShift::query()->with('warehouse:id,name')->where('open_user_id', $request->user()->id)->where('status', 'open')->first(), 'outlet' => $outlet]);
    }

    public function create(Request $request, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $warehouses = Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true)->where('is_sales_enabled', true)->orderBy('name')->get();
        $shift = CashierShift::query()->where('open_user_id', $request->user()->id)->where('status', 'open')->first();

        return view('shifts.create', compact('outlet', 'warehouses', 'shift'));
    }

    public function store(Request $request, OutletAccess $access, CashierShiftService $service): RedirectResponse
    {
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'opening_cash' => ['required', 'numeric', 'min:0', 'max:9999999999999.99']]);
        $outlet = $access->current($request);
        $shift = $service->open($outlet->id, (int) $data['warehouse_id'], (string) $data['opening_cash']);
        $request->session()->put('active_warehouse_id', $shift->warehouse_id);

        return redirect()->route('dashboard')->with('status', __('Shift berhasil dibuka.'));
    }

    public function close(Request $request, CashierShiftService $service): RedirectResponse
    {
        $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'], 'note' => ['nullable', 'string', 'max:1000']]);
        $shift = CashierShift::query()->where('open_user_id', $request->user()->id)->where('status', 'open')->firstOrFail();
        $service->close($shift->id, (string) $data['actual_cash'], $data['note'] ?? null);
        $request->session()->forget('active_warehouse_id');

        return redirect()->route('shifts.index')->with('status', __('Shift berhasil ditutup dan direkonsiliasi.'));
    }

    public function forceForm(Request $request, int $shift, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $record = CashierShift::with(['user:id,name', 'warehouse:id,name'])->where('outlet_id', $outlet->id)->where('status', 'open')->findOrFail($shift);

        return view('shifts.force-close', ['shift' => $record, 'expected' => app(CashierShiftService::class)->expectedCashCents($record) / 100]);
    }

    public function forceClose(Request $request, int $shift, CashierShiftService $service): RedirectResponse
    {
        $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'], 'reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $service->forceClose($shift, (string) $data['actual_cash'], $data['reason']);

        return redirect()->route('shifts.index')->with('status', __('Shift ditutup paksa dengan rekonsiliasi dan audit.'));
    }
}
