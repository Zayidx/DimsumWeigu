<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#435ebe">
    <title>@yield('page-title', $title ?? 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/extensions/bootstrap-icons/font/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard-mobile.css') }}?v={{ filemtime(public_path('assets/dashboard-mobile.css')) }}">
    @livewireStyles
    <script src="{{ asset('mazer/static/js/initTheme.js') }}"></script>
</head>
<body class="workspace-shell {{ request()->routeIs('pos.index') ? 'workspace-pos' : '' }}">
    <a class="skip-link" href="#workspace-content">Langsung ke isi halaman</a>
    @php
        $workspaceOutlets = $outlets ?? app(\App\Support\OutletAccess::class)->outletsFor(auth()->user())->get(['id', 'name']);
        $workspaceOutlet = $workspaceOutlets->firstWhere('id', (int) session('active_outlet_id'));
    @endphp
    <div id="app">
        <aside id="sidebar" aria-label="Menu ruang kerja">
            <div class="sidebar-wrapper">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <a href="{{ route('home') }}" class="workspace-brand"><img src="{{ asset('assets/logo/logo-no-background.png') }}" alt="Weigu"><span>{{ config('app.name') }}<small>Ruang kerja</small></span></a>
                        <button type="button" class="sidebar-hide btn btn-light d-xl-none" aria-label="Tutup menu"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="sidebar-menu">
                    @hasSection('sidebar') @yield('sidebar') @else @include('layouts.partials.sidebar') @endif
                </div>
            </div>
        </aside>
        <div id="main" class="layout-navbar navbar-fixed">
            <header class="workspace-header">
                <nav class="workspace-topbar" aria-label="Menu akun">
                    <div class="workspace-heading"><span class="small text-muted">Ruang kerja</span><strong>@yield('page-title', $title ?? 'Dashboard')</strong></div>
                    <div class="workspace-account">
                        @can('notifications-access')
                            @php($unreadNotifications = \App\Models\OperationalNotification::query()->whereNull('read_at')->where(function ($scope) { $scope->where(fn ($personal) => $personal->where('user_id', auth()->id())->where(fn ($outlet) => $outlet->whereNull('outlet_id')->orWhere('outlet_id', session('active_outlet_id'))))->orWhere(fn ($shared) => $shared->whereNull('user_id')->where('outlet_id', session('active_outlet_id'))); })->count())
                            <a class="btn btn-light position-relative" href="{{ route('notifications.index') }}" aria-label="Notifikasi, {{ $unreadNotifications }} belum dibaca"><i class="bi bi-bell" aria-hidden="true"></i>@if($unreadNotifications)<span class="workspace-notification-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif</a>
                        @endcan
                        <details class="workspace-account-menu">
                            <summary class="btn btn-light" aria-label="Menu profil dan keluar"><i class="bi bi-person-circle" aria-hidden="true"></i><span class="d-none d-md-inline">{{ auth()->user()->name }}</span><i class="bi bi-chevron-down small" aria-hidden="true"></i></summary>
                            <div class="workspace-account-options">
                                <strong>{{ auth()->user()->name }}</strong>
                                <a href="{{ route('profile.edit') }}"><i class="bi bi-person" aria-hidden="true"></i>Profil saya</a>
                                <button type="button" data-theme-toggle><i class="bi bi-moon-stars" aria-hidden="true"></i>Ganti tema</button>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-danger"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Keluar</button></form>
                            </div>
                        </details>
                    </div>
                </nav>
                @if($workspaceOutlets->isNotEmpty())
                    <form method="POST" action="{{ route('outlet.switch') }}" class="workspace-outlet-switcher">
                        @csrf
                        <label for="active-outlet"><i class="bi bi-shop" aria-hidden="true"></i><span>Outlet aktif</span></label>
                        <select id="active-outlet" name="outlet_id" class="form-select" @if($workspaceOutlets->count() === 1) aria-readonly="true" @endif>
                            @foreach($workspaceOutlets as $option)<option value="{{ $option->id }}" @selected((int) session('active_outlet_id') === $option->id)>{{ $option->name }}</option>@endforeach
                        </select>
                        @if($workspaceOutlets->count() > 1)<button class="btn btn-outline-primary" type="submit">Ganti</button>@endif
                    </form>
                @endif
            </header>
            <main id="workspace-content" class="content-wrapper container-fluid" tabindex="-1">
                <div class="page-heading">
                    <h1 class="workspace-page-title">@yield('page-title', $title ?? 'Dashboard')</h1>
                    @hasSection('subtitle')<p class="text-subtitle text-muted">@yield('subtitle')</p>@endif
                </div>
                <div class="workspace-status" role="status" aria-live="polite" data-workspace-status hidden></div>
                @isset($slot) {{ $slot }} @else @yield('content') @endisset
            </main>
            <footer class="workspace-footer"><span>{{ now()->year }} · {{ config('app.name') }}</span><span>v{{ config('app.version') }}</span></footer>
        </div>
    </div>
    @include('layouts.partials.mobile-navigation')
    <div class="workspace-toast-region" aria-live="polite" aria-atomic="true" data-toast-region></div>
    <script src="{{ asset('mazer/compiled/js/app.js') }}"></script>
    <script src="{{ asset('assets/dashboard-mobile.js') }}?v={{ filemtime(public_path('assets/dashboard-mobile.js')) }}" defer></script>
    @livewireScripts
    @stack('scripts')
</body>
</html>
