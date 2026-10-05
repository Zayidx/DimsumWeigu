<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#a84127">
    <title>{{ $title ?? 'Kasir' }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/extensions/bootstrap-icons/font/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\StaticAssets::url('pos.css') }}">
    @livewireStyles
</head>
<body class="pos-shell">
    <a class="pos-skip-link" href="#pos-content">Langsung ke kasir</a>
    @php($posOutlets = app(\App\Support\OutletAccess::class)->outletsFor(auth()->user())->get(['id', 'name']))
    <header class="pos-topbar">
        <a class="pos-brand" href="{{ route('home') }}">
            <img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="{{ config('app.name') }}">
            <span><strong>{{ config('app.name') }}</strong><small>Kasir</small></span>
        </a>

        <div class="pos-topbar-actions">
            @if($posOutlets->count() > 1)
                <form method="POST" action="{{ route('outlet.switch') }}" class="pos-outlet-form">
                    @csrf
                    <label for="pos-active-outlet"><i class="bi bi-shop" aria-hidden="true"></i><span class="visually-hidden">Outlet aktif</span></label>
                    <select id="pos-active-outlet" name="outlet_id" class="form-select" aria-label="Outlet aktif">
                        @foreach($posOutlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((int) session('active_outlet_id') === $outlet->id)>{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Ganti</button>
                </form>
            @else
                <span class="pos-outlet-label"><i class="bi bi-shop" aria-hidden="true"></i>{{ $posOutlets->first()?->name ?? 'Outlet aktif' }}</span>
            @endif

            @can('transactions-access')
                <a class="pos-history-link" href="{{ route('transactions.index') }}"><i class="bi bi-receipt" aria-hidden="true"></i><span>Riwayat</span></a>
            @endcan
            <details class="pos-account-menu">
                <summary aria-label="Menu akun"><span class="pos-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="pos-account-name">{{ auth()->user()->name }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                <div class="pos-account-options">
                    <strong>{{ auth()->user()->name }}</strong>
                    <a href="{{ route('profile.edit') }}"><i class="bi bi-person" aria-hidden="true"></i>Profil saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Keluar</button></form>
                </div>
            </details>
        </div>
    </header>

    <main id="pos-content" class="pos-main" tabindex="-1">
        <div class="pos-page-heading">
            <div>
                <span class="pos-eyebrow"><i class="bi bi-egg-fried" aria-hidden="true"></i> {{ config('app.name') }}</span>
                <h1>{{ $title ?? 'Kasir' }}</h1>
                <p>Pilih menu, susun pesanan, lalu lanjutkan pembayaran.</p>
            </div>
            @can('cashier-shifts-access')
                <a href="{{ route('shifts.index') }}" class="pos-shift-link"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Shift kasir</span></a>
            @endcan
        </div>

        <div class="pos-connection-status" data-pos-status role="status" aria-live="polite" hidden></div>
        {{ $slot }}
    </main>

    <div class="pos-toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>
    <script src="{{ \App\Support\StaticAssets::url('pos.js') }}" defer></script>
    @livewireScripts
</body>
</html>
