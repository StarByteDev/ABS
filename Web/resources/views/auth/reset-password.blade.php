@extends('layouts.auth')
@section('title', 'Choose New Password — Alpha Block Solutions')
@section('content')
<form id="auth-form" class="auth-card auth-recovery-card" method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <header class="auth-card__header">
        <p class="auth-eyebrow">ACCOUNT SECURITY</p>
        <h2>Choose a new password</h2>
        <p>Use a strong password that you don’t use for any other account.</p>
    </header>

    @if($errors->any())
        <div class="auth-message auth-message--error" role="alert">
            <strong>We couldn’t update the password.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="auth-field">
        <label for="email">Email Address</label>
        <div class="auth-input-wrap @error('email') is-invalid @enderror">
            <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
        </div>
    </div>

    <div class="auth-field">
        <label for="password">New Password</label>
        <div class="auth-input-wrap @error('password') is-invalid @enderror">
            <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
            <input id="password" type="password" name="password" autocomplete="new-password" placeholder="Create a strong password" required autofocus>
            <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                <svg class="auth-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                <svg class="auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M2.5 12S6 6 12 6m9.5 6s-3.5 6-9.5 6"/></svg>
            </button>
        </div>
        <p class="auth-field-help">Minimum 8 characters with uppercase, lowercase and a number.</p>
    </div>

    <div class="auth-field">
        <label for="password_confirmation">Confirm New Password</label>
        <div class="auth-input-wrap">
            <span class="auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Repeat your new password" required>
            <button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                <svg class="auth-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                <svg class="auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M2.5 12S6 6 12 6m9.5 6s-3.5 6-9.5 6"/></svg>
            </button>
        </div>
    </div>

    <button class="auth-submit" type="submit" data-busy-label="Updating password…">Update Password</button>
    <a class="auth-flow-link" href="{{ route('login') }}"><span aria-hidden="true">&#8592;</span> Back to sign in</a>
</form>
@endsection
