<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#805945">
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
    <div id="app">
        @include('layouts.partials.sidebar')
        <div id="main" class="layout-navbar navbar-fixed">
            <main id="workspace-content" class="content-wrapper container-fluid {{ request()->routeIs('dashboard') ? 'dashboard-page' : '' }}" tabindex="-1">
                <div class="page-heading">
                    <div class="workspace-page-heading-row">
                        <button type="button" class="burger-btn workspace-menu-toggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list" aria-hidden="true"></i></button>
                        <h1 class="workspace-page-title">@yield('page-title', $title ?? 'Dashboard')</h1>
                    </div>
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
