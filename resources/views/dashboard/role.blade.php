@extends('layouts.dashboard')

@section('page-title', $roleTitle)
@section('subtitle', $roleDescription.' · '.($role === 'cashier' ? $outlet->name : $scopeLabel))

@section('content')
    @if (in_array($role, ['manager', 'warehouse', 'finance'], true) && ($outlets->count() > 1 || $availableWarehouses->count() > 1))
        <form method="get" action="{{ route('dashboard') }}" class="card dashboard-filter">
            <div class="card-body d-flex flex-wrap align-items-end gap-3">
                @if ($outlets->count() > 1)
                    <div class="flex-grow-1"><label for="dashboard-outlet" class="form-label">Outlet</label><select id="dashboard-outlet" name="outlet" class="form-select"><option value="">Semua outlet</option>@foreach ($outlets as $option)<option value="{{ $option->id }}" @selected($selectedOutlet?->id === $option->id)>{{ $option->name }}</option>@endforeach</select></div>
                @endif
                <div class="flex-grow-1">
                    <label for="dashboard-warehouse" class="form-label">Lokasi data</label>
                    <select id="dashboard-warehouse" name="warehouse" class="form-select">
                        <option value="">Semua gudang</option>
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
                                <a href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><strong>{{ \App\Support\DisplayNumber::format($item['count']) }}</strong></a>
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
                        <strong>Rp{{ \App\Support\DisplayNumber::format($transaction->grand_total, 0, ',', '.') }}</strong>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($metrics->isEmpty() && $actions->isEmpty())
        <div class="alert alert-light-info" role="status">Belum ada ringkasan untuk izin akun ini. Hubungi pengelola akses jika Anda memerlukan fitur lain.</div>
    @endif
@endsection
