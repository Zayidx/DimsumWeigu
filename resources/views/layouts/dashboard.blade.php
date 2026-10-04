<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/iconly.css') }}">
    @livewireStyles
    <script src="{{ asset('mazer/static/js/initTheme.js') }}"></script>
</head>
<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="{{ route('home') }}" class="text-decoration-none">{{ config('app.name') }}</a>
                            <button type="button" class="sidebar-toggler sidebar-hide btn btn-link d-xl-none" aria-label="Tutup menu">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="sidebar-menu">
                    @yield('sidebar')
                </div>
            </div>
        </div>
        <div id="main" class="layout-navbar navbar-fixed">
            <header class="mb-3">
                <nav class="navbar navbar-expand navbar-light navbar-top">
                    <div class="container-fluid">
                        <button type="button" class="burger-btn d-block btn btn-link" aria-label="Buka menu">
                            <i class="bi bi-justify fs-3"></i>
                        </button>
                        <div class="ms-auto d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-link" aria-label="Ganti tema" onclick="toggleDarkTheme()">
                                <i class="bi bi-moon-stars-fill"></i>
                            </button>
                            @auth
                                <span class="small text-muted">{{ auth()->user()->name }}</span>
                            @endauth
                        </div>
                    </div>
                </nav>
            </header>
            <main class="content-wrapper container-fluid">
                <div class="page-heading">
                    <div class="page-title">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-6 order-md-1 order-last">
                                <h3>{{ $title ?? 'Dashboard' }}</h3>
                                @hasSection('subtitle')<p class="text-subtitle text-muted">@yield('subtitle')</p>@endif
                            </div>
                            <div class="col-12 col-md-6 order-md-2 order-first">
                                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                    <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li><li class="breadcrumb-item active" aria-current="page">{{ $title ?? 'Dashboard' }}</li></ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                @yield('content')
            </main>
            <footer><div class="footer clearfix mb-0 text-muted"><div class="float-start"><p>{{ now()->year }} · {{ config('app.name') }}</p></div></div></footer>
        </div>
    </div>
    <script src="{{ asset('mazer/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('mazer/compiled/js/app.js') }}"></script>
    <script src="{{ asset('mazer/static/js/components/dark.js') }}"></script>
    <script src="{{ asset('mazer/static/js/components/sidebar.js') }}"></script>
    @livewireScripts
</body>
</html>
