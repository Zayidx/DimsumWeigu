<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <p class="text-muted mb-0">Resep dapat menggabungkan bahan baku dan produk setengah jadi. Untuk menu paket, pilih satuan paket agar HPP dan pemakaian stok dihitung per paket.</p>
        @can('recipes-create')<button class="btn btn-primary" wire:click="create"><i class="bi bi-journal-plus me-1"></i>Buat versi resep</button>@endcan
    </div>

    <div class="card">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Produk / satuan output</th><th>Versi</th><th>Komponen per output</th><th class="text-end">Estimasi HPP</th><th>Mulai berlaku</th></tr></thead>
            <tbody>
                @forelse($recipes as $recipe)
                    <tr>
                        <td><strong>{{ $recipe->product?->title }}</strong><div class="small text-muted">{{ $recipe->product?->sku }} · {{ $recipe->unit?->symbol }}</div></td>
                        <td>v{{ $recipe->version }}</td>
                        <td>
                            @foreach($recipe->items as $item)<div>{{ $item->ingredient?->name }}: {{ number_format((float)$item->quantity, 3, ',', '.') }} {{ $item->ingredient?->unit?->symbol }}</div>@endforeach
                            @foreach($recipe->productItems as $item)<div>{{ $item->product?->title }}: {{ number_format((float)$item->quantity, 3, ',', '.') }} {{ $item->unit?->symbol }}</div>@endforeach
                        </td>
                        <td class="text-end fw-semibold">Rp {{ number_format((float)$recipe->estimated_hpp, 2, ',', '.') }}</td>
                        <td>{{ $recipe->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada resep aktif.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($recipes->hasPages())<div class="card-footer">{{ $recipes->links() }}</div>@endif
    </div>

    @if($showForm)
        <div class="modal-backdrop show" style="z-index:1040" wire:click="$set('showForm', false)"></div>
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="recipe-modal-title" style="z-index:1050" wire:keydown.escape="$set('showForm', false)">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                <form wire:submit="save">
                    <div class="modal-header"><h5 class="modal-title" id="recipe-modal-title">Versi resep baru</h5><button type="button" class="btn-close" wire:click="$set('showForm', false)" aria-label="Tutup"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-light-primary">Gunakan resep bahan mentah untuk dimsum wortel/beef. Resep menu outlet dapat memakai produk setengah jadi, saus, dan kemasan.</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label" for="recipe-product">Produk yang dibuat / dijual</label><select id="recipe-product" wire:model.live="productId" class="form-select" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->title }} ({{ $product->sku }})@if($product->is_semi_finished) · Setengah jadi @endif</option>@endforeach</select>@error('productId')<div class="small text-danger">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label class="form-label" for="recipe-unit">Satuan output resep</label><select id="recipe-unit" wire:model="recipeUnitId" class="form-select" required><option value="">Pilih satuan</option>@if($selectedProduct)<option value="{{ $selectedProduct->unit_id }}">{{ $selectedProduct->unit?->symbol }} (dasar)</option>@foreach($selectedProduct->productUnits->where('unit_id', '!=', $selectedProduct->unit_id) as $variant)<option value="{{ $variant->unit_id }}">{{ $variant->unit?->symbol }}</option>@endforeach @endif</select>@error('recipeUnitId')<div class="small text-danger">{{ $message }}</div>@enderror</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Komponen per satu output</h6><button type="button" class="btn btn-sm btn-outline-primary" wire:click="addLine">Tambah komponen</button></div>
                        @foreach($lines as $index => $line)
                            <div class="row g-2 align-items-end mb-2" wire:key="recipe-line-{{ $index }}">
                                <div class="col-12 col-md-2"><label class="form-label" for="recipe-type-{{ $index }}">Jenis</label><select id="recipe-type-{{ $index }}" wire:model.live="lines.{{ $index }}.type" class="form-select"><option value="ingredient">Bahan</option><option value="product">Setengah jadi</option></select></div>
                                <div class="col-12 col-md-4">
                                    @if(($line['type'] ?? 'ingredient') === 'product')
                                        <label class="form-label" for="recipe-product-{{ $index }}">Produk setengah jadi</label><select id="recipe-product-{{ $index }}" wire:model="lines.{{ $index }}.component_product_id" class="form-select" required><option value="">Pilih produk</option>@foreach($semiFinishedProducts as $component)<option value="{{ $component->id }}">{{ $component->title }} · {{ $component->unit?->symbol }} · Rp {{ number_format((float)$component->buy_price, 0, ',', '.') }}</option>@endforeach</select>
                                    @else
                                        <label class="form-label" for="recipe-ingredient-{{ $index }}">Bahan baku / saus / kemasan</label><select id="recipe-ingredient-{{ $index }}" wire:model="lines.{{ $index }}.ingredient_id" class="form-select" required><option value="">Pilih bahan</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}">{{ $ingredient->name }} · {{ $ingredient->unit?->symbol }} · Rp {{ number_format((float)$ingredient->buy_price, 0, ',', '.') }}</option>@endforeach</select>
                                    @endif
                                </div>
                                <div class="col-6 col-md-3"><label class="form-label" for="recipe-qty-{{ $index }}">Qty / output</label><input id="recipe-qty-{{ $index }}" type="number" min="0.001" step="0.001" wire:model="lines.{{ $index }}.quantity" class="form-control" required></div>
                                <div class="col-4 col-md-2"><label class="form-label" for="recipe-waste-{{ $index }}">Susut %</label><input id="recipe-waste-{{ $index }}" type="number" min="0" max="100" step="0.01" wire:model="lines.{{ $index }}.waste_percent" class="form-control"></div>
                                <div class="col-2 col-md-1"><button type="button" class="btn btn-outline-danger" wire:click="removeLine({{ $index }})" aria-label="Hapus komponen" title="Hapus komponen"><i class="bi bi-trash"></i></button></div>
                            </div>
                        @endforeach
                        @error('lines')<div class="small text-danger">{{ $message }}</div>@enderror
                        <div class="mt-3"><label class="form-label" for="recipe-notes">Catatan versi</label><textarea id="recipe-notes" wire:model="notes" rows="2" class="form-control" maxlength="2000"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-secondary" wire:click="$set('showForm', false)">Batal</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan versi resep</button></div>
                </form>
            </div></div>
        </div>
    @endif
</div>
