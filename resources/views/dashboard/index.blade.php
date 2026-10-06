@extends('layouts.dashboard')

@section('page-title', 'Ringkasan')
@section('subtitle', 'Kondisi penjualan dan operasional · '.$outlet->name)

@section('content')
    @if (session('status'))<div class="alert alert-light-success alert-dismissible show fade" role="status">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>@endif
    @php($trendMaximum = max($salesTrend->pluck('total')->all() ?: [0]))
    <div class="row g-3 mb-4 dashboard-metrics">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Penjualan hari ini</h2><p class="dashboard-metric-value mb-0">Rp{{ number_format($todaySales, 0, ',', '.') }}</p></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Transaksi hari ini</h2><p class="dashboard-metric-value mb-0">{{ number_format($todayTransactions) }}</p></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Shift terbuka</h2><p class="dashboard-metric-value mb-0">{{ number_format($openShifts) }}</p></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Stok perlu dicek</h2><p class="dashboard-metric-value mb-0">{{ number_format($lowStockCount) }}</p></div></div></div>
    </div>

    <div class="card dashboard-trend-card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><h2 class="card-title mb-1">Tren penjualan</h2><p class="text-muted small mb-0">{{ $trendDays === 30 ? 'Per 6 hari' : 'Per hari' }} · {{ $trendStart->translatedFormat('d M') }} – {{ today()->translatedFormat('d M Y') }}</p></div>
            <nav class="dashboard-periods" aria-label="Periode tren penjualan">
                <a href="{{ route('dashboard', ['period' => 7]) }}" class="btn btn-sm {{ $trendDays === 7 ? 'btn-primary' : 'btn-outline-primary' }}" @if($trendDays === 7) aria-current="true" @endif>7 hari</a>
                <a href="{{ route('dashboard', ['period' => 30]) }}" class="btn btn-sm {{ $trendDays === 30 ? 'btn-primary' : 'btn-outline-primary' }}" @if($trendDays === 30) aria-current="true" @endif>30 hari</a>
            </nav>
        </div>
        <div class="card-body">
            <div class="dashboard-trend-summary">
                <div><span class="text-muted small">Total periode ini</span><strong>Rp{{ number_format($trendTotal, 0, ',', '.') }}</strong></div>
                @if ($previousTrendTotal > 0)
                    <span class="dashboard-trend-change {{ $trendChangePercent > 0 ? 'is-positive' : ($trendChangePercent < 0 ? 'is-negative' : '') }}">{{ $trendChangePercent > 0 ? '+' : '' }}{{ number_format($trendChangePercent, 1, ',', '.') }}% dari {{ $trendDays }} hari sebelumnya</span>
                @else
                    <span class="text-muted small">Periode sebelumnya Rp0</span>
                @endif
            </div>
            @if ($trendMaximum > 0)
                <div id="dashboard-sales-trend" class="dashboard-sales-chart" aria-hidden="true"></div>
                <table id="dashboard-sales-data" class="visually-hidden"><caption>Penjualan {{ $trendDays }} hari terakhir, {{ $trendDays === 30 ? 'dikelompokkan per enam hari' : 'per hari' }}</caption><thead><tr><th scope="col">Tanggal</th><th scope="col">Penjualan</th></tr></thead><tbody>@foreach ($salesTrend as $day)<tr><th scope="row">{{ $day['date'] }}</th><td>Rp{{ number_format($day['total'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table>
            @else
                <div class="dashboard-chart-empty"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Belum ada penjualan dalam {{ $trendDays }} hari terakhir.</span></div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        @can('transactions-access')<div class="col-12 col-xl-7"><div class="card"><div class="card-header d-flex align-items-center justify-content-between"><h4 class="card-title mb-0">Transaksi terbaru</h4><a href="{{ Route::has('transactions.index') ? route('transactions.index') : '#' }}" class="btn btn-sm btn-outline-primary" @if (! Route::has('transactions.index')) aria-disabled="true" @endif>Lihat transaksi</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th scope="col">Nomor</th><th scope="col">Pelanggan</th><th scope="col">Pembayaran</th><th scope="col" class="text-end">Total</th></tr></thead><tbody>
                @forelse ($recentTransactions as $transaction)
                    @php($paymentLabel = ['paid' => 'Lunas', 'partial' => 'Sebagian', 'unpaid' => 'Belum lunas'][$transaction->payment_status] ?? str($transaction->payment_status)->headline())
                    @php($paymentStyle = ['paid' => 'bg-light-success text-success', 'partial' => 'bg-light-warning text-warning', 'unpaid' => 'bg-light-secondary text-secondary'][$transaction->payment_status] ?? 'bg-light-secondary text-secondary')
                    <tr><td><a href="{{ route('transactions.show', $transaction) }}" class="fw-bold">{{ $transaction->number }}</a><div class="small text-muted">{{ $transaction->occurred_at?->format('d M Y, H:i') }}</div></td><td>{{ $transaction->customer?->name ?? 'Pelanggan umum' }}</td><td><span class="badge {{ $paymentStyle }}">{{ $paymentLabel }}</span></td><td class="text-end fw-semibold">Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center py-5"><i class="bi bi-receipt text-muted fs-2"></i><p class="text-muted mt-2 mb-0">Belum ada transaksi di outlet ini.</p></td></tr>
                @endforelse
            </tbody></table></div></div></div></div>
        @endcan
        <div class="col-12 {{ auth()->user()->can('transactions-access') ? 'col-xl-5' : '' }}"><div class="card"><div class="card-header d-flex align-items-center justify-content-between"><h4 class="card-title mb-0">Perhatian stok</h4>@can('inventory-access') @if (Route::has('inventory.index'))<a href="{{ route('inventory.index') }}" class="small">Buka inventori</a>@endif @endcan</div>
            <div class="card-body"><div class="list-group list-group-flush">
                @forelse ($lowStock as $row)
                    <div class="list-group-item px-0 dashboard-stock-item">
                        <div><div class="fw-semibold">{{ $row->product->title }}</div><div class="small text-muted">{{ $row->product->sku }} · {{ $row->warehouse->name }}</div><div class="small mt-1">Saldo {{ number_format((float) $row->stock, 3, ',', '.') }} / pesan ulang {{ number_format((float) $row->reorder_point, 3, ',', '.') }}</div></div>
                        @can('inventory-access')<a href="{{ route('inventory.index', ['warehouse' => $row->warehouse_id, 'search' => $row->product->sku]) }}" class="dashboard-stock-link">Lihat stok<span class="visually-hidden"> {{ $row->product->title }} di {{ $row->warehouse->name }}</span></a>@endcan
                    </div>
                @empty
                    <div class="text-center py-4"><i class="bi bi-check-circle text-success fs-2"></i><p class="text-muted mt-2 mb-0">Tidak ada produk di bawah reorder point.</p></div>
                @endforelse
            </div></div></div>
            <div class="card"><div class="card-body d-flex align-items-center justify-content-between"><div><p class="text-muted small mb-1">Shift saya</p>@if ($shift)<strong>Terbuka · {{ $shift->warehouse->name }}</strong>@else<strong>Belum dibuka</strong>@endif</div>@if (Route::has('shifts.create') && auth()->user()->can('cashier-shifts-open'))<a href="{{ ($shift ? route('shifts.index') : route('shifts.create')) }}" class="btn btn-primary btn-sm">{{ $shift ? 'Lihat shift' : 'Buka shift' }}</a>@endif</div></div>
        </div>
    </div>
@endsection

@if ($trendMaximum > 0)
    @push('scripts')
        <script src="{{ asset('mazer/extensions/apexcharts/apexcharts.min.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const target = document.querySelector('#dashboard-sales-trend');
                if (!target) return;
                const showDataTable = () => {
                    target.hidden = true;
                    document.querySelector('#dashboard-sales-data')?.classList.remove('visually-hidden');
                    document.querySelector('#dashboard-sales-data')?.classList.add('table', 'table-striped');
                };
                if (typeof ApexCharts === 'undefined') { showDataTable(); return; }

                const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
                const shortRupiah = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
                const darkMode = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
                const chart = new ApexCharts(target, {
                    chart: { type: 'bar', height: 260, background: 'transparent', toolbar: { show: false }, fontFamily: 'Nunito, sans-serif', animations: { enabled: false } },
                    series: [{ name: 'Penjualan', data: @js($salesTrend->pluck('total')->values()) }],
                    theme: { mode: darkMode() ? 'dark' : 'light' },
                    colors: [darkMode() ? '#8298ef' : '#435ebe'],
                    plotOptions: { bar: { borderRadius: 5, columnWidth: '42%' } },
                    dataLabels: { enabled: false },
                    xaxis: { categories: @js($salesTrend->pluck('label')->values()), axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { formatter: value => `Rp${shortRupiah.format(value)}`, style: { colors: darkMode() ? '#c5cee0' : '#566176' } } },
                    grid: { borderColor: darkMode() ? '#42445a' : '#e5eaf1', strokeDashArray: 4, padding: { left: 8, right: 8 } },
                    tooltip: { y: { formatter: value => rupiah.format(value) } },
                    responsive: [{ breakpoint: 576, options: { chart: { height: 220 }, plotOptions: { bar: { columnWidth: '55%' } } } }]
                });
                chart.render().then(() => {
                    new MutationObserver(() => chart.updateOptions({
                        theme: { mode: darkMode() ? 'dark' : 'light' },
                        colors: [darkMode() ? '#8298ef' : '#435ebe'],
                        yaxis: { labels: { formatter: value => `Rp${shortRupiah.format(value)}`, style: { colors: darkMode() ? '#c5cee0' : '#566176' } } },
                        grid: { borderColor: darkMode() ? '#42445a' : '#e5eaf1' }
                    })).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
                }).catch(showDataTable);
            });
        </script>
    @endpush
@endif
