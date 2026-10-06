<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <p class="text-muted mb-0">Kelola katalog, harga, pajak, dan status penjualan produk.</p>
        </div>
        @can('products-create')
            <button type="button" wire:click="openCreate" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Produk baru</button>
        @endcan
    </div>

    @if (session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="card">
        <div class="card-header"><div class="row g-2 align-items-center">
            <div class="col"><h5 class="card-title mb-0">Daftar produk</h5></div>
            <div class="col-12 col-md-5"><input type="search" wire:model.live.debounce.350ms="search" class="form-control" placeholder="Cari nama, SKU, atau barcode" aria-label="Cari produk"></div>
        </div></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Produk</th><th>Kategori</th><th>Harga jual</th><th>Stok gudang aktif</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        @php($warehouseStock = $product->warehouseStocks->first())
                        <tr>
                            <td><div class="fw-semibold">{{ $product->title }} @if($product->is_semi_finished)<span class="badge bg-light-primary text-primary">Setengah jadi</span>@endif</div><div class="small text-muted">{{ $product->sku }}@if($product->barcode) · {{ $product->barcode }}@endif</div></td>
                            <td>{{ $product->category?->name ?? 'Tanpa kategori' }}</td>
                            <td>Rp {{ number_format((float) $product->sell_price, 0, ',', '.') }}<div class="small text-muted">per {{ $product->unit?->symbol }}</div></td>
                            <td>@if($warehouseStock){{ number_format((float) $warehouseStock->stock, 3, ',', '.') }}@else<span class="text-muted">—</span>@endif</td>
                            <td><span class="badge {{ $product->is_active ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end text-nowrap">
                                @can('products-edit')<button type="button" wire:click="edit({{ $product->id }})" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $product->title }}"><i class="bi bi-pencil"></i></button>@endcan
                                @if($product->is_active)@can('products-delete')<button type="button" wire:click="deactivate({{ $product->id }})" wire:confirm="Nonaktifkan produk {{ $product->title }}? Riwayat dan stoknya tetap tersimpan." class="btn btn-sm btn-outline-danger" aria-label="Nonaktifkan {{ $product->title }}"><i class="bi bi-slash-circle"></i></button>@endcan @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5"><i class="bi bi-box-seam fs-2 text-muted"></i><p class="mt-2 mb-1">Belum ada produk yang cocok.</p><span class="text-muted small">Buat produk baru atau ubah kata pencarian.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="card-footer">{{ $products->links() }}</div>@endif
    </div>

    @if($showForm)
        <div class="modal-backdrop show" style="z-index:1040" wire:click="closeForm"></div>
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="product-form-title" style="z-index:1050" wire:keydown.escape="closeForm">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                <form wire:submit="save">
                    <div class="modal-header"><h5 class="modal-title" id="product-form-title">{{ $productId ? 'Ubah produk' : 'Tambah produk' }}</h5><button type="button" class="btn-close" wire:click="closeForm" aria-label="Tutup"></button></div>
                    <div class="modal-body"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="product-title">Nama produk</label><input id="product-title" wire:model="title" class="form-control" required>@error('title')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label class="form-label" for="product-sku">SKU</label><input id="product-sku" wire:model="sku" class="form-control" required>@error('sku')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><label class="form-label" for="product-barcode">Barcode</label><input id="product-barcode" wire:model="barcode" class="form-control" inputmode="numeric"></div>
                        <div class="col-md-6"><label class="form-label" for="product-category">Kategori</label><select id="product-category" wire:model="categoryId" class="form-select"><option value="">Tanpa kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>@error('categoryId')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="product-unit">Satuan dasar</label><select id="product-unit" wire:model="unitId" class="form-select" required><option value="">Pilih satuan</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select>@error('unitId')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="product-buy-price">Harga beli (Rp)</label><input id="product-buy-price" type="number" inputmode="decimal" min="0" step="0.01" wire:model="buyPrice" class="form-control" required>@error('buyPrice')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="product-sell-price">Harga jual (Rp)</label><input id="product-sell-price" type="number" inputmode="decimal" min="0" step="0.01" wire:model="sellPrice" class="form-control" required>@error('sellPrice')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label class="form-label" for="product-tax">Pajak (%)</label><input id="product-tax" type="number" inputmode="decimal" min="0" max="100" step="0.0001" wire:model="taxRate" class="form-control" placeholder="Ikuti pengaturan default">@error('taxRate')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="product-min-stock">Stok minimum</label><input id="product-min-stock" type="number" inputmode="decimal" min="0" step="0.001" wire:model="minStock" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label" for="product-reorder">Titik pemesanan ulang</label><input id="product-reorder" type="number" inputmode="decimal" min="0" step="0.001" wire:model="reorderPoint" class="form-control" required></div>
                        <div class="col-12"><div class="d-flex justify-content-between align-items-center"><div><strong>Satuan alternatif</strong><div class="small text-muted">Konversi dan harga jual khusus, bila diperlukan.</div></div><button type="button" wire:click="addAlternateUnit" class="btn btn-sm btn-outline-primary">Tambah satuan</button></div></div>
                        @foreach($alternateUnits as $index => $alternate)
                            <div class="col-12"><div class="row g-2 align-items-end border rounded p-2 mx-0"><div class="col-12 col-md-3"><label class="form-label" for="alt-unit-{{ $index }}">Satuan</label><select id="alt-unit-{{ $index }}" wire:model="alternateUnits.{{ $index }}.unit_id" class="form-select"><option value="">Pilih</option>@foreach($units->where('id', '!=', $unitId) as $unit)<option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select>@error("alternateUnits.$index.unit_id")<small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-6 col-md-2"><label class="form-label" for="alt-factor-{{ $index }}">Faktor ke satuan dasar</label><input id="alt-factor-{{ $index }}" type="number" inputmode="decimal" min="0.00000001" step="0.00000001" wire:model="alternateUnits.{{ $index }}.conversion_factor" class="form-control"></div><div class="col-6 col-md-2"><label class="form-label" for="alt-price-{{ $index }}">Harga jual (Rp)</label><input id="alt-price-{{ $index }}" type="number" inputmode="decimal" min="0" step="0.01" wire:model="alternateUnits.{{ $index }}.sell_price" class="form-control" placeholder="Otomatis"></div><div class="col-10 col-md-4"><label class="form-label" for="alt-barcode-{{ $index }}">Barcode satuan</label><input id="alt-barcode-{{ $index }}" wire:model="alternateUnits.{{ $index }}.barcode" class="form-control">@error("alternateUnits.$index.barcode")<small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-2 col-md-1"><button type="button" wire:click="removeAlternateUnit({{ $index }})" class="btn btn-outline-danger" aria-label="Hapus satuan"><i class="bi bi-trash"></i></button></div></div></div>
                        @endforeach
                        @error('alternateUnits')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                        <div class="col-12"><label class="form-label" for="product-description">Deskripsi</label><textarea id="product-description" wire:model="description" rows="3" class="form-control"></textarea></div>
                        <div class="col-12"><label class="form-label" for="product-image">Gambar produk</label><input id="product-image" type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="form-control"><div wire:loading wire:target="image" class="small text-muted mt-1">Mengunggah…</div>@error('image')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-12 d-flex flex-wrap gap-3">
                            <label class="form-check"><input type="checkbox" wire:model="taxInclusive" class="form-check-input"><span class="form-check-label">Harga sudah termasuk pajak</span></label>
                            <label class="form-check"><input type="checkbox" wire:model="isActive" class="form-check-input"><span class="form-check-label">Produk aktif</span></label>
                            <label class="form-check"><input type="checkbox" wire:model="isSellable" class="form-check-input"><span class="form-check-label">Dapat dijual</span></label>
                            <label class="form-check"><input type="checkbox" wire:model.live="isSemiFinished" class="form-check-input"><span class="form-check-label">Produk setengah jadi untuk dirakit outlet</span></label>
                            <label class="form-check"><input type="checkbox" wire:model="allowSingleUnitSale" class="form-check-input"><span class="form-check-label">Izinkan penjualan satuan dasar (contoh: Chiquro)</span></label>
                            <label class="form-check"><input type="checkbox" wire:model="trackStock" class="form-check-input"><span class="form-check-label">Lacak stok</span></label>
                            <label class="form-check"><input type="checkbox" wire:model="trackBatches" class="form-check-input"><span class="form-check-label">Lacak batch/kedaluwarsa</span></label>
                        </div>
                    </div></div>
                    <div class="modal-footer"><button type="button" wire:click="closeForm" class="btn btn-light-secondary">Batal</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Simpan</button></div>
                </form>
            </div></div>
        </div>
    @endif
</div>
