@extends('layouts.dashboard')

@section('page-title', 'Ringkasan')


@section('content')
    @if (session('status'))<div class="alert alert-light-success alert-dismissible show fade" role="status">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>@endif
    <div class="row g-3 mb-4 dashboard-metrics">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><h6 class="text-muted font-semibold">Penjualan hari ini</h6><h4 class="font-extrabold mb-0">Rp{{ number_format($todaySales, 0, ',', '.') }}</h4></div><div class="stats-icon purple"><i class="bi bi-cash-stack"></i></div></div><p class="text-muted small mt-3 mb-0">{{ $outlet->name }}</p></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><h6 class="text-muted font-semibold">Transaksi hari ini</h6><h4 class="font-extrabold mb-0">{{ number_format($todayTransactions) }}</h4></div><div class="stats-icon blue"><i class="bi bi-receipt-cutoff"></i></div></div><p class="text-muted small mt-3 mb-0">Transaksi selesai pada outlet aktif</p></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><h6 class="text-muted font-semibold">Shift terbuka</h6><h4 class="font-extrabold mb-0">{{ number_format($openShifts) }}</h4></div><div class="stats-icon green"><i class="bi bi-clock-history"></i></div></div><p class="text-muted small mt-3 mb-0">Kasir pada outlet ini</p></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><h6 class="text-muted font-semibold">Stok perlu dicek</h6><h4 class="font-extrabold mb-0">{{ number_format($lowStockCount) }}</h4></div><div class="stats-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div></div><p class="text-muted small mt-3 mb-0">Di bawah reorder point</p></div></div></div>
    </div>

    <div class="row g-4">
        @can('transactions-access')<div class="col-12 col-xl-7"><div class="card"><div class="card-header d-flex align-items-center justify-content-between"><h4 class="card-title mb-0">Transaksi terbaru</h4><a href="{{ Route::has('transactions.index') ? route('transactions.index') : '#' }}" class="btn btn-sm btn-outline-primary" @if (! Route::has('transactions.index')) aria-disabled="true" @endif>Lihat transaksi</a></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nomor</th><th>Pelanggan</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody>
                @forelse ($recentTransactions as $transaction)
                    <tr><td><span class="fw-bold">{{ $transaction->number }}</span><div class="small text-muted">{{ $transaction->occurred_at?->format('d M Y, H:i') }}</div></td><td>{{ $transaction->customer?->name ?? 'Pelanggan umum' }}</td><td><span class="badge bg-light-primary">{{ ucfirst($transaction->payment_status) }}</span></td><td class="text-end fw-semibold">Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center py-5"><i class="bi bi-receipt text-muted fs-2"></i><p class="text-muted mt-2 mb-0">Belum ada transaksi di outlet ini.</p></td></tr>
                @endforelse
            </tbody></table></div></div></div></div>
        @endcan
        <div class="col-12 {{ auth()->user()->can('transactions-access') ? 'col-xl-5' : '' }}"><div class="card"><div class="card-header d-flex align-items-center justify-content-between"><h4 class="card-title mb-0">Perhatian stok</h4>@can('inventory-access') @if (Route::has('inventory.index'))<a href="{{ route('inventory.index') }}" class="small">Buka inventori</a>@endif @endcan</div>
            <div class="card-body"><div class="list-group list-group-flush">
                @forelse ($lowStock as $row)
                    <div class="list-group-item px-0 d-flex align-items-center justify-content-between"><div><div class="fw-semibold">{{ $row->product->title }}</div><div class="small text-muted">{{ $row->product->sku }} · {{ $row->warehouse->name }}</div></div><span class="badge bg-light-danger">{{ number_format((float) $row->stock, 3, ',', '.') }}</span></div>
                @empty
                    <div class="text-center py-4"><i class="bi bi-check-circle text-success fs-2"></i><p class="text-muted mt-2 mb-0">Tidak ada produk di bawah reorder point.</p></div>
                @endforelse
            </div></div></div>
            <div class="card"><div class="card-body d-flex align-items-center justify-content-between"><div><p class="text-muted small mb-1">Shift saya</p>@if ($shift)<strong>Terbuka · {{ $shift->warehouse->name }}</strong>@else<strong>Belum dibuka</strong>@endif</div>@if (Route::has('shifts.create') && auth()->user()->can('cashier-shifts-open'))<a href="{{ ($shift ? route('shifts.index') : route('shifts.create')) }}" class="btn btn-primary btn-sm">{{ $shift ? 'Lihat shift' : 'Buka shift' }}</a>@endif</div></div>
        </div>
    </div>
@endsection
