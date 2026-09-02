@extends('layouts.auth')
@section('title', 'Account Recovery — Alpha Block Solutions')
@section('body-class', 'abs-recovery-page')
@section('auth-skip-label', 'Skip to account recovery')
@section('auth-panel-label', 'Recover your Alpha Block Solutions account')
@section('content')
@php($activeRecoveryMode = old('recovery_mode', $recoveryMode ?? 'password'))
<section id="auth-form" class="auth-card auth-recovery-card" aria-labelledby="recovery-title">
    <header class="auth-card__header">
        <p class="auth-eyebrow">ACCOUNT RECOVERY</p>
        <h2 id="recovery-title">Recover your account</h2>
        <p>Choose what you need help recovering.</p>
    </header>

    @if(session('status'))
        <div class="auth-message auth-message--success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="auth-message auth-message--error" role="alert">
            <strong>Please check the recovery details.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="auth-recovery-tabs" role="tablist" aria-label="Recovery options">
        <button id="recovery-password-tab" class="auth-recovery-tab" type="button" role="tab" data-recovery-tab="password" aria-controls="recovery-password-panel" aria-selected="{{ $activeRecoveryMode === 'password' ? 'true' : 'false' }}">Password</button>
        <button id="recovery-identifier-tab" class="auth-recovery-tab" type="button" role="tab" data-recovery-tab="identifier" aria-controls="recovery-identifier-panel" aria-selected="{{ $activeRecoveryMode === 'identifier' ? 'true' : 'false' }}">Username / Email</button>
    </div>

    <form id="recovery-password-panel" class="auth-recovery-panel" method="POST" action="{{ route('password.email') }}" role="tabpanel" aria-labelledby="recovery-password-tab" data-recovery-panel="password" @if($activeRecoveryMode !== 'password') hidden @endif novalidate>
        @csrf
        <input type="hidden" name="recovery_mode" value="password">
        <div class="auth-field">
            <label for="recovery_identifier">Email Address or Username</label>
            <div class="auth-input-wrap @error('recovery_identifier') is-invalid @enderror">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                </span>
                <input id="recovery_identifier" type="text" name="recovery_identifier" value="{{ old('recovery_identifier') }}" maxlength="255" autocomplete="username" required @if($activeRecoveryMode === 'password') autofocus @endif aria-invalid="{{ $errors->has('recovery_identifier') ? 'true' : 'false' }}">
            </div>
            <p class="auth-recovery-help">We’ll send secure recovery instructions to your registered email.</p>
        </div>
        <button class="auth-submit" type="submit" data-busy-label="Sending recovery link…"><span>Send Recovery Link</span></button>
    </form>

    <form id="recovery-identifier-panel" class="auth-recovery-panel" method="POST" action="{{ route('account.identifier.email') }}" role="tabpanel" aria-labelledby="recovery-identifier-tab" data-recovery-panel="identifier" @if($activeRecoveryMode !== 'identifier') hidden @endif novalidate>
        @csrf
        <input type="hidden" name="recovery_mode" value="identifier">
        <div class="auth-field">
            <label for="recovery_phone">Registered Phone or WhatsApp Number</label>
            <div class="auth-phone-group @error('country_code') is-invalid @enderror @error('phone') is-invalid @enderror">
                <div class="auth-input-wrap auth-country-code-wrap">
                    <input id="recovery_country_code" type="tel" name="country_code" value="{{ old('country_code') }}" maxlength="8" autocomplete="tel-country-code" inputmode="tel" placeholder="+971" aria-label="Country calling code" required>
                </div>
                <div class="auth-input-wrap auth-input-wrap--plain auth-phone-number-wrap">
                    <input id="recovery_phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="24" autocomplete="tel-national" inputmode="tel" required aria-invalid="{{ ($errors->has('country_code') || $errors->has('phone')) ? 'true' : 'false' }}">
                </div>
            </div>
            <p class="auth-recovery-help">We’ll send your sign-in email to the email address registered to your ABS account.</p>
        </div>
        <button class="auth-submit" type="submit" data-busy-label="Sending account reminder…"><span>Send Account Reminder</span></button>
    </form>

    <div class="auth-divider" aria-hidden="true"><span></span><i></i><span></span></div>
    <p class="auth-register">Remembered your details? <a href="{{ route('login') }}">Sign in</a></p>

    <div class="auth-card__security">
        <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5.1-3.4 8.5-8 10-4.6-1.5-8-4.9-8-10V6l8-3Z"/><rect x="9" y="10" width="6" height="6" rx="1"/></svg></span>
        <p>Your recovery request is protected and encrypted.</p>
    </div>
</section>
@endsection
