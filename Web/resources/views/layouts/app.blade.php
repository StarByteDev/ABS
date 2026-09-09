<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Alpha Block Solutions — Pulse trading intelligence, live market awareness and invitation-only private member reporting.">
    <meta name="theme-color" content="#07111f">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title', 'Alpha Block Solutions')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/abs-app.css') }}?v={{ @filemtime(public_path('assets/css/abs-app.css')) ?: '14.4' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-home-final.css') }}?v={{ @filemtime(public_path('assets/css/abs-home-final.css')) ?: '14.4' }}">
    @stack('head')
</head>
<body class="abs-body" data-home-page="{{ request()->routeIs('home') ? '1' : '0' }}">
    <div class="ambient ambient-one"></div><div class="ambient ambient-two"></div>
    @include('partials.header')
    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="flash flash-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="flash flash-error"><strong>Please review:</strong> {{ $errors->first() }}</div>@endif
    <main>@yield('content')</main>
    @include('partials.footer')
    @stack('scripts')
    <script src="{{ asset('assets/js/abs-app.js') }}?v={{ @filemtime(public_path('assets/js/abs-app.js')) ?: '14.4' }}" defer></script>
</body>
</html>
