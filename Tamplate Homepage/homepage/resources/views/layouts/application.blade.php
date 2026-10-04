<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\StaticAssets::url('application.css') }}">
    @livewireStyles
    <script src="{{ \App\Support\StaticAssets::url('theme.js') }}"></script>
</head>
<body class="font-sans antialiased bg-slate-50 transition-colors duration-200">
    {{ $slot }}
    @stack('page-scripts')
    @livewireScripts
</body>
</html>
