<div data-sales-report data-sales-charts="{{ base64_encode(json_encode($chartData, JSON_THROW_ON_ERROR)) }}">
    <form wire:submit.prevent="applyFilters" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-lg-2"><label class="form-label" for="report-from">Dari</label><input id="report-from" type="date" wire:model="from" class="form-control">@error('from')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-lg-2"><label class="form-label" for="report-to">Sampai</label><input id="report-to" type="date" wire:model="to" class="form-control">@error('to')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="report-outlet">Outlet</label><select id="report-outlet" wire:model.change="outletId" class="form-select"><option value="">Semua outlet yang diizinkan</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}">{{ $outlet->name }}</option>@endforeach</select></div>
                <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="report-warehouse">Gudang</label><select id="report-warehouse" wire:model="warehouseId" class="form-select"><option value="">Semua gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
                <div class="col-12 col-sm-6 col-lg-2"><label class="form-label" for="report-cashier">Kasir</label><select id="report-cashier" wire:model="cashierId" class="form-select"><option value="">Semua kasir</option>@foreach($cashiers as $cashier)<option value="{{ $cashier->id }}">{{ $cashier->name }}</option>@endforeach</select></div>
                <div class="col-12 col-sm-6 col-lg-3"><label class="form-label" for="report-method">Pembayaran</label><select id="report-method" wire:model="method" class="form-select"><option value="">Semua metode</option><option value="cash">Tunai</option><option value="bank-transfer">Transfer</option><option value="qris">QRIS</option><option value="pay-later">Piutang</option></select></div>
                <div class="col-12 col-lg-9 d-flex flex-wrap align-items-center justify-content-between gap-2"><span class="text-muted small">Transaksi selesai · rentang maksimal 12 bulan</span><div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i>Terapkan</button>@can('reports-export')<button type="button" wire:click="export" class="btn btn-outline-primary"><i class="bi bi-download" aria-hidden="true"></i>CSV</button>@endcan</div></div>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-4"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Nilai transaksi</span><h2 class="h4 mb-0">Rp {{ number_format($totalSales, 0, ',', '.') }}</h2></div></section></div>
        <div class="col-12 col-sm-6 col-xl-4"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Transaksi</span><h2 class="h4 mb-0">{{ number_format($totalTransactions, 0, ',', '.') }}</h2></div></section></div>
        <div class="col-12 col-sm-6 col-xl-4"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Transaksi lunas</span><h2 class="h4 mb-0">Rp {{ number_format($paidSales, 0, ',', '.') }}</h2></div></section></div>
    </div>

    @if($totalTransactions > 0)
        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8"><section class="card h-100" aria-labelledby="sales-daily-title"><div class="card-header"><h2 id="sales-daily-title" class="card-title mb-0">Tren penjualan harian</h2></div><div class="card-body"><div id="sales-chart-daily" class="sales-report-chart" aria-label="Grafik tren nilai penjualan per hari"></div></div></section></div>
            <div class="col-12 col-xl-4"><section class="card h-100" aria-labelledby="sales-method-title"><div class="card-header"><h2 id="sales-method-title" class="card-title mb-0">Metode pembayaran</h2></div><div class="card-body"><div id="sales-chart-methods" class="sales-report-chart" aria-label="Grafik nilai pembayaran per metode"></div></div></section></div>
            <div class="col-12 col-xl-6"><section class="card h-100" aria-labelledby="sales-outlet-title"><div class="card-header"><h2 id="sales-outlet-title" class="card-title mb-0">Perbandingan outlet</h2></div><div class="card-body"><div id="sales-chart-outlets" class="sales-report-chart" aria-label="Grafik nilai penjualan per outlet"></div></div></section></div>
            <div class="col-12 col-xl-6"><section class="card h-100" aria-labelledby="sales-product-title"><div class="card-header"><h2 id="sales-product-title" class="card-title mb-0">Produk terlaris</h2></div><div class="card-body"><div id="sales-chart-products" class="sales-report-chart" aria-label="Grafik nilai penjualan produk terlaris"></div></div></section></div>
        </div>
    @else
        <div class="alert alert-light-info mb-4" role="status">Belum ada transaksi selesai untuk tanggal dan filter yang dipilih.</div>
    @endif

    <section class="card" aria-labelledby="sales-detail-title">
        <div class="card-header"><h2 id="sales-detail-title" class="card-title mb-0">Rincian penjualan</h2></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nomor / waktu</th><th>Kasir</th><th>Outlet / gudang</th><th>Pelanggan</th><th>Metode</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody>
            @forelse($rows as $row)
                <tr><td><a href="{{ route('transactions.show', $row) }}" class="fw-bold">{{ $row->number }}</a><div class="small text-muted">{{ $row->occurred_at?->format('d/m/Y H:i') }}</div></td><td>{{ $row->user?->name ?? '—' }}</td><td>{{ $outlets->firstWhere('id', $row->outlet_id)?->name ?? '—' }}<small class="d-block text-muted">{{ $row->warehouse?->name ?? '—' }}</small></td><td>{{ $row->customer?->name ?? 'Pelanggan umum' }}</td><td>{{ $row->tenders->pluck('method')->unique()->join(', ') ?: '—' }}</td><td><span class="badge {{ $row->payment_status === 'paid' ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ str($row->payment_status)->headline() }}</span></td><td class="text-end fw-semibold">Rp {{ number_format($row->grand_total, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">Tidak ada transaksi untuk filter ini.</td></tr>
            @endforelse
        </tbody></table></div>
        @if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    </section>
</div>

@push('scripts')
    <script src="{{ asset('mazer/extensions/apexcharts/apexcharts.min.js') }}" defer></script>
    <script src="{{ asset('assets/sales-report-charts.js') }}?v={{ filemtime(public_path('assets/sales-report-charts.js')) }}" defer></script>
@endpush
