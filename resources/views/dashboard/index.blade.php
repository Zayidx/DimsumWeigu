@extends('layouts.dashboard')

@section('page-title', 'Ringkasan')
@section('subtitle', 'Kondisi penjualan dan operasional · '.$scopeLabel)

@section('content')
    @if (session('status'))<div class="alert alert-light-success alert-dismissible show fade" role="status">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>@endif
    @php($trendMaximum = max($salesTrend->pluck('total')->all() ?: [0]))
    @if ($outlets->count() > 1 || $availableWarehouses->count() > 1)
        <form method="get" action="{{ route('dashboard') }}" class="dashboard-filter card mb-3"><div class="card-body d-flex flex-wrap align-items-end gap-2">
            @if ($outlets->count() > 1)<div class="flex-grow-1"><label for="dashboard-outlet" class="form-label mb-1">Outlet</label><select id="dashboard-outlet" name="outlet" class="form-select"><option value="">Semua outlet</option>@foreach ($outlets as $option)<option value="{{ $option->id }}" @selected($selectedOutlet?->id === $option->id)>{{ $option->name }}</option>@endforeach</select></div>@endif
            <div class="flex-grow-1"><label for="dashboard-warehouse" class="form-label mb-1">Gudang</label><select id="dashboard-warehouse" name="warehouse" class="form-select"><option value="">Semua gudang</option>@foreach ($availableWarehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($selectedWarehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
            <input type="hidden" name="period" value="{{ $trendDays }}"><button class="btn btn-outline-primary" type="submit">Terapkan lokasi</button>
        </div></form>
    @endif
    <p class="dashboard-context text-muted small mb-3">Angka dan antrean untuk <strong>{{ $selectedWarehouse?->name ?? $scopeLabel }}</strong>. Penjualan menghitung transaksi selesai, termasuk yang belum lunas.</p>
    <div class="row g-3 mb-4 dashboard-metrics">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Penjualan hari ini</h2><p class="dashboard-metric-value mb-0">Rp{{ \App\Support\DisplayNumber::format($todaySales, 0, ',', '.') }}</p><small class="text-muted">Nilai transaksi selesai, belum tentu diterima tunai.</small></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Transaksi hari ini</h2><p class="dashboard-metric-value mb-0">{{ \App\Support\DisplayNumber::format($todayTransactions) }}</p><small class="text-muted">Transaksi selesai sejak pukul 00.00.</small></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Shift terbuka</h2><p class="dashboard-metric-value mb-0">{{ \App\Support\DisplayNumber::format($openShifts) }}</p><small class="text-muted">Semua kasir pada lokasi terpilih.</small></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2 class="dashboard-metric-label">Stok perlu dicek</h2><p class="dashboard-metric-value mb-0">{{ \App\Support\DisplayNumber::format($lowStockCount) }}</p><small class="text-muted">Saldo stok mencapai batas pesan ulang.</small></div></div></div>
    </div>

    <div class="card dashboard-trend-card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><h2 class="card-title mb-1">Tren penjualan</h2><p class="text-muted small mb-0">{{ $trendDays === 30 ? 'Per 6 hari' : 'Per hari' }} · {{ $trendStart->translatedFormat('d M') }} – {{ today()->translatedFormat('d M Y') }}</p></div>
            <nav class="dashboard-periods" aria-label="Periode tren penjualan">
                <a href="{{ route('dashboard', array_filter(['period' => 7, 'warehouse' => $selectedWarehouse?->id, 'outlet' => $selectedOutlet?->id])) }}" class="btn btn-sm {{ $trendDays === 7 ? 'btn-primary' : 'btn-outline-primary' }}" @if($trendDays === 7) aria-current="true" @endif>7 hari</a>
                <a href="{{ route('dashboard', array_filter(['period' => 30, 'warehouse' => $selectedWarehouse?->id, 'outlet' => $selectedOutlet?->id])) }}" class="btn btn-sm {{ $trendDays === 30 ? 'btn-primary' : 'btn-outline-primary' }}" @if($trendDays === 30) aria-current="true" @endif>30 hari</a>
            </nav>
        </div>
        <div class="card-body">
            <div class="dashboard-trend-summary">
                <div><span class="text-muted small">Total periode ini</span><strong>Rp{{ \App\Support\DisplayNumber::format($trendTotal, 0, ',', '.') }}</strong></div>
                @if ($previousTrendTotal > 0)
                    <span class="dashboard-trend-change {{ $trendChangePercent > 0 ? 'is-positive' : ($trendChangePercent < 0 ? 'is-negative' : '') }}">{{ $trendChangePercent > 0 ? '+' : '' }}{{ \App\Support\DisplayNumber::format($trendChangePercent, 1, ',', '.') }}% dari {{ $trendDays }} hari sebelumnya</span>
                @else
                    <span class="text-muted small">Periode sebelumnya Rp0</span>
                @endif
            </div>
            @if ($trendMaximum > 0)
                <div class="dashboard-chart-controls mb-2"><span class="text-muted small">{{ auth()->user()->can('reports-access') ? 'Pilih batang atau tanggal untuk membuka laporan.' : 'Rincian penjualan per tanggal.' }}</span><div class="btn-group btn-group-sm" role="group" aria-label="Tampilan tren"><button type="button" class="btn btn-primary" id="dashboard-show-chart" aria-pressed="true">Grafik</button><button type="button" class="btn btn-outline-primary" id="dashboard-show-table" aria-pressed="false">Tabel</button></div></div>
                <div id="dashboard-sales-trend" class="dashboard-sales-chart" aria-hidden="true"></div>
                <div class="table-responsive dashboard-sales-table visually-hidden" id="dashboard-sales-table"><table id="dashboard-sales-data" class="table table-striped align-middle mb-0"><caption>Penjualan {{ $trendDays }} hari terakhir, {{ $trendDays === 30 ? 'dikelompokkan per enam hari' : 'per hari' }}</caption><thead><tr><th scope="col">Tanggal</th><th scope="col" class="text-end">Penjualan</th></tr></thead><tbody>@foreach ($salesTrend as $day)<tr><th scope="row">@can('reports-access')<a href="{{ route('reports.sales', array_filter(['from' => $day['from'], 'to' => $day['to'], 'warehouse' => $selectedWarehouse?->id, 'outlet' => $selectedOutlet?->id])) }}">{{ $day['date'] }}</a>@else{{ $day['date'] }}@endcan</th><td class="text-end">Rp{{ \App\Support\DisplayNumber::format($day['total'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
            @else
                <div class="dashboard-chart-empty"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Belum ada penjualan dalam {{ $trendDays }} hari terakhir.</span></div>
            @endif
        </div>
    </div>

    @if ($quickActions->isNotEmpty() || $tasks->isNotEmpty() || auth()->user()->can('notifications-access'))
        @php($workPanelCount = (int) $quickActions->isNotEmpty() + (int) $tasks->isNotEmpty() + (int) auth()->user()->can('notifications-access'))
        @php($workPanelColumn = $workPanelCount === 3 ? 'col-xl-4' : ($workPanelCount === 2 ? 'col-xl-6' : 'col-xl-12'))
        <div class="row g-3 mb-4">
            @if ($quickActions->isNotEmpty())<div class="col-12 {{ $workPanelColumn }}"><section class="card h-100" aria-labelledby="dashboard-actions-title"><div class="card-header"><h2 id="dashboard-actions-title" class="card-title mb-0">Aksi cepat</h2></div><div class="card-body d-grid gap-2">@foreach ($quickActions as $action)<a class="btn btn-outline-primary justify-content-start" href="{{ $action['url'] }}"><i class="bi {{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}</a>@endforeach</div></section></div>@endif
            @if ($tasks->isNotEmpty())<div class="col-12 {{ $workPanelColumn }}"><section class="card h-100" aria-labelledby="dashboard-tasks-title"><div class="card-header"><h2 id="dashboard-tasks-title" class="card-title mb-0">Perlu ditindaklanjuti</h2></div><div class="list-group list-group-flush">@foreach ($tasks as $task)<a class="list-group-item list-group-item-action dashboard-task" href="{{ $task['url'] }}"><span><strong>{{ $task['label'] }}</strong><small class="d-block text-muted">Tertua {{ \Illuminate\Support\Carbon::parse($task['oldest'])->diffForHumans() }}</small></span><span class="badge bg-light-warning text-warning">{{ $task['count'] }}</span></a>@endforeach</div></section></div>@endif
            @can('notifications-access')<div class="col-12 {{ $workPanelColumn }}"><section class="card h-100" aria-labelledby="dashboard-notifications-title"><div class="card-header d-flex align-items-center justify-content-between gap-2"><h2 id="dashboard-notifications-title" class="card-title mb-0">Notifikasi belum dibaca</h2><a class="small" href="{{ route('notifications.index') }}">Lihat semua</a></div><div class="list-group list-group-flush">@forelse ($notifications as $notice)<a class="list-group-item list-group-item-action" href="{{ $notice->destination(auth()->user()) }}"><span class="badge {{ str_ends_with($notice->type, 'overdue') ? 'bg-light-danger text-danger' : 'bg-light-warning text-warning' }} mb-1">{{ str_ends_with($notice->type, 'overdue') ? 'Lewat jatuh tempo' : 'Perlu perhatian' }}</span><strong class="d-block">{{ $notice->title }}</strong><small class="text-muted">{{ $notice->created_at?->diffForHumans() }} · {{ str($notice->type)->headline() }}</small></a>@empty<div class="list-group-item text-muted">Tidak ada notifikasi baru.</div>@endforelse</div></section></div>@endcan
        </div>
    @endif

    @can('reports-access')
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-7"><section class="card h-100" aria-labelledby="dashboard-products-title"><div class="card-header"><h2 id="dashboard-products-title" class="card-title mb-0">Produk terlaris · {{ $trendDays }} hari</h2></div><div class="card-body">@forelse ($topProducts as $product)<div class="dashboard-ranking"><span>{{ $loop->iteration }}. {{ $product->product_name }}</span><strong>{{ \App\Support\DisplayNumber::format((float) $product->quantity, 3, ',', '.') }} {{ $product->unit_name }}</strong></div>@empty<p class="text-muted mb-0">Belum ada produk terjual pada periode ini.</p>@endforelse<small class="text-muted d-block mt-2">Jumlah dari baris transaksi selesai, dipisah menurut satuan.</small></div></section></div>
            <div class="col-12 col-lg-5"><section class="card h-100" aria-labelledby="dashboard-hours-title"><div class="card-header"><h2 id="dashboard-hours-title" class="card-title mb-0">Jam ramai · {{ $trendDays }} hari</h2></div><div class="card-body">@forelse ($busyHours as $hour)<div class="dashboard-ranking"><span>{{ str_pad((string) $hour->sale_hour, 2, '0', STR_PAD_LEFT) }}.00–{{ str_pad((string) (((int) $hour->sale_hour + 1) % 24), 2, '0', STR_PAD_LEFT) }}.00</span><strong>{{ \App\Support\DisplayNumber::format($hour->transactions) }} transaksi</strong></div>@empty<p class="text-muted mb-0">Belum ada transaksi selesai pada periode ini.</p>@endforelse<small class="text-muted d-block mt-2">Berdasarkan waktu transaksi selesai.</small></div></section></div>
        </div>
    @endcan

    <div class="row g-4">
        @can('transactions-access')<div class="col-12 col-xl-7"><div class="card"><div class="card-header d-flex align-items-center justify-content-between"><h4 class="card-title mb-0">Transaksi terbaru</h4><a href="{{ Route::has('transactions.index') ? route('transactions.index') : '#' }}" class="btn btn-sm btn-outline-primary" @if (! Route::has('transactions.index')) aria-disabled="true" @endif>Lihat transaksi</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th scope="col">Nomor</th><th scope="col">Pelanggan</th><th scope="col">Pembayaran</th><th scope="col" class="text-end">Total</th></tr></thead><tbody>
                @forelse ($recentTransactions as $transaction)
                    @php($paymentLabel = ['paid' => 'Lunas', 'partial' => 'Sebagian', 'unpaid' => 'Belum lunas'][$transaction->payment_status] ?? str($transaction->payment_status)->headline())
                    @php($paymentStyle = ['paid' => 'bg-light-success text-success', 'partial' => 'bg-light-warning text-warning', 'unpaid' => 'bg-light-secondary text-secondary'][$transaction->payment_status] ?? 'bg-light-secondary text-secondary')
                    <tr><td><a href="{{ route('transactions.show', $transaction) }}" class="fw-bold">{{ $transaction->number }}</a><div class="small text-muted">{{ $transaction->occurred_at?->format('d M Y, H:i') }}</div></td><td>{{ $transaction->customer?->name ?? 'Pelanggan umum' }}</td><td><span class="badge {{ $paymentStyle }}">{{ $paymentLabel }}</span></td><td class="text-end fw-semibold">Rp{{ \App\Support\DisplayNumber::format($transaction->grand_total, 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center py-5"><i class="bi bi-receipt text-muted fs-2"></i><p class="text-muted mt-2 mb-0">Belum ada transaksi di outlet ini.</p></td></tr>
                @endforelse
            </tbody></table></div></div></div></div>
        @endcan
        <div class="col-12 {{ auth()->user()->can('transactions-access') ? 'col-xl-5' : '' }}"><div class="card"><div class="card-header d-flex align-items-center justify-content-between"><h4 class="card-title mb-0">Perhatian stok</h4>@can('inventory-access') @if (Route::has('inventory.index'))<a href="{{ route('inventory.index') }}" class="small">Buka inventori</a>@endif @endcan</div>
            <div class="card-body"><div class="list-group list-group-flush">
                @forelse ($lowStock as $row)
                    <div class="list-group-item px-0 dashboard-stock-item">
                        <div><div class="fw-semibold">{{ $row->product->title }}</div><div class="small text-muted">{{ $row->product->sku }} · {{ $row->warehouse->name }}</div><div class="small mt-1">Saldo {{ \App\Support\DisplayNumber::format((float) $row->stock, 3, ',', '.') }} / pesan ulang {{ \App\Support\DisplayNumber::format((float) $row->reorder_point, 3, ',', '.') }}</div></div>
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
        <script src="{{ asset('assets/vendor/apexcharts.min.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const target = document.querySelector('#dashboard-sales-trend');
                if (!target) return;
                const table = document.querySelector('#dashboard-sales-table');
                const chartButton = document.querySelector('#dashboard-show-chart');
                const tableButton = document.querySelector('#dashboard-show-table');
                const setDisplay = (display) => {
                    const showingTable = display === 'table';
                    target.hidden = showingTable;
                    table.classList.toggle('visually-hidden', !showingTable);
                    chartButton.classList.toggle('btn-primary', !showingTable);
                    chartButton.classList.toggle('btn-outline-primary', showingTable);
                    tableButton.classList.toggle('btn-primary', showingTable);
                    tableButton.classList.toggle('btn-outline-primary', !showingTable);
                    chartButton.setAttribute('aria-pressed', String(!showingTable));
                    tableButton.setAttribute('aria-pressed', String(showingTable));
                };
                chartButton.addEventListener('click', () => setDisplay('chart'));
                tableButton.addEventListener('click', () => setDisplay('table'));
                const showDataTable = () => {
                    setDisplay('table');
                    chartButton.disabled = true;
                };
                if (typeof ApexCharts === 'undefined') { showDataTable(); return; }

                const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
                const shortRupiah = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
                const darkMode = () => document.documentElement.dataset.workspaceTheme === 'dark';
                const reportUrls = @js($salesTrend->map(fn ($day) => auth()->user()->can('reports-access') ? route('reports.sales', array_filter(['from' => $day['from'], 'to' => $day['to'], 'warehouse' => $selectedWarehouse?->id, 'outlet' => $selectedOutlet?->id])) : null)->values());
                const chart = new ApexCharts(target, {
                    chart: { type: 'bar', height: 260, background: 'transparent', toolbar: { show: false }, fontFamily: 'Nunito, sans-serif', animations: { enabled: false }, events: { dataPointSelection: (event, context, config) => { const url = reportUrls[config.dataPointIndex]; if (url) window.location.assign(url); } } },
                    series: [{ name: 'Penjualan', data: @js($salesTrend->pluck('total')->values()) }],
                    theme: { mode: darkMode() ? 'dark' : 'light' },
                    colors: [darkMode() ? '#f5f5f5' : '#171717'],
                    plotOptions: { bar: { borderRadius: 5, columnWidth: '42%' } },
                    dataLabels: { enabled: false },
                    xaxis: { categories: @js($salesTrend->pluck('label')->values()), axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { formatter: value => `Rp${shortRupiah.format(value)}`, style: { colors: darkMode() ? '#c4c4c4' : '#666666' } } },
                    grid: { borderColor: darkMode() ? '#404040' : '#dededc', strokeDashArray: 4, padding: { left: 8, right: 8 } },
                    tooltip: { y: { formatter: value => rupiah.format(value) } },
                    responsive: [{ breakpoint: 576, options: { chart: { height: 220 }, plotOptions: { bar: { columnWidth: '55%' } } } }]
                });
                chart.render().then(() => {
                    new MutationObserver(() => chart.updateOptions({
                        theme: { mode: darkMode() ? 'dark' : 'light' },
                        colors: [darkMode() ? '#f5f5f5' : '#171717'],
                        yaxis: { labels: { formatter: value => `Rp${shortRupiah.format(value)}`, style: { colors: darkMode() ? '#c4c4c4' : '#666666' } } },
                        grid: { borderColor: darkMode() ? '#404040' : '#dededc' }
                    })).observe(document.documentElement, { attributes: true, attributeFilter: ['data-workspace-theme'] });
                }).catch(showDataTable);
            });
        </script>
    @endpush
@endif
