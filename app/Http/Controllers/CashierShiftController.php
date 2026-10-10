<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Models\Warehouse;
use App\Services\Cashier\CashierShiftService;
use App\Support\FixedDecimal;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierShiftController extends Controller
{
    public function index(Request $request, OutletAccess $access): View
    {
        $outlet = $access->current($request);
        $openShift = CashierShift::query()->with('warehouse:id,name')->where('open_user_id', $request->user()->id)->where('status', 'open')->first();
        if ($openShift && $openShift->outlet_id !== $outlet->id) {
            $outlet = $access->require($request->user(), $openShift->outlet_id);
            $request->session()->put('active_outlet_id', $outlet->id);
            $request->session()->put('active_warehouse_id', $openShift->warehouse_id);
        }
        $query = CashierShift::query()->with(['user:id,name', 'warehouse:id,name'])->where('outlet_id', $outlet->id)->latest('opened_at');
        if (($request->user()->hasRole('cashier') && ! $request->user()->hasRole('super-admin')) || (! $request->user()->can('cashier-shifts-close') && ! $request->user()->can('cashier-shifts-force-close'))) {
            $query->where('user_id', $request->user()->id);
        }

        $shiftSummary = null;
        if ($openShift) {
            $transactionIds = Transaction::query()->where('cashier_shift_id', $openShift->id)->whereIn('status', ['completed', 'paid'])->select('id');
            $tenders = TransactionTender::query()->whereIn('transaction_id', $transactionIds)->where('status', 'paid')->whereIn('method', ['cash', 'qris'])->get(['method', 'amount']);
            $cashSales = $tenders->where('method', 'cash')->sum(fn (TransactionTender $tender) => FixedDecimal::money((string) $tender->amount));
            $qrisSales = $tenders->where('method', 'qris')->sum(fn (TransactionTender $tender) => FixedDecimal::money((string) $tender->amount));
            $movements = $openShift->cashMovements()->get(['type', 'amount']);
            $cashIn = $movements->where('type', 'in')->sum(fn ($movement) => FixedDecimal::money((string) $movement->amount));
            $cashOut = $movements->where('type', 'out')->sum(fn ($movement) => FixedDecimal::money((string) $movement->amount));

            $shiftSummary = [
                'expected_cents' => app(CashierShiftService::class)->expectedCashCents($openShift),
                'cash_sales_cents' => $cashSales,
                'qris_sales_cents' => $qrisSales,
                'cash_in_cents' => $cashIn,
                'cash_out_cents' => $cashOut,
            ];
        }

        return view('shifts.index', ['shifts' => $query->paginate(20), 'openShift' => $openShift, 'outlet' => $outlet, 'shiftSummary' => $shiftSummary]);
    }

    public function create(Request $request, OutletAccess $access): View|RedirectResponse
    {
        $outlets = $access->outletsFor($request->user())->get(['id', 'name']);
        $requestedOutlet = $request->query('outlet');
        $outlet = $requestedOutlet === null ? $access->current($request) : $access->require($request->user(), (int) $requestedOutlet);
        $warehouses = Warehouse::query()->where('outlet_id', $outlet->id)->where('is_active', true)->where('is_sales_enabled', true)->orderBy('name')->get();
        $shift = CashierShift::query()->where('open_user_id', $request->user()->id)->where('status', 'open')->first();
        if ($shift) {
            $activeOutlet = $access->require($request->user(), $shift->outlet_id);
            $request->session()->put('active_outlet_id', $activeOutlet->id);
            $request->session()->put('active_warehouse_id', $shift->warehouse_id);

            return redirect()->route('pos.index');
        }

        return view('shifts.create', compact('outlet', 'outlets', 'warehouses', 'shift'));
    }

    public function store(Request $request, OutletAccess $access, CashierShiftService $service): RedirectResponse
    {
        $data = $request->validate(['outlet_id' => ['nullable', 'integer'], 'warehouse_id' => ['required', 'integer']]);
        $outlet = isset($data['outlet_id']) ? $access->require($request->user(), (int) $data['outlet_id']) : $access->current($request);
        $shift = $service->open($outlet->id, (int) $data['warehouse_id']);
        $request->session()->put('active_outlet_id', $outlet->id);
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
