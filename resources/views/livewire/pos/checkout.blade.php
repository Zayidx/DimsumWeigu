<div class="pos-workspace" x-data="{ cartOpen: false }" x-on:pos-cart-open.window="cartOpen = true" x-on:keydown.escape.window="cartOpen = false">
    @if($lastTransaction)
        <div class="alert alert-success d-flex align-items-center justify-content-between" role="status"><span>Pembayaran berhasil. <strong>{{ $lastTransaction }}</strong></span><a class="btn btn-outline-success" href="{{ route('transactions.show', $lastTransactionId) }}">Lihat transaksi</a></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert" data-error-summary><strong>Periksa kembali</strong><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    @if(!$shift)
        <div class="card"><div class="card-body text-center py-5"><i class="bi bi-shop fs-1 text-primary" aria-hidden="true"></i><h2 class="h5 mt-3">Buka shift untuk mulai melayani</h2><p class="text-muted">Pilih gudang penjualan dan catat kas awal sebelum membuat transaksi.</p>@can('cashier-shifts-open')<a class="btn btn-primary" href="{{ route('shifts.create') }}">Buka shift kasir</a>@else<p class="text-muted">Akun ini belum memiliki izin membuka shift. Hubungi administrator.</p>@endcan</div></div>
    @else
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><span class="badge bg-light-success text-success">Shift aktif</span><span class="ms-2 small fw-semibold">{{ $shift->warehouse->name }}</span></div><a href="{{ route('shifts.index') }}" class="btn btn-outline-secondary">Kelola shift</a></div>
        <div class="row g-3 align-items-start">
            <section class="col-12 col-xl-7" aria-label="Pilih produk">
                <div class="card"><div class="card-header"><h2 class="h5 card-title mb-0">Pilih produk</h2></div><div class="card-body">
                    <label for="pos-product-search" class="visually-hidden">Cari atau scan produk</label>
                    <div class="input-group mb-3"><span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span><input id="pos-product-search" type="search" wire:model.live.debounce.250ms="search" class="form-control" placeholder="Nama, SKU, atau barcode" autocomplete="off" enterkeyhint="search"></div>
                    <div class="row g-2">
                        @forelse($products as $product)
                            @php($stock = $product->warehouseStocks->first())
                            <div class="col-6" wire:key="pos-product-{{ $product->id }}"><article class="pos-product-card">
                                <button type="button" wire:click="addProduct({{ $product->id }})" wire:loading.attr="disabled" class="btn pos-product-main" aria-label="Tambah {{ $product->title }}">
                                    <span class="pos-product-title"><strong>{{ $product->title }}</strong><i class="bi bi-plus-circle text-primary" aria-hidden="true"></i></span>
                                    <span class="d-block small text-muted mt-1">{{ $product->unit?->symbol }}@if($product->track_stock && $stock) · stok {{ number_format(max(0, (float)$stock->stock - (float)$stock->reserved_stock), 3, ',', '.') }}@endif</span>
                                    <span class="pos-product-price">Rp{{ number_format((float)$product->sell_price, 0, ',', '.') }}</span>
                                </button>
                                @if($product->productUnits->where('unit_id', '!=', $product->unit_id)->isNotEmpty())<div class="pos-variants">
                                    @foreach($product->productUnits->where('unit_id', '!=', $product->unit_id) as $altUnit)
                                        <button type="button" wire:click="addProductUnit({{ $product->id }}, {{ $altUnit->unit_id }})" wire:loading.attr="disabled" class="btn btn-outline-primary" aria-label="Tambah {{ $product->title }} {{ $altUnit->unit->name }}"><span>+ {{ $altUnit->unit->symbol }}</span><strong>Rp{{ number_format((float)($altUnit->sell_price ?? ((float)$product->sell_price * (float)$altUnit->conversion_factor)),0,',','.') }}</strong></button>
                                    @endforeach
                                </div>@endif
                            </article></div>
                        @empty
                            <div class="col-12 text-center py-5 text-muted"><i class="bi bi-search fs-2" aria-hidden="true"></i><p class="mt-2 mb-0">{{ $search !== '' ? 'Produk tidak ditemukan.' : 'Belum ada produk dengan stok tersedia di gudang ini.' }}</p><p class="small mt-2">{{ $search !== '' ? 'Coba nama produk, SKU, atau barcode lain.' : 'Stok bahan di PUSAT belum menjadi stok produk yang bisa dijual di outlet.' }}</p>@can('inventory-access')<a href="{{ route('inventory.index') }}" class="btn btn-outline-primary">Lihat inventori</a>@endcan</div>
                        @endforelse
                    </div>
                    <div class="mt-3">{{ $products->links() }}</div>
                </div></div>
            </section>
            <div class="pos-cart-backdrop" :class="{ 'is-open': cartOpen }" x-on:click="cartOpen = false" aria-hidden="true"></div>
            <section id="pos-cart-panel" class="col-12 col-xl-5 pos-cart-panel" :class="{ 'is-open': cartOpen }" aria-labelledby="pos-cart-title" tabindex="-1">
                <div class="card"><div class="card-header pos-cart-heading"><div><h2 id="pos-cart-title" class="h5 card-title mb-0">Keranjang</h2><span class="small text-muted">{{ $cart->items->count() }} baris produk</span></div><button type="button" class="btn btn-light pos-cart-close" x-on:click="cartOpen = false" aria-label="Kembali ke pilihan produk"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="card-body p-0">
                    @if($errors->any())<div class="alert alert-danger m-3" role="alert">{{ $errors->first() }}</div>@endif
                    @forelse($cart->items as $item)
                        <div class="pos-line-item" wire:key="pos-item-{{ $item->id }}">
                            <div class="d-flex justify-content-between align-items-start gap-2"><div class="pos-line-item-title"><strong>{{ $item->product->title }}</strong><div class="small text-muted">{{ $item->unit?->name }}</div></div><button type="button" wire:click="removeItem({{ $item->id }})" wire:loading.attr="disabled" class="btn btn-light text-danger" aria-label="Hapus {{ $item->product->title }}"><i class="bi bi-trash" aria-hidden="true"></i></button></div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2"><div class="input-group pos-quantity-controls"><button class="btn btn-outline-secondary" type="button" wire:click="setQuantity({{ $item->id }}, '{{ max(0.001, (float)$item->quantity - 1) }}')" wire:loading.attr="disabled" aria-label="Kurangi jumlah {{ $item->product->title }}">&minus;</button><input type="number" min="0.001" step="0.001" inputmode="decimal" value="{{ (float)$item->quantity }}" wire:change="setQuantity({{ $item->id }}, $event.target.value)" class="form-control text-center" aria-label="Jumlah {{ $item->product->title }}"><button class="btn btn-outline-secondary" type="button" wire:click="setQuantity({{ $item->id }}, '{{ (float)$item->quantity + 1 }}')" wire:loading.attr="disabled" aria-label="Tambah jumlah {{ $item->product->title }}">+</button></div><strong class="small">Rp{{ number_format(($previewLines[$item->id] ?? 0) / 100, 0, ',', '.') }}</strong></div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted"><i class="bi bi-cart fs-2" aria-hidden="true"></i><p class="mt-2 mb-0">Keranjang masih kosong.</p><span class="small">Pilih produk untuk memulai.</span><button type="button" class="btn btn-primary pos-cart-close mx-auto mt-3" x-on:click="cartOpen = false">Pilih produk</button></div>
                    @endforelse
                </div>
                @if($cart->items->isNotEmpty())
                    <div class="card-body border-top">
                        <details class="mb-3"><summary class="fw-semibold d-flex align-items-center">Pelanggan dan catatan</summary><div class="pt-3"><label class="form-label" for="pos-customer">Pelanggan (opsional)</label><select id="pos-customer" wire:model="customerId" class="form-select"><option value="">Pelanggan umum</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}@if($customer->phone) · {{ $customer->phone }}@endif</option>@endforeach</select><label class="form-label mt-3" for="pos-notes">Catatan transaksi</label><textarea id="pos-notes" wire:model="notes" rows="2" class="form-control" maxlength="2000"></textarea><button type="button" wire:click="saveDetails" wire:loading.attr="disabled" class="btn btn-outline-secondary mt-2">Simpan detail</button></div></details>
                        <div class="pos-checkout-actions"><div class="d-flex justify-content-between gap-2 pos-total"><span>Total</span><strong>Rp{{ number_format($previewTotal / 100, 0, ',', '.') }}</strong></div><p class="small text-muted mb-0">Termasuk perkiraan pajak. Total akhir diperiksa saat pembayaran.</p>
                            @can('pos-checkout')
                                <form wire:submit="pay" class="mt-3"><label class="form-label" for="pos-cash">Tunai diterima (Rp)</label><input id="pos-cash" type="number" min="0" step="0.01" inputmode="decimal" wire:model="cashReceived" class="form-control" required><div class="pos-quick-cash"><button type="button" wire:click="$set('cashReceived', '{{ number_format($previewTotal / 100, 2, '.', '') }}')" class="btn btn-outline-primary">Uang pas</button>@foreach([50000, 100000] as $cashOption)@if($cashOption * 100 >= $previewTotal)<button type="button" wire:click="$set('cashReceived', '{{ $cashOption }}')" class="btn btn-outline-secondary">{{ number_format($cashOption,0,',','.') }}</button>@endif@endforeach</div>@error('cashReceived')<div class="text-danger small mt-1">{{ $message }}</div>@enderror<button class="btn btn-success w-100 mt-3" type="submit" wire:loading.attr="disabled"><span wire:loading.remove wire:target="pay"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Bayar tunai</span><span wire:loading wire:target="pay">Memproses pembayaran…</span></button></form>
                                @if($payLaterEnabled)<button type="button" wire:click="payLater" wire:confirm="Catat transaksi ini sebagai piutang pelanggan? Batas kredit akan diperiksa." class="btn btn-outline-warning w-100 mt-2" wire:loading.attr="disabled">Bayar nanti (piutang)</button>@endif
                            @endcan
                            @can('pos-hold')<form wire:submit="hold" class="mt-3"><label class="form-label" for="pos-hold-label">Tahan keranjang</label><div class="input-group"><input id="pos-hold-label" wire:model="holdLabel" class="form-control" maxlength="120" placeholder="Nama atau nomor antrean"><button type="submit" class="btn btn-outline-primary" wire:loading.attr="disabled">Tahan</button></div></form>@endcan
                        </div>
                    </div>
                @endif
                </div>
                @if($heldCarts->isNotEmpty())<div class="card"><div class="card-header"><h2 class="h5 card-title mb-0">Keranjang tertahan</h2></div><ul class="list-group list-group-flush">@foreach($heldCarts as $held)<li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2"><div><strong>{{ $held->hold_label }}</strong><div class="small text-muted">{{ $held->items_count }} item · {{ $held->held_at?->format('H:i') }}</div></div><div class="d-flex gap-2">@can('pos-hold')<button type="button" wire:click="resume({{ $held->id }})" wire:loading.attr="disabled" class="btn btn-primary">Lanjutkan</button><button type="button" wire:click="discard({{ $held->id }})" wire:loading.attr="disabled" wire:confirm="Hapus keranjang tertahan ini?" class="btn btn-outline-danger" aria-label="Hapus keranjang tertahan"><i class="bi bi-trash" aria-hidden="true"></i></button>@endcan</div></li>@endforeach</ul></div>@endif
            </section>
        </div>
        <button type="button" class="btn btn-primary pos-mobile-cart-button" x-on:click="cartOpen = true" :aria-expanded="cartOpen" aria-controls="pos-cart-panel"><span><strong>Lihat keranjang · {{ $cart->items->count() }}</strong><small>{{ $heldCarts->count() ? $heldCarts->count().' keranjang tertahan' : 'Lanjut ke pembayaran' }}</small></span><strong>Rp{{ number_format($previewTotal / 100,0,',','.') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></strong></button>
    @endif
</div>
