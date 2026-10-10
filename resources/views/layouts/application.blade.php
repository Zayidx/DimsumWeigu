<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#fffaf4">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset
    <link rel="stylesheet" href="{{ \App\Support\StaticAssets::url('application.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/homepage.css') }}?v={{ filemtime(public_path('assets/homepage.css')) }}">
    @livewireStyles
</head>
<body class="font-sans antialiased bg-slate-50 transition-colors duration-200">
    {{ $slot }}
    @stack('page-scripts')
    @livewireScripts
</body>
</html>
