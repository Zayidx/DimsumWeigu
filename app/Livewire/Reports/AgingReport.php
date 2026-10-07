<?php

namespace App\Livewire\Reports;

use App\Exports\MasterDataExcelExport;
use App\Models\Payable;
use App\Models\Receivable;
use App\Support\OutletAccess;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgingReport extends Component
{
    private const BUCKETS = ['current' => 'Belum jatuh tempo', '1-30' => '1–30 hari', '31-60' => '31–60 hari', '61-90' => '61–90 hari', '90+' => '>90 hari'];

    public string $outletId = '';

    public function export(OutletAccess $access): StreamedResponse
    {
        abort_unless(auth()->user()->can('reports-export'), 403);
        [$receivables,$payables] = $this->reports($access);

        $data = [];
        foreach (['Piutang' => $receivables, 'Hutang' => $payables] as $type => $rows) {
            foreach (self::BUCKETS as $key => $label) {
                $row = $rows->firstWhere('bucket', $key);
                $data[] = [$type, $label, $row->item_count ?? 0, $row->outstanding ?? 0];
            }
        }

        return Excel::download(new MasterDataExcelExport(['Jenis', 'Kelompok umur', 'Jumlah dokumen', 'Saldo'], $data, 'Aging'), 'aging-'.($this->outletId ?: 'all').'-'.today()->toDateString().'.xlsx');
    }

    public function render(OutletAccess $access)
    {
        abort_unless(auth()->user()->can('reports-access'), 403);
        [$receivables,$payables] = $this->reports($access);

        return view('livewire.reports.aging-report', ['receivables' => $this->fillBuckets($receivables), 'payables' => $this->fillBuckets($payables), 'outlets' => $access->outletsFor(auth()->user())->get(['id', 'name'])])->layout('layouts.dashboard', ['title' => __('Aging piutang & hutang')]);
    }

    private function reports(OutletAccess $access): array
    {
        $outletIds = $access->outletsFor(auth()->user())->pluck('id');
        if ($this->outletId !== '') {
            abort_unless($outletIds->contains((int) $this->outletId), 404);
            $outletIds = collect([(int) $this->outletId]);
        }
        $receivableQuery = Receivable::query()->whereIn('status', ['unpaid', 'partial'])->whereHas('transaction', fn (Builder $query) => $query->whereIn('outlet_id', $outletIds));
        $payableQuery = Payable::query()->whereIn('status', ['unpaid', 'partial'])->where(function (Builder $query) use ($outletIds): void {
            $query->whereHas('receiving.warehouse', fn (Builder $warehouse) => $warehouse->whereIn('outlet_id', $outletIds))
                ->orWhereHas('ingredientReceiving.warehouse', fn (Builder $warehouse) => $warehouse->whereIn('outlet_id', $outletIds));
        });

        return [$this->aggregate($receivableQuery), $this->aggregate($payableQuery)];
    }

    private function aggregate(Builder $query)
    {
        $today = today()->toDateString();
        $date30 = today()->subDays(30)->toDateString();
        $date60 = today()->subDays(60)->toDateString();
        $date90 = today()->subDays(90)->toDateString();

        return $query->selectRaw("CASE WHEN due_at IS NULL OR due_at >= ? THEN 'current' WHEN due_at >= ? THEN '1-30' WHEN due_at >= ? THEN '31-60' WHEN due_at >= ? THEN '61-90' ELSE '90+' END AS bucket", [$today, $date30, $date60, $date90])
            ->selectRaw('COUNT(*) AS item_count, SUM(amount - paid_amount) AS outstanding')->groupBy('bucket')->get();
    }

    private function fillBuckets($rows): array
    {
        return collect(self::BUCKETS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($rows->firstWhere('bucket', $key)->item_count ?? 0), 'amount' => (float) ($rows->firstWhere('bucket', $key)->outstanding ?? 0)])->values()->all();
    }
}
