<div>
    <div class="alert alert-light-primary">
        Saat produksi dimulai, bahan sesuai rencana langsung dipotong dari stok Gudang Bersama. Saat selesai, catat bahan aktual dan hasil jadi.
    </div>
    
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-1">Shift &amp; produksi hari ini</h5>
                <div class="small text-muted">Buka shift saat mulai bekerja, lalu semua production order yang dimulai akan tercatat pada shift tersebut.</div>
            </div>
        </div>
        <div class="card-body">
            @if($openShifts->isNotEmpty())
                @foreach($openShifts as $shift)
                    @php($items = $shift->orders->flatMap->items)
                    <div class="alert alert-light-success">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <strong>Shift aktif · {{ $shift->warehouse->name }}</strong>
                                <div class="small">Dibuka {{ $shift->opened_at->format('d/m/Y H:i') }}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="closeShift({{ $shift->id }})" wire:confirm="Tutup shift produksi ini? Periksa ringkasan target, hasil, dan bahan terpakai.">
                                Tutup shift
                            </button>
                        </div>
                        <div class="small mt-2">
                            {{ $shift->orders->count() }} aktivitas · 
                            target {{ \App\Support\DisplayNumber::format($items->sum(fn ($item) => (float) $item->planned_quantity), 3, ',', '.') }} · 
                            aktual {{ \App\Support\DisplayNumber::format($items->sum(fn ($item) => (float) $item->produced_quantity), 3, ',', '.') }} · 
                            selisih {{ \App\Support\DisplayNumber::format($items->whereNotNull('variance_quantity')->sum(fn ($item) => (float) $item->variance_quantity), 3, ',', '.') }}
                        </div>
                        @foreach($items->flatMap->materials->groupBy('ingredient_id') as $materials)
                            <div class="small">
                                {{ $materials->first()->ingredient?->name }}: {{ \App\Support\DisplayNumber::format($materials->sum(fn ($row) => (float) $row->actual_quantity), 3, ',', '.') }} {{ $materials->first()->ingredient?->unit?->symbol }} terpakai
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @else
                <form wire:submit="openShift" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label for="production-shift-warehouse" class="form-label">Gudang produksi</label>
                        <select id="production-shift-warehouse" wire:model="shiftWarehouseId" class="form-select" required>
                            <option value="">Pilih gudang</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('shiftWarehouseId')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" wire:loading.attr="disabled">Buka shift produksi</button>
                    </div>
                </form>
            @endif
            @error('shift')<div class="small text-danger mt-2">{{ $message }}</div>@enderror
        </div>
        
        <div class="d-flex justify-content-end align-items-center gap-2 mb-2 px-3">
            <label for="production-date" class="form-label mb-0">Tanggal produksi</label>
            <input id="production-date" type="date" wire:model.live="productionDate" class="form-control form-control-sm" style="max-width: 180px">
        </div>
        
        @if($todayShifts->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu / gudang</th>
                            <th>Petugas</th>
                            <th>Hasil produksi</th>
                            <th>Status shift</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todayShifts as $shift)
                            <tr>
                                <td>
                                    {{ $shift->opened_at->format('H:i') }}
                                    @if($shift->closed_at)
                                        –{{ $shift->closed_at->format('H:i') }}
                                    @endif
                                    <div class="small text-muted">{{ $shift->warehouse->name }}</div>
                                </td>
                                <td>{{ $shift->user->name }}</td>
                                <td>
                                    @php($outputs = $shift->orders->where('status', 'completed')->flatMap->items->groupBy('product_id'))
                                    @forelse($outputs as $items)
                                        <div>{{ $items->first()->product->title }} · {{ \App\Support\DisplayNumber::format($items->sum(fn ($item) => (float) $item->produced_quantity), 3, ',', '.') }} {{ $items->first()->product->unit?->symbol }}</div>
                                    @empty
                                        <span class="text-muted">Belum ada hasil tercatat</span>
                                    @endforelse
                                </td>
                                <td>
                                    <span class="badge {{ $shift->status === 'open' ? 'bg-light-success text-success' : 'bg-light-secondary' }}">
                                        {{ $shift->status === 'open' ? 'Berjalan' : 'Ditutup' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="small text-muted px-3 pb-3">Tidak ada shift produksi pada tanggal tersebut.</div>
        @endif
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Siap diproduksi</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Permintaan</th>
                        <th>Target menu</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td><strong>{{ $request->number }}</strong></td>
                            <td>
                                @foreach($request->items as $item)
                                    <div>{{ $item->product?->title }} · {{ \App\Support\DisplayNumber::format((float)$item->quantity, 3, ',', '.') }}</div>
                                @endforeach
                            </td>
                            <td><span class="badge bg-light-success text-success">{{ str($request->status)->headline() }}</span></td>
                            <td class="text-end">
                                @can('production-orders-create')
                                    <button class="btn btn-sm btn-primary" wire:click="start({{ $request->id }})" wire:confirm="Mulai produksi? Kebutuhan bahan akan diperiksa ulang dan langsung dipotong dari stok gudang.">Mulai produksi</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada permintaan yang siap diproduksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Production order</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Menu</th>
                        <th>Target / aktual</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <strong>{{ $order->number }}</strong>
                                <div class="small text-muted">{{ $order->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>
                                @foreach($order->items as $item)
                                    <div>{{ $item->product?->title }}</div>
                                @endforeach
                            </td>
                            <td>
                                @foreach($order->items as $item)
                                    <div>{{ \App\Support\DisplayNumber::format((float)$item->planned_quantity, 3, ',', '.') }} / {{ \App\Support\DisplayNumber::format((float)$item->produced_quantity, 3, ',', '.') }}</div>
                                @endforeach
                            </td>
                            <td>
                                <span class="badge {{ $order->status === 'completed' ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">
                                    {{ str($order->status)->replace('_', ' ')->headline() }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if($order->status === 'in_production')
                                    @can('production-orders-complete')
                                        <button class="btn btn-sm btn-primary" wire:click="openComplete({{ $order->id }})">Catat hasil</button>
                                    @endcan
                                @else
                                    <div class="small text-muted">
                                        @foreach($order->items as $item)
                                            HPP aktual: Rp {{ \App\Support\DisplayNumber::format((float)$item->unit_cost, 2, ',', '.') }}
                                            @if(!$loop->last)<br>@endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada production order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showForm)
        <div class="modal-backdrop show" style="z-index:1040" wire:click="$set('showForm', false)"></div>
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="production-modal-title" style="z-index:1050;">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                
                <!-- PERUBAHAN ADA DI SINI: Tag <form> kini bertindak sebagai .modal-content -->
                <form wire:submit="complete" class="modal-content">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="production-modal-title">Konsumsi bahan &amp; hasil aktual</h5>
                        <button type="button" class="btn-close" wire:click="$set('showForm', false)" aria-label="Tutup"></button>
                    </div>
                    
                    <!-- modal-body akan otomatis bisa discroll karena sudah menjadi anak langsung dari form.modal-content -->
                    <div class="modal-body" style="overflow-y: auto;">
                        <h6>Konsumsi bahan aktual</h6>
                        <p class="small text-muted">Sisa yang tidak dipakai akan dikembalikan ke stok tersedia.</p>
                        @foreach($materialLines as $index => $line)
                            <div class="row g-2 align-items-end mb-2" wire:key="production-material-{{ $line['production_order_material_id'] }}">
                                <div class="col-7">
                                    <label class="form-label" for="actual-material-{{ $index }}">{{ $line['name'] }} ({{ $line['unit'] }})</label>
                                    <div class="small text-muted">Rencana {{ \App\Support\DisplayNumber::format((float)$line['planned_quantity'], 3, ',', '.') }}</div>
                                </div>
                                <div class="col-5">
                                    <input id="actual-material-{{ $index }}" type="number" min="0" step="0.001" wire:model="materialLines.{{ $index }}.actual_quantity" class="form-control" required aria-label="Konsumsi aktual {{ $line['name'] }}">
                                </div>
                            </div>
                        @endforeach
                        
                        <hr>
                        
                        <h6>Hasil jadi aktual</h6>
                        @foreach($outputLines as $index => $line)
                            <div class="row g-2 align-items-end mb-3" wire:key="production-output-{{ $line['production_order_item_id'] }}">
                                <div class="col-7">
                                    <label class="form-label" for="actual-output-{{ $index }}">{{ $line['name'] }} ({{ $line['unit'] }})</label>
                                    <div class="small text-muted">Target {{ \App\Support\DisplayNumber::format((float)$line['planned_quantity'], 3, ',', '.') }}</div>
                                </div>
                                <div class="col-5">
                                    <input id="actual-output-{{ $index }}" type="number" min="0" step="0.001" wire:model="outputLines.{{ $index }}.actual_output" class="form-control" required aria-label="Output aktual {{ $line['name'] }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="variance-note-{{ $index }}">Catatan selisih bila hasil berbeda</label>
                                    <input id="variance-note-{{ $index }}" wire:model="outputLines.{{ $index }}.variance_note" class="form-control" maxlength="2000">
                                </div>
                            </div>
                        @endforeach
                        @error('materials')<div class="small text-danger">{{ $message }}</div>@enderror 
                        @error('outputs')<div class="small text-danger">{{ $message }}</div>@enderror
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-secondary" wire:click="$set('showForm', false)">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Selesaikan &amp; tambah stok jadi</button>
                    </div>
                    
                </form>
            </div>
        </div>
    @endif
</div>