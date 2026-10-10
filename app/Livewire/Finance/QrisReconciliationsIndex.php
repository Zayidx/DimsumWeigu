<?php

namespace App\Livewire\Finance;

use App\Models\QrisReconciliation;
use App\Support\AuditLogger;
use App\Support\OutletAccess;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class QrisReconciliationsIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $outletId = '';

    public string $salesDate = '';

    public string $settlementDate = '';

    public string $reportedGross = '';

    public string $feeAmount = '0';

    public string $netReceived = '';

    public string $merchantReference = '';

    public string $notes = '';

    public string $from = '';

    public string $to = '';

    public function mount(OutletAccess $access): void
    {
        $today = today();
        $this->salesDate = $today->toDateString();
        $this->settlementDate = $today->toDateString();
        $this->from = $today->copy()->startOfMonth()->toDateString();
        $this->to = $today->toDateString();
        $this->outletId = (string) ($access->current(request())?->id ?? '');
    }

    public function updatedOutletId(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function useUnreconciledDay(int $outletId, string $salesDate, OutletAccess $access): void
    {
        abort_unless($access->outletsFor(auth()->user())->whereKey($outletId)->exists(), 404);
        $this->outletId = (string) $outletId;
        $this->salesDate = $salesDate;
        $this->resetValidation();
        $this->dispatch('qris-reconciliation-form-ready');
    }

    public function save(OutletAccess $access, AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->can('qris-reconciliations-create'), 403);

        $data = $this->validate([
            'outletId' => ['required', 'integer'],
            'salesDate' => ['required', 'date', 'before_or_equal:today'],
            'settlementDate' => ['required', 'date', 'after_or_equal:salesDate'],
            'reportedGross' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'feeAmount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'netReceived' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'merchantReference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $outlet = $access->require(auth()->user(), (int) $data['outletId']);
        $grossCents = (int) round((float) $data['reportedGross'] * 100);
        $feeCents = (int) round((float) $data['feeAmount'] * 100);
        $netCents = (int) round((float) $data['netReceived'] * 100);
        if ($feeCents > $grossCents) {
            $this->addError('feeAmount', __('Biaya QRIS tidak boleh melebihi nilai bruto.'));

            return;
        }

        if (QrisReconciliation::query()->where('outlet_id', $outlet->id)->whereDate('sales_date', $data['salesDate'])->exists()) {
            $this->addError('salesDate', __('Rekonsiliasi untuk outlet dan tanggal penjualan ini sudah ada.'));

            return;
        }

        $posGross = $this->posGrossFor($outlet->id, $data['salesDate']);

        try {
            DB::transaction(function () use ($data, $outlet, $grossCents, $feeCents, $netCents, $posGross, $audit): void {
                $reconciliation = QrisReconciliation::query()->create([
                    'outlet_id' => $outlet->id,
                    'sales_date' => $data['salesDate'],
                    'settlement_date' => $data['settlementDate'],
                    'reported_gross' => number_format($grossCents / 100, 2, '.', ''),
                    'fee_amount' => number_format($feeCents / 100, 2, '.', ''),
                    'net_received' => number_format($netCents / 100, 2, '.', ''),
                    'pos_gross_at_reconciliation' => number_format($posGross / 100, 2, '.', ''),
                    'merchant_reference' => filled($data['merchantReference']) ? trim($data['merchantReference']) : null,
                    'notes' => filled($data['notes']) ? trim($data['notes']) : null,
                    'recorded_by' => auth()->id(),
                ]);

                $audit->record('qris.reconciliation_recorded', $reconciliation, null, [
                    'outlet_id' => $outlet->id,
                    'sales_date' => $data['salesDate'],
                    'settlement_date' => $data['settlementDate'],
                    'pos_gross' => number_format($posGross / 100, 2, '.', ''),
                    'reported_gross' => number_format($grossCents / 100, 2, '.', ''),
                    'fee_amount' => number_format($feeCents / 100, 2, '.', ''),
                    'net_received' => number_format($netCents / 100, 2, '.', ''),
                    'expected_net' => number_format(($grossCents - $feeCents) / 100, 2, '.', ''),
                    'merchant_reference' => $reconciliation->merchant_reference,
                ], ['outlet_id' => $outlet->id]);
            });
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw $exception;
            }

            $this->addError('salesDate', __('Rekonsiliasi untuk outlet dan tanggal penjualan ini sudah tercatat.'));

            return;
        }

        $this->reset(['reportedGross', 'netReceived', 'merchantReference', 'notes']);
        $this->feeAmount = '0';
        $this->dispatch('toast', type: 'success', message: __('Rekonsiliasi QRIS manual berhasil dicatat.'));
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()?->can('qris-reconciliations-access'), 403);
        $validated = $this->validate([
            'outletId' => ['nullable', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        abort_if(now()->parse($validated['from'])->diffInDays(now()->parse($validated['to'])) > 366, 422, __('Rentang laporan maksimal satu tahun.'));

        $outlets = $access->outletsFor(auth()->user())->get(['id', 'name']);
        if ($this->outletId !== '') {
            abort_unless($outlets->contains('id', (int) $this->outletId), 404);
        }
        $outletIds = $this->outletId !== '' ? [(int) $this->outletId] : $outlets->pluck('id')->all();
        $records = QrisReconciliation::query()
            ->with(['outlet:id,name', 'recorder:id,name'])
            ->whereIn('outlet_id', $outletIds)
            ->whereBetween('sales_date', [$this->from, $this->to])
            ->latest('sales_date')
            ->paginate(30);

        $periodTotals = QrisReconciliation::query()->whereIn('outlet_id', $outletIds)->whereBetween('sales_date', [$this->from, $this->to]);
        $grossTotal = (int) round((float) (clone $periodTotals)->sum('reported_gross') * 100);
        $netTotal = (int) round((float) (clone $periodTotals)->sum('net_received') * 100);

        $currentByDay = DB::table('transaction_tenders')
            ->join('transactions', 'transactions.id', '=', 'transaction_tenders.transaction_id')
            ->whereIn('transactions.outlet_id', $outletIds)
            ->whereBetween('transactions.occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->whereIn('transactions.status', ['completed', 'paid'])
            ->where('transaction_tenders.method', 'qris')
            ->where('transaction_tenders.status', 'paid')
            ->selectRaw('transactions.outlet_id, DATE(transactions.occurred_at) as sales_date, SUM(transaction_tenders.amount) as total')
            ->groupBy('transactions.outlet_id')
            ->groupByRaw('DATE(transactions.occurred_at)')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->outlet_id.'|'.$row->sales_date => (int) round((float) $row->total * 100)]);

        $records->getCollection()->transform(function (QrisReconciliation $record) use ($currentByDay): QrisReconciliation {
            $record->current_pos_gross_cents = $currentByDay[$record->outlet_id.'|'.$record->sales_date->toDateString()] ?? 0;
            $record->difference_cents = $record->current_pos_gross_cents - (int) round((float) $record->reported_gross * 100);
            $record->net_difference_cents = (int) round((float) $record->net_received * 100) - ((int) round((float) $record->reported_gross * 100) - (int) round((float) $record->fee_amount * 100));

            return $record;
        });

        $unreconciledDays = DB::table('transaction_tenders')
            ->join('transactions', 'transactions.id', '=', 'transaction_tenders.transaction_id')
            ->leftJoin('qris_reconciliations', function ($join): void {
                $join->on('qris_reconciliations.outlet_id', '=', 'transactions.outlet_id')
                    ->whereRaw('qris_reconciliations.sales_date = DATE(transactions.occurred_at)');
            })
            ->whereIn('transactions.outlet_id', $outletIds)
            ->whereBetween('transactions.occurred_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->whereIn('transactions.status', ['completed', 'paid'])
            ->where('transaction_tenders.method', 'qris')
            ->where('transaction_tenders.status', 'paid')
            ->whereNull('qris_reconciliations.id')
            ->selectRaw('transactions.outlet_id, DATE(transactions.occurred_at) as sales_date, SUM(transaction_tenders.amount) as total')
            ->groupBy('transactions.outlet_id')
            ->groupByRaw('DATE(transactions.occurred_at)')
            ->orderByDesc('sales_date')
            ->limit(14)
            ->get()
            ->map(function ($row) use ($outlets): object {
                $row->outlet_name = $outlets->firstWhere('id', (int) $row->outlet_id)?->name;

                return $row;
            });

        return view('livewire.finance.qris-reconciliations-index', [
            'outlets' => $outlets,
            'records' => $records,
            'unreconciledDays' => $unreconciledDays,
            'grossTotal' => $grossTotal,
            'netTotal' => $netTotal,
        ])->layout('layouts.dashboard', ['title' => __('Rekonsiliasi QRIS')]);
    }

    private function posGrossFor(int $outletId, string $salesDate): int
    {
        $amount = DB::table('transaction_tenders')
            ->join('transactions', 'transactions.id', '=', 'transaction_tenders.transaction_id')
            ->where('transactions.outlet_id', $outletId)
            ->whereDate('transactions.occurred_at', $salesDate)
            ->whereIn('transactions.status', ['completed', 'paid'])
            ->where('transaction_tenders.method', 'qris')
            ->where('transaction_tenders.status', 'paid')
            ->sum('transaction_tenders.amount');

        return (int) round((float) $amount * 100);
    }
}
