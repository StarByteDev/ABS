@extends('layouts.auth')
@section('title', 'Member Portal — Alpha Block Solutions')
@section('content')
@php($isPulseLogin = ($service ?? null) === 'pulse')
@php($isPrivateLogin = ($service ?? null) === 'private')

<form id="auth-form" class="auth-card auth-login-card premium-login-form" method="POST" action="{{ route('login.store') }}" novalidate>
    @csrf
    @if($service)<input type="hidden" name="service" value="{{ $service }}">@endif
    @if($plan)<input type="hidden" name="plan" value="{{ $plan }}">@endif

    <header class="auth-card__header">
        <p class="auth-eyebrow">MEMBER PORTAL</p>
        <h2>Welcome back</h2>
        <p>Sign in to continue to Alpha Block Solutions.</p>
    </header>

    @if(session('status'))
        <div class="auth-message auth-message--success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="auth-message auth-message--error" role="alert">
            <strong>We couldn’t sign you in.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="auth-field">
        <label for="email">Email Address</label>
        <div class="auth-input-wrap @error('email') is-invalid @enderror">
            <span class="auth-input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
            </span>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        </div>
    </div>

    <div class="auth-field">
        <label for="password">Password</label>
        <div class="auth-input-wrap @error('password') is-invalid @enderror">
            <span class="auth-input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
            </span>
            <input id="password" type="password" name="password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
            <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                <svg class="auth-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                <svg class="auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2c.5-.1.9-.2 1.4-.2 6 0 9.5 6 9.5 6a17 17 0 0 1-2.2 2.8M6.3 6.3A17.2 17.2 0 0 0 2.5 12S6 18 12 18c1.5 0 2.8-.4 3.9-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        </div>
    </div>

    <div class="auth-options">
        <label class="auth-checkbox" for="remember">
            <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span aria-hidden="true"></span>
            <b>Remember me</b>
        </label>
        <a href="{{ route('password.request') }}">Forgot password?</a>
    </div>

    <button class="auth-submit" type="submit" data-busy-label="Signing in securely…">
        <span>{{ $isPrivateLogin ? 'Open Member Portal' : ($isPulseLogin ? 'Open Pulse' : 'Sign In') }}</span>
    </button>

    <div class="auth-divider" aria-hidden="true"><span></span><i></i><span></span></div>

    @unless($isPrivateLogin)
        <p class="auth-register">New to Alpha Block Solutions? <a href="{{ route('register', $isPulseLogin ? array_filter(['service' => 'pulse', 'plan' => $plan ?? null]) : []) }}">Create an account</a></p>
    @else
        <p class="auth-register">Need access assistance? <a href="mailto:{{ config('brand.support_email') }}">Contact Support</a></p>
    @endunless

    <div class="auth-card__security">
        <span aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5.1-3.4 8.5-8 10-4.6-1.5-8-4.9-8-10V6l8-3Z"/><rect x="9" y="10" width="6" height="6" rx="1"/><path d="M10.5 10V8.8a1.5 1.5 0 0 1 3 0V10"/></svg>
        </span>
        <p>Your connection is protected and encrypted.</p>
    </div>
</form>
@endsection
