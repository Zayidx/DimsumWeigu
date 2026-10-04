<div>
    <form wire:submit.prevent="applyFilters" class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2"><label class="form-label" for="report-from">Dari</label><input id="report-from" type="date" wire:model="from" class="form-control">@error('from')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-6 col-md-2"><label class="form-label" for="report-to">Sampai</label><input id="report-to" type="date" wire:model="to" class="form-control">@error('to')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-12 col-md-3"><label class="form-label" for="report-warehouse">Gudang</label><select id="report-warehouse" wire:model="warehouseId" class="form-select"><option value="">Semua gudang outlet</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
                <div class="col-12 col-md-3"><label class="form-label" for="report-cashier">Kasir</label><select id="report-cashier" wire:model="cashierId" class="form-select"><option value="">Semua kasir</option>@foreach($cashiers as $cashier)<option value="{{ $cashier->id }}">{{ $cashier->name }}</option>@endforeach</select></div>
                <div class="col-12 col-md-2"><label class="form-label" for="report-method">Pembayaran</label><select id="report-method" wire:model="method" class="form-select"><option value="">Semua metode</option><option value="cash">Tunai</option><option value="bank-transfer">Transfer</option><option value="qris">QRIS</option><option value="pay-later">Piutang</option></select></div>
                <div class="col-12 d-flex justify-content-between align-items-center mt-2"><span class="text-muted">{{ $outlet->name }} · transaksi selesai · maksimal rentang 12 bulan</span><div><button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>@can('reports-export')<button type="button" wire:click="export" class="btn btn-outline-primary ms-1"><i class="bi bi-download me-1"></i>CSV</button>@endcan</div></div>
            </div>
        </div>
    </form>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Nilai transaksi</div><h4 class="mb-0">Rp {{ number_format($totalSales,0,',','.') }}</h4></div></div></div>
        <div class="col-12 col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Transaksi</div><h4 class="mb-0">{{ number_format($totalTransactions,0,',','.') }}</h4></div></div></div>
        <div class="col-12 col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Transaksi lunas</div><h4 class="mb-0">Rp {{ number_format($paidSales,0,',','.') }}</h4></div></div></div>
    </div>
    <div class="card"><div class="card-header"><h5 class="card-title mb-0">Rincian penjualan</h5></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nomor / waktu</th><th>Kasir</th><th>Gudang</th><th>Pelanggan</th><th>Metode</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td><a href="{{ route('transactions.show',$row) }}" class="fw-bold">{{ $row->number }}</a><div class="small text-muted">{{ $row->occurred_at?->format('d/m/Y H:i') }}</div></td><td>{{ $row->user?->name ?? '—' }}</td><td>{{ $row->warehouse?->name ?? '—' }}</td><td>{{ $row->customer?->name ?? 'Pelanggan umum' }}</td><td>{{ $row->tenders->pluck('method')->unique()->join(', ') ?: '—' }}</td><td><span class="badge {{ $row->payment_status==='paid'?'bg-light-success text-success':'bg-light-warning text-warning' }}">{{ str($row->payment_status)->headline() }}</span></td><td class="text-end fw-semibold">Rp {{ number_format($row->grand_total,0,',','.') }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-5">Tidak ada transaksi untuk filter ini.</td></tr>@endforelse
    </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div>
