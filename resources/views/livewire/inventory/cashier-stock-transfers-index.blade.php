<div>
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
        <div><h4 class="mb-1">Ambil &amp; kembalikan stok</h4><p class="text-muted mb-0">Stok berpindah langsung antara Gudang Bersama dan {{ $branch->name }} setelah dikonfirmasi.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-xl-7"><div class="card h-100"><div class="card-body">
            <form wire:submit="save" wire:confirm="Konfirmasi perpindahan stok? Saldo gudang dan outlet akan langsung diperbarui.">
                <fieldset class="mb-3"><legend class="form-label fw-semibold mb-2">Jenis perpindahan</legend>
                    <div class="d-flex flex-wrap gap-3"><label><input type="radio" wire:model.live="direction" value="pickup" class="form-check-input me-1">Ambil stok dari gudang</label><label><input type="radio" wire:model.live="direction" value="return" class="form-check-input me-1">Kembalikan stok ke gudang</label></div>
                </fieldset>
                <p class="small text-muted">{{ $direction === 'pickup' ? 'Sumber: Gudang Bersama · Tujuan: '.$branch->name : 'Sumber: '.$branch->name.' · Tujuan: Gudang Bersama' }}</p>
                @foreach($items as $index => $item)
                    <div class="row g-2 align-items-end mb-2" wire:key="cashier-stock-item-{{ $index }}">
                        <div class="col-12 col-sm-7"><label class="form-label" for="stock-product-{{ $index }}">Produk setengah jadi</label><select id="stock-product-{{ $index }}" wire:model="items.{{ $index }}.product_id" class="form-select" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->title }}</option>@endforeach</select></div>
                        <div class="col-8 col-sm-3"><label class="form-label" for="stock-quantity-{{ $index }}">Jumlah</label><input id="stock-quantity-{{ $index }}" type="number" inputmode="decimal" min="0.001" step="0.001" wire:model="items.{{ $index }}.quantity" class="form-control" required></div>
                        <div class="col-4 col-sm-2"><button type="button" class="btn btn-outline-danger w-100" wire:click="removeItem({{ $index }})" aria-label="Hapus produk">Hapus</button></div>
                    </div>
                @endforeach
                @error('items')<div class="small text-danger mb-2">{{ $message }}</div>@enderror
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" wire:click="addItem">Tambah produk</button>
                <div class="mb-3"><label class="form-label" for="stock-note">{{ $direction === 'return' ? 'Alasan pengembalian' : 'Catatan (opsional)' }}</label><textarea id="stock-note" wire:model="notes" rows="2" maxlength="2000" class="form-control" @required($direction === 'return')></textarea>@error('notes')<div class="small text-danger">{{ $message }}</div>@enderror</div>
                @if($direction === 'return')<div class="form-check mb-3"><input id="stock-good" type="checkbox" wire:model="isReturnGood" class="form-check-input" required><label class="form-check-label" for="stock-good">Saya sudah memeriksa fisik barang: masih layak, belum digunakan, dan belum kedaluwarsa.</label></div>@endif
                <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ $direction === 'pickup' ? 'Konfirmasi pengambilan' : 'Konfirmasi pengembalian' }}</button>
            </form>
        </div></div></div>
        <div class="col-12 col-xl-5"><div class="card h-100"><div class="card-header"><h5 class="card-title mb-0">Stok tersedia</h5></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Produk</th><th>Gudang Bersama</th><th>{{ $branch->name }}</th></tr></thead><tbody>
            @forelse($products as $product)
                @php $centralBalance = $balances->get($central->id)?->firstWhere('product_id', $product->id); $branchBalance = $balances->get($branch->id)?->firstWhere('product_id', $product->id); @endphp
                <tr><td>{{ $product->title }}</td><td>{{ \App\Support\DisplayNumber::format(max(0, (float) ($centralBalance?->stock ?? 0) - (float) ($centralBalance?->reserved_stock ?? 0)), 3, ',', '.') }}</td><td>{{ \App\Support\DisplayNumber::format(max(0, (float) ($branchBalance?->stock ?? 0) - (float) ($branchBalance?->reserved_stock ?? 0)), 3, ',', '.') }}</td></tr>
            @empty<tr><td colspan="3" class="text-muted text-center py-3">Belum ada produk setengah jadi aktif.</td></tr>@endforelse
        </tbody></table></div></div></div>
    </div>
    <div class="card mt-3"><div class="card-header"><h5 class="card-title mb-0">Riwayat perpindahan saya</h5></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Waktu / nomor</th><th>Jenis</th><th>Produk / jumlah</th><th>Status</th></tr></thead><tbody>
        @forelse($transfers as $transfer)
            <tr><td>{{ $transfer->created_at->format('d/m/Y H:i') }}<div class="small text-muted">{{ $transfer->number }}</div></td><td>{{ $transfer->direction === 'return' ? 'Pengembalian' : 'Pengambilan' }}</td><td>@foreach($transfer->items as $item)<div>{{ $item->product?->title }} · {{ \App\Support\DisplayNumber::format((float) $item->quantity_sent, 3, ',', '.') }}</div>@endforeach</td><td>{{ str($transfer->status)->headline() }}</td></tr>
        @empty<tr><td colspan="4" class="text-muted text-center py-3">Belum ada perpindahan stok.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
