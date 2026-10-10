<div data-sales-report data-sales-charts="{{ base64_encode(json_encode($chartData, JSON_THROW_ON_ERROR)) }}">
    <form wire:submit.prevent="applyFilters" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="cashier-report-from">Dari tanggal</label>
                    <input id="cashier-report-from" type="date" wire:model="from" class="form-control">
                    @error('from')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="cashier-report-to">Sampai tanggal</label>
                    <input id="cashier-report-to" type="date" wire:model="to" class="form-control">
                    @error('to')<small class="text-danger">{{ $message }}</small>@enderror
                </div>
                <div class="col-12 col-sm-7 col-lg-3">
                    <label class="form-label" for="cashier-report-sort">Urutan transaksi</label>
                    <select id="cashier-report-sort" wire:model="sortOrder" class="form-select">
                        <option value="desc">Terbaru</option>
                        <option value="asc">Terlama</option>
                    </select>
                </div>
                <div class="col-12 col-sm-5 col-lg-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Terapkan</button>
                </div>
            </div>
        </div>
    </form>

    <p class="small text-muted mb-3">Laporan ini hanya menampilkan transaksi POS milik Anda. Untuk koreksi atau penghapusan data penjualan, hubungi Finance.</p>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7">
            <section class="card h-100" aria-labelledby="cashier-sales-trend-title">
                <div class="card-header"><h2 id="cashier-sales-trend-title" class="card-title mb-0">Tren penjualan harian</h2></div>
                <div class="card-body"><div id="sales-chart-daily" class="sales-report-chart" role="img" aria-label="Grafik tren penjualan harian"></div></div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card h-100" aria-labelledby="cashier-payment-mix-title">
                <div class="card-header"><h2 id="cashier-payment-mix-title" class="card-title mb-0">Tunai dan QRIS</h2></div>
                <div class="card-body"><div id="sales-chart-methods" class="sales-report-chart" role="img" aria-label="Grafik perbandingan pembayaran tunai dan QRIS"></div></div>
            </section>
        </div>
        <div class="col-12">
            <section class="card" aria-labelledby="cashier-best-sellers-chart-title">
                <div class="card-header"><h2 id="cashier-best-sellers-chart-title" class="card-title mb-0">Menu terlaris berdasarkan jumlah terjual</h2></div>
                <div class="card-body"><div id="sales-chart-products" class="sales-report-chart" role="img" aria-label="Grafik jumlah menu terlaris yang terjual"></div></div>
            </section>
        </div>
        <div class="col-12">
            <section class="card" aria-labelledby="cashier-hourly-sales-title">
                <div class="card-header"><h2 id="cashier-hourly-sales-title" class="card-title mb-0">Jam ramai dan sepi pembeli</h2></div>
                <div class="card-body">
                    <p class="small text-muted">Grafik menunjukkan jumlah transaksi pada tiap jam selama rentang tanggal terpilih. Jam tanpa transaksi ditampilkan sebagai 0.</p>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <div class="rounded border p-3 h-100">
                                <span class="dashboard-metric-label">Jam paling ramai</span>
                                @if($busiestHour)
                                    <p class="h5 mb-1">{{ $busiestHour['label'] }}–{{ sprintf('%02d.59', $busiestHour['hour']) }}</p>
                                    <span class="small text-muted">{{ \App\Support\DisplayNumber::format($busiestHour['transactions'], 0, ',', '.') }} transaksi pada rentang ini</span>
                                @else
                                    <p class="h5 mb-0">Belum ada transaksi</p>
                                @endif
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="rounded border p-3 h-100">
                                <span class="dashboard-metric-label">Jam paling sepi dengan transaksi</span>
                                @if($quietestHour)
                                    <p class="h5 mb-1">{{ $quietestHour['label'] }}–{{ sprintf('%02d.59', $quietestHour['hour']) }}</p>
                                    <span class="small text-muted">{{ \App\Support\DisplayNumber::format($quietestHour['transactions'], 0, ',', '.') }} transaksi pada rentang ini</span>
                                @else
                                    <p class="h5 mb-0">Belum ada transaksi</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div id="sales-chart-hours" class="sales-report-chart" role="img" aria-label="Grafik jumlah transaksi per jam dari pukul 00 sampai 23"></div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Total penjualan</span><h2 class="h4 mb-0">Rp {{ \App\Support\DisplayNumber::format($totalSales, 0, ',', '.') }}</h2></div></section></div>
        <div class="col-12 col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Jumlah transaksi</span><h2 class="h4 mb-0">{{ \App\Support\DisplayNumber::format($totalTransactions, 0, ',', '.') }}</h2></div></section></div>
        <div class="col-12 col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">Tunai diterima</span><h2 class="h4 mb-0">Rp {{ \App\Support\DisplayNumber::format($cashSales, 0, ',', '.') }}</h2></div></section></div>
        <div class="col-12 col-sm-6 col-xl-3"><section class="card h-100"><div class="card-body"><span class="dashboard-metric-label">QRIS diterima</span><h2 class="h4 mb-0">Rp {{ \App\Support\DisplayNumber::format($qrisSales, 0, ',', '.') }}</h2><small class="text-muted">Pembayaran QRIS yang dicatat manual.</small></div></section></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7">
            <section class="card h-100" aria-labelledby="cashier-best-sellers-title">
                <div class="card-header"><h2 id="cashier-best-sellers-title" class="card-title mb-0">Menu paling laku</h2></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Menu</th><th class="text-end">Terjual</th><th class="text-end">Transaksi</th><th class="text-end">Nilai penjualan</th></tr></thead>
                    <tbody>
                        @forelse($productSales as $product)
                            <tr><td class="fw-semibold">{{ $product->product_name }}</td><td class="text-end">{{ \App\Support\DisplayNumber::format($product->quantity, 3, ',', '.') }}</td><td class="text-end">{{ \App\Support\DisplayNumber::format($product->transactions, 0, ',', '.') }}</td><td class="text-end">Rp {{ \App\Support\DisplayNumber::format($product->total, 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada penjualan pada rentang tanggal ini.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card h-100" aria-labelledby="cashier-daily-sales-title">
                <div class="card-header"><h2 id="cashier-daily-sales-title" class="card-title mb-0">Penjualan per hari</h2></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Tanggal</th><th class="text-end">Transaksi</th><th class="text-end">Penjualan</th></tr></thead>
                    <tbody>
                        @forelse($dailySales as $day)
                            <tr><td>{{ \Illuminate\Support\Carbon::parse($day->day)->format('d/m/Y') }}</td><td class="text-end">{{ \App\Support\DisplayNumber::format($day->transactions, 0, ',', '.') }}</td><td class="text-end">Rp {{ \App\Support\DisplayNumber::format($day->total, 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">Belum ada penjualan pada rentang tanggal ini.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>
    </div>

    <section class="card" aria-labelledby="cashier-transactions-title">
        <div class="card-header"><h2 id="cashier-transactions-title" class="card-title mb-0">Daftar transaksi</h2><small class="text-muted">Rata-rata transaksi Rp {{ \App\Support\DisplayNumber::format($averageTicket, 0, ',', '.') }}</small></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nomor transaksi</th><th>Pembayaran</th><th>Status</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><a href="{{ route('transactions.show', $row) }}" class="fw-semibold">{{ $row->number }}</a><small class="d-block text-muted">{{ $row->occurred_at?->format('d/m/Y H:i') }}</small></td>
                        <td>{{ $row->tenders->pluck('method')->unique()->map(fn ($method) => $method === 'qris' ? 'QRIS' : ($method === 'cash' ? 'Tunai' : str($method)->headline()))->join(', ') ?: 'Belum ada pembayaran' }}</td>
                        <td><span class="badge {{ $row->payment_status === 'paid' ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ str($row->payment_status)->headline() }}</span></td>
                        <td class="text-end fw-semibold">Rp {{ \App\Support\DisplayNumber::format($row->grand_total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-5">Tidak ada transaksi pada rentang tanggal ini.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    </section>
</div>

@push('scripts')
    <script src="{{ asset('assets/vendor/apexcharts.min.js') }}" defer></script>
    <script src="{{ asset('assets/sales-report-charts.js') }}?v={{ filemtime(public_path('assets/sales-report-charts.js')) }}" defer></script>
@endpush
