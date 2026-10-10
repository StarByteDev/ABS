<!DOCTYPE html>
<html lang="en">
<head>
    <script>try{document.documentElement.dataset.absTheme=(localStorage.getItem('abs.theme')==='light'?'light':'dark')}catch(e){document.documentElement.dataset.absTheme='dark'}</script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Alpha Block Solutions — Pulse trading intelligence and live market awareness.">
    <meta name="theme-color" content="#07111f">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title', 'Alpha Block Solutions')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/abs-app.css') }}?v={{ @filemtime(public_path('assets/css/abs-app.css')) ?: '14.4' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-home-final.css') }}?v={{ @filemtime(public_path('assets/css/abs-home-final.css')) ?: '14.4' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1580.css') }}?v=15.8.5">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1583.css') }}?v=15.8.5">
    <script src="{{ asset('assets/js/abs-theme-v1587.js') }}?v=15.8.9" defer></script>
    @stack('head')
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1586.css') }}?v=15.8.6">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1587.css') }}?v=15.8.7">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1588.css') }}?v=15.8.8">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1589.css') }}?v=15.8.9">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1590.css') }}?v=15.9.0">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-theme-v1591.css') }}?v=15.9.1">
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
<button type="button" class="abs-theme-toggle" data-abs-theme-toggle aria-label="Switch theme" aria-pressed="true">◐ <span data-abs-theme-label>Switch to Light</span></button>
</body>
</html>
