<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionCorrectionRequest;
use App\Support\AuditLogger;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function show(Request $request, int $transaction, OutletAccess $access): View
    {
        abort_unless($request->user()->can('transactions-access'), 403);
        $outletIds = $access->outletsFor($request->user())->pluck('id');
        $sale = Transaction::query()->with(['customer', 'user:id,name', 'warehouse:id,name', 'details', 'tenders', 'correctionRequests.requester:id,name', 'correctionRequests.handler:id,name'])
            ->whereIn('outlet_id', $outletIds)
            ->when($request->user()->hasRole('cashier') && ! $request->user()->hasRole('super-admin'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereKey($transaction)->firstOrFail();

        return view('transactions.show', ['transaction' => $sale]);
    }

    public function storeCorrectionRequest(Request $request, int $transaction, OutletAccess $access, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('transaction-corrections-create'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:3000']]);
        $data['reason'] = trim($data['reason']);
        if (mb_strlen($data['reason']) < 10) {
            throw ValidationException::withMessages(['reason' => __('Jelaskan koreksi sekurangnya 10 karakter.')]);
        }
        $outletIds = $access->outletsFor($request->user())->pluck('id');

        DB::transaction(function () use ($request, $transaction, $outletIds, $data, $audit) {
            $sale = Transaction::query()->whereIn('outlet_id', $outletIds)->where('user_id', $request->user()->id)
                ->where('source', 'pos')->whereKey($transaction)->lockForUpdate()->firstOrFail();
            $activeRequest = $sale->correctionRequests()->whereIn('status', ['pending', 'in_review'])->exists();
            if ($activeRequest) {
                throw ValidationException::withMessages(['reason' => __('Permintaan koreksi untuk transaksi ini masih ditinjau Finance.')]);
            }

            $correction = TransactionCorrectionRequest::query()->create([
                'transaction_id' => $sale->id,
                'requested_by' => $request->user()->id,
                'status' => 'pending',
                'reason' => trim($data['reason']),
            ]);
            $audit->record('transaction.correction_requested', $correction, null, [
                'transaction_id' => $sale->id,
                'status' => $correction->status,
                'reason' => $correction->reason,
            ], ['outlet_id' => $sale->outlet_id]);
        });

        return redirect()->route('transactions.show', $transaction)->with('status', __('Permintaan koreksi dikirim ke Finance.'));
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
