@extends('layouts.dashboard')

@section('page-title', $roleTitle)
@section('subtitle', $roleDescription.' · '.$outlet->name)

@section('content')
    @if (in_array($role, ['warehouse', 'finance'], true) && $availableWarehouses->count() > 1)
        <form method="get" action="{{ route('dashboard') }}" class="card dashboard-filter">
            <div class="card-body d-flex flex-wrap align-items-end gap-3">
                <div class="flex-grow-1">
                    <label for="dashboard-warehouse" class="form-label">Lokasi data</label>
                    <select id="dashboard-warehouse" name="warehouse" class="form-select">
                        <option value="">Semua gudang · {{ $outlet->name }}</option>
                        @foreach ($availableWarehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($selectedWarehouse?->id === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-outline-primary">Terapkan lokasi</button>
            </div>
        </form>
    @endif

    @if ($metrics->isNotEmpty())
        <div class="row g-3 dashboard-role-metrics">
            @foreach ($metrics as $metric)
                <div class="col-12 col-sm-6 col-xxl-3">
                    <section class="card h-100" aria-label="{{ $metric['label'] }}">
                        <div class="card-body dashboard-role-metric">
                            <span class="dashboard-metric-label">{{ $metric['label'] }}</span>
                            <strong>{{ $metric['value'] }}</strong>
                            <small>{{ $metric['detail'] }}</small>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
    @endif

    @if ($actions->isNotEmpty() || $workItems->isNotEmpty())
        <div class="row g-3">
            @if ($actions->isNotEmpty())
                <div class="col-12 {{ $workItems->isNotEmpty() ? 'col-xl-5' : '' }}">
                    <section class="card h-100" aria-labelledby="role-actions-title">
                        <div class="card-header"><h2 id="role-actions-title" class="card-title mb-0">Aksi untuk Anda</h2></div>
                        <div class="card-body dashboard-role-actions">
                            @foreach ($actions as $action)
                                <a href="{{ $action['url'] }}" class="btn btn-outline-primary"><i class="bi {{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}</a>
                            @endforeach
                        </div>
                    </section>
                </div>
            @endif
            @if ($workItems->isNotEmpty())
                <div class="col-12 {{ $actions->isNotEmpty() ? 'col-xl-7' : '' }}">
                    <section class="card h-100" aria-labelledby="role-work-title">
                        <div class="card-header"><h2 id="role-work-title" class="card-title mb-0">Pekerjaan yang perlu dicek</h2></div>
                        <div class="card-body dashboard-role-list">
                            @foreach ($workItems as $item)
                                <a href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><strong>{{ number_format($item['count']) }}</strong></a>
                            @endforeach
                        </div>
                    </section>
                </div>
            @endif
        </div>
    @endif

    @if ($role === 'cashier' && $recentTransactions->isNotEmpty())
        <section class="card" aria-labelledby="cashier-recent-title">
            <div class="card-header"><h2 id="cashier-recent-title" class="card-title mb-0">Transaksi saya terbaru</h2></div>
            <div class="card-body dashboard-role-list">
                @foreach ($recentTransactions as $transaction)
                    <a href="{{ route('transactions.show', $transaction) }}">
                        <span>{{ $transaction->number }}<small class="d-block text-muted">{{ $transaction->occurred_at?->format('d M Y, H:i') }}</small></span>
                        <strong>Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</strong>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($metrics->isEmpty() && $actions->isEmpty())
        <div class="alert alert-light-info" role="status">Belum ada ringkasan untuk izin akun ini. Hubungi pengelola akses jika Anda memerlukan fitur lain.</div>
    @endif
@endsection
