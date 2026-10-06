<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function show(Request $request, int $transaction, OutletAccess $access): View
    {
        abort_unless($request->user()->can('transactions-access'), 403);
        $outletIds = $access->outletsFor($request->user())->pluck('id');
        $sale = Transaction::query()->with(['customer', 'user:id,name', 'warehouse:id,name', 'details', 'tenders'])
            ->whereIn('outlet_id', $outletIds)
            ->when($request->user()->hasRole('cashier') && ! $request->user()->hasRole('super-admin'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereKey($transaction)->firstOrFail();

        return view('transactions.show', ['transaction' => $sale]);
    }

    public function receipt(Request $request, int $transaction, OutletAccess $access): View
    {
        abort_unless($request->user()->can('transactions-access'), 403);
        $size = $request->validate(['size' => ['nullable', 'in:58,80']])['size'] ?? '80';
        $outletIds = $access->outletsFor($request->user())->pluck('id');
        $sale = Transaction::query()->with(['customer', 'user:id,name', 'warehouse:id,name', 'details', 'tenders', 'outlet'])
            ->whereIn('outlet_id', $outletIds)
            ->when($request->user()->hasRole('cashier') && ! $request->user()->hasRole('super-admin'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereKey($transaction)->firstOrFail();

        return view('transactions.receipt', ['transaction' => $sale, 'outlet' => $sale->outlet, 'width' => $size]);
    }
}
