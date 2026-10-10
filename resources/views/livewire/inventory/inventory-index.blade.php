<div>
    @unless($isSharedWarehouse)<div class="alert alert-light-primary">Gudang outlet hanya menerima stok produk setengah jadi dari transfer Gudang Bersama. Stok masuk, stok awal, dan impor hanya tersedia di Gudang Bersama.</div>@endunless
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <p class="text-muted mb-0">Kelola saldo produk setengah jadi yang diproduksi Gudang Bersama dan dikirim ke outlet.</p>
        <div class="d-flex gap-2 align-items-center"><label class="form-label mb-0" for="inventory-warehouse">Gudang</label><select id="inventory-warehouse" wire:change="selectWarehouse($event.target.value)" class="form-select form-select-sm" style="min-width:220px">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int)$warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
    </div>
    @error('warehouseId')<div class="alert alert-warning">{{ $message }}</div>@enderror
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->has('file'))<div class="alert alert-danger">{{ $errors->first('file') }}</div>@endif
    <div class="card mb-4"><div class="card-body"><div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
        <div><h6 class="mb-1">Data stok setengah jadi</h6><small class="text-muted">Export saldo atau impor stok awal untuk produk setengah jadi yang belum memiliki histori stok.</small></div>
        <div class="d-flex flex-wrap gap-2">
            @if($warehouseId)<a class="btn btn-outline-primary" href="{{ route('inventory.export', ['warehouse_id' => $warehouseId]) }}"><i class="bi bi-download me-1"></i>Export stok</a>@endif
            @can('inventory-opening') @if($isSharedWarehouse)
                @if($warehouseId)<a class="btn btn-outline-secondary" href="{{ route('inventory.opening-stock.template', ['warehouse_id' => $warehouseId]) }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Unduh template</a>@endif
                <form method="POST" action="{{ route('inventory.opening-stock.import') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
                    @csrf<input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
                    <input class="form-control" type="file" name="file" accept=".xlsx" required aria-label="File Excel stok awal">
                    <button class="btn btn-primary" type="submit" @disabled(!$warehouseId)><i class="bi bi-upload me-1"></i>Impor stok awal</button>
                </form>
            @endif @endcan
        </div>
    </div></div></div>
    <div class="card">
        <div class="card-header"><div class="row align-items-center g-2"><div class="col"><h5 class="card-title mb-0">Stok menu setengah jadi per gudang</h5></div><div class="col-12 col-md-5"><input type="search" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari nama, SKU, barcode" aria-label="Cari stok setengah jadi"></div></div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Produk</th><th>Gudang</th><th>Stok tersedia</th><th>Reorder point</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($stocks as $row)
                <tr><td><strong>{{ $row->product->title }}</strong><div class="small text-muted">{{ $row->product->sku }} · {{ $row->product->category?->name ?? 'Tanpa kategori' }}</div></td><td>{{ $row->warehouse->name }}</td><td>{{ \App\Support\DisplayNumber::format((float)$row->stock - (float)$row->reserved_stock, 3, ',', '.') }} {{ $row->product->unit?->symbol }}</td><td>{{ \App\Support\DisplayNumber::format((float)$row->reorder_point, 3, ',', '.') }}</td><td>@if((float)$row->stock <= (float)$row->reorder_point)<span class="badge bg-light-warning text-warning">Perlu pemesanan</span>@else<span class="badge bg-light-success text-success">Tersedia</span>@endif</td><td class="text-end">@canany(['inventory-opening','inventory-adjust'])<button type="button" wire:click="openAdjustment({{ $row->product_id }})" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i>Mutasi</button>@endcanany</td></tr>
            @empty<tr><td colspan="6" class="text-center py-5 text-muted">Belum ada saldo produk pada gudang ini.</td></tr>@endforelse
        </tbody></table></div>
        @if($stocks->hasPages())<div class="card-footer">{{ $stocks->links() }}</div>@endif
    </div>

    <div class="card mt-4"><div class="card-header"><div class="row align-items-center g-2"><div class="col"><h5 class="card-title mb-0">Riwayat mutasi terbaru</h5></div><div class="col-12 col-md-5"><input type="search" wire:model.live.debounce.300ms="ledgerSearch" class="form-control form-control-sm" placeholder="Filter riwayat produk" aria-label="Filter riwayat"></div></div></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Waktu</th><th>Produk</th><th>Tipe</th><th>Perubahan</th><th>Sebelum → sesudah</th><th>Alasan</th></tr></thead><tbody>
            @forelse($ledger as $entry)<tr><td>{{ $entry->created_at?->format('d/m/Y H:i') }}</td><td>{{ $entry->product?->title }}<div class="small text-muted">{{ $entry->warehouse?->name }}</div></td><td>{{ str($entry->type)->replace('_', ' ')->headline() }}</td><td class="{{ (float)$entry->quantity_delta < 0 ? 'text-danger' : 'text-success' }}">{{ (float)$entry->quantity_delta > 0 ? '+' : '' }}{{ \App\Support\DisplayNumber::format((float)$entry->quantity_delta, 3, ',', '.') }}</td><td>{{ \App\Support\DisplayNumber::format((float)$entry->quantity_before, 3, ',', '.') }} → {{ \App\Support\DisplayNumber::format((float)$entry->quantity_after, 3, ',', '.') }}</td><td>{{ data_get($entry->metadata, 'reason', '—') }}</td></tr>
            @empty<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada mutasi yang tercatat.</td></tr>@endforelse
        </tbody></table></div>
    </div>

    @if($showForm)<div class="modal-backdrop show" style="z-index:1040" wire:click="closeForm"></div><div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title" style="z-index:1050" wire:keydown.escape="closeForm"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form wire:submit="adjust"><div class="modal-header"><h5 class="modal-title" id="stock-modal-title">Penyesuaian stok</h5><button type="button" class="btn-close" wire:click="closeForm" aria-label="Tutup"></button></div><div class="modal-body">
        <div class="mb-3"><label for="stock-direction" class="form-label">Jenis mutasi</label><select id="stock-direction" wire:model="direction" class="form-select">@if($isSharedWarehouse)<option value="in">Stok masuk</option>@endif<option value="out">Stok keluar</option></select></div>
        <div class="mb-3"><label for="stock-quantity" class="form-label">Jumlah</label><input id="stock-quantity" type="number" inputmode="decimal" min="0.001" step="0.001" wire:model="quantity" class="form-control" required>@error('quantity')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label for="stock-reason" class="form-label">Alasan</label><textarea id="stock-reason" wire:model="reason" class="form-control" rows="3" maxlength="500" required></textarea>@error('reason')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        @if($direction === 'in')<div class="mb-3"><label for="stock-batch" class="form-label">Nomor batch <span class="text-muted">(wajib untuk produk dengan pelacakan batch)</span></label><input id="stock-batch" wire:model="batchNumber" class="form-control" maxlength="80">@error('batchNumber')<div class="text-danger small">{{ $message }}</div>@enderror</div><div class="mb-3"><label for="stock-expiry" class="form-label">Tanggal kedaluwarsa</label><input id="stock-expiry" type="date" wire:model="expiresAt" min="{{ today()->addDay()->toDateString() }}" class="form-control"></div>@endif
        </div><div class="modal-footer"><button type="button" wire:click="closeForm" class="btn btn-light-secondary">Batal</button>@can('inventory-opening')<button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan mutasi</button>@else<button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan mutasi</button>@endcan</div></form></div></div></div>@endif
</div>
