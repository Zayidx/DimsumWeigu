<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Akun' }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/extensions/bootstrap-icons/font/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\StaticAssets::url('dashboard-mobile.css') }}">
    <script src="{{ asset('mazer/static/js/initTheme.js') }}"></script>
</head>
<body class="workspace-auth">
    <div id="auth">{{ $slot }}</div>
</body>
</html>
