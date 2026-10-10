<div>
    <div class="alert alert-light-info" role="note">
        Saran memakai penjualan POS selesai dan resep komponen aktif pada {{ $periodStart->format('d/m/Y') }} sampai {{ $periodEnd->format('d/m/Y') }}. Perhitungannya memperhitungkan stok outlet, transfer yang dikirim, permintaan produksi yang masih aktif, dan stok Gudang Bersama. Periksa resep serta jumlah fisik sebelum membuat proses kerja.
    </div>

    <section class="card mb-4">
        <div class="card-body"><div class="row g-3 align-items-end">
            <div class="col-12 col-md-5"><label for="replenishment-outlet" class="form-label">Outlet</label><select id="replenishment-outlet" wire:model.change="outletId" class="form-select"><option value="">Semua outlet</option>@foreach($outlets as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label for="replenishment-history" class="form-label">Riwayat penjualan</label><select id="replenishment-history" wire:model.change="lookbackDays" class="form-select"><option value="7">7 hari</option><option value="14">14 hari</option><option value="30">30 hari</option></select></div>
            <div class="col-6 col-md-3"><label for="replenishment-target" class="form-label">Target stok outlet</label><select id="replenishment-target" wire:model.change="targetDays" class="form-select"><option value="3">3 hari kebutuhan</option><option value="5">5 hari kebutuhan</option><option value="7">7 hari kebutuhan</option></select></div>
        </div></div>
    </section>

    @if(!$centralWarehouse)
        <div class="alert alert-light-warning" role="status">Gudang Bersama yang aktif belum ditemukan. Saran stok belum bisa dihitung.</div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><p class="mb-0 text-muted">Gudang sumber: <strong>{{ $centralWarehouse->name }}</strong></p><span class="small text-muted">Jumlah ditampilkan dalam satuan dasar produk.</span></div>
        <section aria-labelledby="replenishment-table-title">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 id="replenishment-table-title" class="card-title mb-0">Kebutuhan stok setengah jadi</h2><small class="text-muted">Perkiraan penjualan digunakan sebagai saran, bukan mutasi stok otomatis.</small></div><div class="d-flex flex-wrap gap-2">@can('transfers-access')<a href="{{ route('transfers.index') }}" class="btn btn-sm btn-outline-primary">Buka transfer stok</a>@endcan @can('production-orders-access')<a href="{{ route('production-orders.index') }}" class="btn btn-sm btn-outline-secondary">Buka produksi</a>@endcan</div></div>
            <div class="row g-3">
                @forelse($rows as $row)
                    <div class="col-12 col-xl-6" wire:key="replenishment-{{ $row['outlet_id'] }}-{{ $row['product_id'] }}">
                        <article class="card h-100">
                            <div class="card-header"><h3 class="h6 mb-1">{{ $row['product_name'] }}</h3><small class="text-muted">{{ $row['outlet_name'] }} · rata-rata {{ \App\Support\DisplayNumber::format($row['average_daily'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }} per hari</small></div>
                            <div class="card-body"><div class="row g-3">
                                <div class="col-6"><span class="dashboard-metric-label">Terpakai di periode</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['sales_quantity'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                                <div class="col-6"><span class="dashboard-metric-label">Target outlet</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['target_quantity'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                                <div class="col-6"><span class="dashboard-metric-label">Stok tersedia outlet</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['outlet_available'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                                <div class="col-6"><span class="dashboard-metric-label">Transfer berjalan</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['incoming_quantity'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                                <div class="col-6"><span class="dashboard-metric-label">Permintaan aktif</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['requested_quantity'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                                <div class="col-6"><span class="dashboard-metric-label">Tersedia di pusat</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['central_available'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                                <div class="col-6"><span class="dashboard-metric-label">Produksi berjalan</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['production_in_progress'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div>
                            </div></div>
                            <div class="card-footer"><div class="row g-3"><div class="col-6"><span class="dashboard-metric-label">Saran transfer</span><strong class="d-block">{{ \App\Support\DisplayNumber::format($row['suggested_transfer'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div><div class="col-6"><span class="dashboard-metric-label">Perlu produksi tambahan</span><strong class="d-block {{ $row['additional_production'] > 0 ? 'text-warning' : '' }}">{{ \App\Support\DisplayNumber::format($row['additional_production'] / 1000, 3, ',', '.') }} {{ $row['unit_symbol'] }}</strong></div></div></div>
                        </article>
                    </div>
                @empty
                    <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">Belum ada kebutuhan yang bisa dihitung. Pastikan outlet memiliki penjualan POS, resep menu aktif dengan komponen produk setengah jadi, dan saldo stok gudang.</div></div></div>
                @endforelse
            </div>
        </section>
    @endif
</div>
