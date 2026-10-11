<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <p class="text-muted mb-0">Gudang meminta target produk setengah jadi. Sistem menghitung bahan baku dan estimasi biayanya dari resep aktif.</p>
        @can('production-requests-create')<button class="btn btn-primary" wire:click="create"><i class="bi bi-plus-lg me-1"></i>Permintaan baru</button>@endcan
    </div>
    
    <div class="card"><div class="card-header"><h5 class="card-title mb-0">Permintaan produk setengah jadi dan bahan</h5></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Permintaan</th><th>Produk setengah jadi / target</th><th>Bahan, kebutuhan, biaya, dan kekurangan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse($requests as $request)<tr><td><strong>{{ $request->number }}</strong><div class="small text-muted">{{ $request->created_at->format('d/m/Y H:i') }}</div></td><td>@foreach($request->items as $item)<div>{{ $item->product?->title }} · {{ \App\Support\DisplayNumber::format((float)$item->quantity, 3, ',', '.') }} unit <small class="text-muted">(resep v{{ $item->recipe_version_id ? $item->recipe?->version : '—' }})</small></div>@endforeach</td><td><div class="small mb-2"><strong>Estimasi total bahan: Rp {{ \App\Support\DisplayNumber::format((float) $request->materials->sum('estimated_cost'), 2, ',', '.') }}</strong></div>@foreach($request->materials as $material)<div>{{ $material->ingredient?->name }}: {{ \App\Support\DisplayNumber::format((float)$material->required_quantity, 3, ',', '.') }} {{ $material->ingredient?->unit?->symbol }} <small class="text-muted">@ Rp {{ \App\Support\DisplayNumber::format((float) $material->unit_cost, 2, ',', '.') }} / unit = Rp {{ \App\Support\DisplayNumber::format((float) $material->estimated_cost, 2, ',', '.') }}</small> <small class="{{ (float)$material->purchase_quantity > 0 ? 'text-danger' : 'text-success' }}">· beli {{ \App\Support\DisplayNumber::format((float)$material->purchase_quantity, 3, ',', '.') }}</small></div>@endforeach</td><td><span class="badge bg-light-primary text-primary">{{ $request->status === 'purchasing' ? 'Sedang dikirim' : str($request->status)->replace('_', ' ')->headline() }}</span></td><td>@if(in_array($request->status, ['requested', 'purchasing'], true))@can('production-requests-approve')<button class="btn btn-sm btn-outline-primary" wire:click="openApproval({{ $request->id }})">{{ $request->status === 'purchasing' ? 'Buat PO tambahan' : 'Setujui & buat PO' }}</button>@endcan @elseif($request->status === 'approved')<span class="small text-success">Stok tersedia</span>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-center py-5 text-muted">Belum ada permintaan produksi untuk outlet ini.</td></tr>@endforelse
    </tbody></table></div></div>

    @can('production-requests-approve')
        <div class="card mt-3"><div class="card-header"><h5 class="card-title mb-0">Nilai restock Finance</h5></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Permintaan</th><th>Estimasi bahan</th><th>Disetujui di PO</th><th>Realisasi diterima</th></tr></thead><tbody>
            @forelse($requests as $request)
                <tr><td>{{ $request->number }}</td><td>Rp {{ \App\Support\DisplayNumber::format((float) $restockTotals[$request->id]['estimated'], 2, ',', '.') }}</td><td>Rp {{ \App\Support\DisplayNumber::format((float) $restockTotals[$request->id]['approved'], 2, ',', '.') }}</td><td>Rp {{ \App\Support\DisplayNumber::format((float) $restockTotals[$request->id]['received'], 2, ',', '.') }}</td></tr>
            @empty<tr><td colspan="4" class="text-center py-3 text-muted">Belum ada permintaan restock.</td></tr>@endforelse
        </tbody></table></div></div>
    @endcan

    @can('production-requests-reject')
        @if($requests->contains(fn ($request) => $request->status === 'requested'))
            <div class="card mt-3"><div class="card-body"><h6 class="mb-2">Keputusan restock Finance</h6>
                @foreach($requests->where('status', 'requested') as $request)
                    <div class="d-flex align-items-center justify-content-between gap-2 border-top py-2"><span>{{ $request->number }} · menunggu keputusan</span><button class="btn btn-sm btn-outline-danger" wire:click="openRejection({{ $request->id }})">Tolak dengan alasan</button></div>
                @endforeach
            </div></div>
        @endif
    @endcan

    @if($requests->contains(fn ($request) => $request->status === 'rejected'))
        <div class="card mt-3"><div class="card-body"><h6 class="mb-2">Permintaan ditolak</h6>
            @foreach($requests->where('status', 'rejected') as $request)
                <div class="border-top py-2"><strong>{{ $request->number }}</strong><div class="small text-muted">{{ $request->rejection_reason }}</div></div>
            @endforeach
        </div></div>
    @endif

    <div class="card mt-4"><div class="card-header"><h5 class="card-title mb-0">Penerimaan bahan menunggu QC</h5></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Purchase order</th><th>Permintaan</th><th>Pemasok</th><th>Total</th><th></th></tr></thead><tbody>
        @forelse($purchases as $purchase)<tr><td><strong>{{ $purchase->number }}</strong></td><td>{{ $purchase->request?->number }}</td><td>{{ $purchase->supplier?->name ?? '—' }}</td><td>Rp {{ \App\Support\DisplayNumber::format((float)$purchase->total, 2, ',', '.') }}</td><td class="text-end">@can('goods-receivings-create')<button class="btn btn-sm btn-primary" wire:click="openReceiving({{ $purchase->id }})">Terima &amp; QC</button>@endcan</td></tr>
        @empty<tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada pembelian bahan yang menunggu penerimaan.</td></tr>@endforelse
    </tbody></table></div></div>

    <!-- MODAL FORM PERMINTAAN -->
    @if($showForm)
    <div class="modal-backdrop show" style="z-index:1040" wire:click="$set('showForm', false)"></div>
    <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="request-modal-title" style="z-index:1050">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form wire:submit="submitRequest" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="request-modal-title">Permintaan produksi setengah jadi</h5>
                    <button type="button" class="btn-close" wire:click="$set('showForm', false)" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Pilih produk setengah jadi dan target output. Finance melihat kebutuhan bahan, stok, estimasi harga, dan mengisi harga beli aktual bila perlu membeli.</p>
                    <div class="row g-2 mb-3">
                        @foreach($products as $product)
                        <div class="col-12 col-sm-6 col-lg-4" wire:key="catalog-product-{{ $product->id }}">
                            <div class="border rounded p-2 h-100">
                                <strong class="d-block mb-1">{{ $product->title }}</strong>
                                <div class="small text-muted mb-2">{{ $product->unit?->symbol ?? 'pcs' }}</div>
                                <div class="d-flex gap-2">
                                    <input type="number" min="0.001" step="0.001" wire:model="catalogQuantities.{{ $product->id }}" class="form-control form-control-sm" placeholder="Target" aria-label="Target {{ $product->title }}">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addCatalogProduct({{ $product->id }})">Tambah</button>
                                </div>
                                @error('catalogQuantities.'.$product->id)<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @if($products->isEmpty())
                        <div class="alert alert-light-warning">Belum ada produk setengah jadi dengan resep bahan baku aktif. Tandai produk sebagai setengah jadi dan buat resep terlebih dahulu di Resep &amp; HPP.</div>
                    @endif
                    
                    @foreach($lines as $index => $line)
                    <div class="row g-2 align-items-end mb-3" wire:key="request-line-{{ $index }}">
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="request-product-{{ $index }}">Produk setengah jadi</label>
                            <select id="request-product-{{ $index }}" wire:model.live.debounce.300ms="lines.{{ $index }}.product_id" class="form-select" required>
                                <option value="">Pilih produk</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->title }} · {{ $product->sku }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-8 col-md-3">
                            <label class="form-label" for="request-qty-{{ $index }}">Target output</label>
                            <input id="request-qty-{{ $index }}" type="number" min="0.001" step="0.001" wire:model.live.debounce.300ms="lines.{{ $index }}.quantity" class="form-control" required>
                        </div>
                        <div class="col-4 col-md-1">
                            <button type="button" class="btn btn-outline-danger" wire:click="removeLine({{ $index }})" aria-label="Hapus menu"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    @endforeach
                    
                    @error('lines')<div class="small text-danger">{{ $message }}</div>@enderror
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addLine">Tambah produk</button>
                    
                    @if($previewMaterials)
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Bahan mentah</th><th>Kebutuhan</th><th>Stok gudang</th><th>Siap dipakai</th><th>Kekurangan</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($previewMaterials as $material)
                                <tr>
                                    <td>{{ $material['ingredient'] }}</td>
                                    <td>{{ $material['required'] }} {{ $material['unit'] }}</td>
                                    <td>{{ $material['stock'] }}</td>
                                    <td>{{ $material['available'] }}</td>
                                    <td>{{ $material['shortage'] }}</td>
                                    <td><span class="badge {{ $material['status'] === 'Cukup' ? 'bg-light-success text-success' : 'bg-light-danger text-danger' }}">{{ $material['status'] }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    <div class="mt-3">
                        <label class="form-label" for="request-notes">Catatan</label>
                        <textarea id="request-notes" wire:model="notes" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" wire:click="$set('showForm', false)">Batal</button>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Kirim permintaan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL FORM APPROVAL -->
    @if($showApproval)
    <div class="modal-backdrop show" style="z-index:1040" wire:click="$set('showApproval', false)"></div>
    <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="approval-modal-title" style="z-index:1050">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form wire:submit="approve" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approval-modal-title">Persetujuan &amp; pembelian Finance</h5>
                    <button type="button" class="btn-close" wire:click="$set('showApproval', false)" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="request-supplier">Pemasok bahan</label>
                    <select id="request-supplier" wire:model="supplierId" class="form-select">
                        <option value="">Pilih pemasok</option>
                        @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
                    </select>
                    @error('supplierId')<div class="small text-danger">{{ $message }}</div>@enderror
                    <div class="mt-3">
                        <h6>Kebutuhan, jumlah pembelian, dan harga terbaru</h6>
                        <p class="small text-muted">Jumlah pembelian otomatis dimulai dari kekurangan. Finance boleh menambah jumlah agar stok bahan tersedia untuk produksi berikutnya.</p>
                        @forelse($approvalMaterials as $material)
                        <div class="row g-2 align-items-end mb-2" wire:key="approval-material-{{ $material['ingredient_id'] }}">
                            <div class="col-7">
                                <label class="form-label" for="purchase-cost-{{ $material['ingredient_id'] }}">{{ $material['name'] }} · kekurangan {{ \App\Support\DisplayNumber::format((float)$material['quantity'], 3, ',', '.') }} {{ $material['unit'] }}</label>
                                <label class="form-label" for="purchase-qty-{{ $material['ingredient_id'] }}">Jumlah yang dibeli ({{ $material['unit'] }})</label>
                                <input id="purchase-qty-{{ $material['ingredient_id'] }}" type="number" min="0" step="0.001" inputmode="decimal" wire:model.live.debounce.300ms="purchaseQuantities.{{ $material['ingredient_id'] }}" class="form-control mb-2" required>
                                <small class="form-text text-muted d-block">Harga beli saat ini: Rp {{ \App\Support\DisplayNumber::format((float) $material["unit_cost"], 2, ",", ".") }} per {{ $material["unit"] }}</small>
                                <input id="purchase-cost-{{ $material['ingredient_id'] }}" type="number" min="0" step="0.00000001" inputmode="decimal" wire:model.live.debounce.300ms="unitCosts.{{ $material['ingredient_id'] }}" class="form-control" required>
                            </div>
                            <div class="col-5 small text-muted">Harga per satuan resep</div>
                        </div>
                        @empty
                        <div class="alert alert-light-success mb-0">Stok sudah mencukupi. Permintaan akan disetujui tanpa membuat PO.</div>
                        @endforelse
                    </div>
                    @error('unitCosts.*')<div class="small text-danger">{{ $message }}</div>@enderror
                    <div class="alert alert-light-primary mt-3 mb-0">Estimasi total pembelian: <strong>Rp {{ \App\Support\DisplayNumber::format($this->purchaseTotal, 2, ',', '.') }}</strong></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" wire:click="$set('showApproval', false)">Batal</button>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Setujui &amp; proses pembelian</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL FORM REJECTION -->
    @if($showRejection)
    <div class="modal-backdrop show" style="z-index:1040" wire:click="$set('showRejection', false)"></div>
    <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="rejection-modal-title" style="z-index:1050">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form wire:submit="reject" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejection-modal-title">Tolak permintaan restock</h5>
                    <button type="button" class="btn-close" wire:click="$set('showRejection', false)" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="rejection-reason">Alasan untuk Warehouse</label>
                    <textarea id="rejection-reason" wire:model="rejectionReason" class="form-control" rows="3" maxlength="2000" required></textarea>
                    @error('rejectionReason')<div class="small text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" wire:click="$set('showRejection', false)">Batal</button>
                    <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">Konfirmasi penolakan</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL FORM RECEIVING -->
    @if($showReceiving)
    <div class="modal-backdrop show" style="z-index:1040" wire:click="$set('showReceiving', false)"></div>
    <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="receiving-modal-title" style="z-index:1050">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form wire:submit="receive" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiving-modal-title">Penerimaan dan QC bahan</h5>
                    <button type="button" class="btn-close" wire:click="$set('showReceiving', false)" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="receipt-invoice" class="form-label">Nomor invoice / nota (opsional)</label>
                        <input id="receipt-invoice" wire:model="invoiceReference" class="form-control" maxlength="100">
                    </div>
                    @foreach($receiptLines as $index => $line)
                    <div class="border rounded p-3 mb-3" wire:key="receipt-line-{{ $line['purchase_order_item_id'] }}">
                        <strong>{{ $line['name'] }} <span class="text-muted">({{ $line['unit'] }})</span></strong>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label" for="sent-{{ $index }}">Qty datang</label>
                                <input id="sent-{{ $index }}" type="number" min="0" step="0.001" wire:model="receiptLines.{{ $index }}.sent_quantity" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="accepted-{{ $index }}">Qty lolos QC</label>
                                <input id="accepted-{{ $index }}" type="number" min="0" step="0.001" wire:model="receiptLines.{{ $index }}.accepted_quantity" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="qc-{{ $index }}">Kondisi barang</label>
                                <select id="qc-{{ $index }}" wire:model="receiptLines.{{ $index }}.qc_status" class="form-select">
                                    <option value="good">Baik</option>
                                    <option value="damaged">Rusak sebagian</option>
                                    <option value="short">Kurang kirim</option>
                                    <option value="wrong_item">Barang salah</option>
                                    <option value="unusable">Tidak dapat digunakan</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="receipt-note-{{ $index }}">Catatan</label>
                                <input id="receipt-note-{{ $index }}" wire:model="receiptLines.{{ $index }}.notes" class="form-control" maxlength="1000">
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @error('receiptLines')<div class="small text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" wire:click="$set('showReceiving', false)">Batal</button>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Konfirmasi penerimaan</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>