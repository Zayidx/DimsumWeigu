<div>
    <div class="mb-3"><p class="text-muted mb-0">Target, hasil aktual, dan pemakaian bahan produksi di Gudang Bersama.</p></div>
    <div class="card mb-3"><div class="card-body"><div class="row g-2">
        <div class="col-6 col-lg-2"><label class="form-label" for="report-from">Dari</label><input id="report-from" type="date" wire:model.live="fromDate" class="form-control"></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="report-to">Sampai</label><input id="report-to" type="date" wire:model.live="toDate" class="form-control"></div>
        <div class="col-12 col-lg-3"><label class="form-label" for="report-product">Produk</label><select id="report-product" wire:model.live="productId" class="form-select"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->title }}</option>@endforeach</select></div>
        <div class="col-6 col-lg-3"><label class="form-label" for="report-shift">Shift</label><select id="report-shift" wire:model.live="shiftId" class="form-select"><option value="">Semua shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">#{{ $shift->id }} · {{ $shift->user?->name }} · {{ $shift->opened_at?->format('d/m/Y') }}</option>@endforeach</select></div>
        <div class="col-6 col-lg-2"><label class="form-label" for="report-status">Status</label><select id="report-status" wire:model.live="status" class="form-select"><option value="">Semua</option><option value="in_production">Sedang diproduksi</option><option value="completed">Selesai</option></select></div>
    </div></div></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Order / waktu</th><th>Shift / petugas</th><th>Produk</th><th>Target</th><th>Aktual</th><th>Selisih / hasil</th><th>Bahan terpakai / catatan</th></tr></thead><tbody>
        @forelse($orders as $order)
            @foreach($order->items as $item)
                @if($productId === '' || (int) $productId === $item->product_id)
                    <tr><td><strong>{{ $order->number }}</strong><div class="small text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</div><span class="badge {{ $order->status === 'completed' ? 'bg-light-success text-success' : 'bg-light-warning text-warning' }}">{{ $order->status === 'completed' ? 'Selesai' : 'Sedang diproduksi' }}</span></td>
                        <td>#{{ $order->production_shift_id }}<div class="small text-muted">{{ $order->shift?->user?->name }}</div></td>
                        <td>{{ $item->product?->title }}</td>
                        <td>{{ \App\Support\DisplayNumber::format((float) $item->planned_quantity, 3, ',', '.') }} {{ $item->product?->unit?->symbol }}</td>
                        <td>{{ \App\Support\DisplayNumber::format((float) $item->produced_quantity, 3, ',', '.') }}</td>
                        <td>{{ $item->variance_quantity === null ? '—' : \App\Support\DisplayNumber::format((float) $item->variance_quantity, 3, ',', '.') }}<div class="small text-muted">{{ $item->yield_percent === null ? '—' : $item->yield_percent.'%' }}</div></td>
                        <td>@foreach($item->materials as $material)<div class="small">{{ $material->ingredient?->name }}: {{ \App\Support\DisplayNumber::format((float) $material->actual_quantity, 3, ',', '.') }} {{ $material->ingredient?->unit?->symbol }}</div>@endforeach @if($item->variance_note)<div class="small text-muted mt-1">{{ $item->variance_note }}</div>@endif</td></tr>
                @endif
            @endforeach
        @empty<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada produksi pada filter ini.</td></tr>@endforelse
    </tbody></table></div>@if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif</div>
</div>
