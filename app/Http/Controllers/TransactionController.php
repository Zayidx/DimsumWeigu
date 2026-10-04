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
        $outlet = $access->current($request);
        $sale = Transaction::query()->with(['customer', 'user:id,name', 'warehouse:id,name', 'details', 'tenders'])
            ->where('outlet_id', $outlet->id)->whereKey($transaction)->firstOrFail();

        return view('transactions.show', ['transaction' => $sale]);
    }

    public function receipt(Request $request, int $transaction, OutletAccess $access): View
    {
        abort_unless($request->user()->can('transactions-access'), 403);
        $size = $request->validate(['size' => ['nullable', 'in:58,80']])['size'] ?? '80';
        $outlet = $access->current($request);
        $sale = Transaction::query()->with(['customer', 'user:id,name', 'warehouse:id,name', 'details', 'tenders'])
            ->where('outlet_id', $outlet->id)->whereKey($transaction)->firstOrFail();

        return view('transactions.receipt', ['transaction' => $sale, 'outlet' => $outlet, 'width' => $size]);
    }
}
