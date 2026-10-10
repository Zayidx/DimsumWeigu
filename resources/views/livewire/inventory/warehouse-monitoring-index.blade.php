<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div>
            <p class="text-muted mb-1">Gudang Bersama · {{ $warehouse->name }}</p>
            <p class="text-muted mb-0">Pantau bahan untuk produksi dan stok setengah jadi yang akan dikirim ke outlet.</p>
        </div>
        <div class="w-100" style="max-width: 360px">
            <label class="visually-hidden" for="warehouse-monitor-search">Cari bahan atau produk</label>
            <input id="warehouse-monitor-search" type="search" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari nama atau kode">
        </div>
    </div>

    <section class="card mb-4" aria-labelledby="raw-materials-heading">
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <div><h2 id="raw-materials-heading" class="h5 card-title mb-1">Bahan mentah</h2><p class="small text-muted mb-0">Stok tersedia untuk produksi di Gudang Bersama.</p></div>
            <span class="badge bg-light-secondary text-dark">{{ $rawMaterials->count() }} bahan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th scope="col">Bahan</th><th scope="col">Stok tersedia</th><th scope="col">Batas minimum</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    @forelse($rawMaterials as $item)
                        <tr>
                            <td><strong>{{ $item->name }}</strong><div class="small text-muted">{{ $item->code }}</div></td>
                            <td>{{ \App\Support\DisplayNumber::format($item->available, 3, ',', '.') }} {{ $item->unit }}</td>
                            <td>{{ \App\Support\DisplayNumber::format((float) $item->reorder_point, 3, ',', '.') }} {{ $item->unit }}</td>
                            <td>@if($item->available <= (float) $item->reorder_point)<span class="badge bg-light-warning text-warning">Perlu diperiksa</span>@else<span class="badge bg-light-success text-success">Tersedia</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada bahan yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" aria-labelledby="semi-finished-heading">
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <div><h2 id="semi-finished-heading" class="h5 card-title mb-1">Menu setengah jadi</h2><p class="small text-muted mb-0">Termasuk Chiquro mentah, dimsum topping, lumpia isi, dan saus produksi gudang.</p></div>
            <span class="badge bg-light-secondary text-dark">{{ $semiFinished->count() }} produk</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th scope="col">Produk setengah jadi</th><th scope="col">Stok tersedia</th><th scope="col">Batas minimum</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    @forelse($semiFinished as $item)
                        <tr>
                            <td><strong>{{ $item->title }}</strong><div class="small text-muted">{{ $item->sku }}</div></td>
                            <td>{{ \App\Support\DisplayNumber::format($item->available, 3, ',', '.') }} {{ $item->unit }}</td>
                            <td>{{ \App\Support\DisplayNumber::format((float) ($item->reorder_point ?? 0), 3, ',', '.') }} {{ $item->unit }}</td>
                            <td>@if($item->available <= (float) ($item->reorder_point ?? 0))<span class="badge bg-light-warning text-warning">Perlu diproduksi</span>@else<span class="badge bg-light-success text-success">Tersedia</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada produk setengah jadi yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <p class="small text-muted mt-3 mb-0">Menu siap saji yang diracik atau digoreng di outlet tidak ditampilkan di inventori gudang.</p>
</div>
