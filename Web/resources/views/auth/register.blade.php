@extends('layouts.auth')
@section('title', 'Create Your Account — Alpha Block Solutions')
@section('body-class', 'abs-register-page')
@section('auth-skip-label', 'Skip to create account form')
@section('auth-heading-primary', 'One secure account.')
@section('auth-heading-accent', 'Every ABS service.')
@section('auth-intelligence-copy', 'Create your Alpha Block Solutions identity and access the services, intelligence and account features available to you.')
@section('auth-benefit-one', 'Secure ABS Identity')
@section('auth-benefit-two', 'Services in One Place')
@section('auth-benefit-three', 'Pulse Trial Eligibility')
@section('auth-panel-label', 'Create an Alpha Block Solutions account')

@section('content')
@php($isPulseRegistration = ($service ?? null) === 'pulse')

<form id="auth-form" class="auth-card auth-registration-card" method="POST" action="{{ route('register.store') }}" novalidate>
    @csrf
    @if($isPulseRegistration)<input type="hidden" name="service" value="pulse">@endif
    @if($plan)<input type="hidden" name="plan" value="{{ $plan }}">@endif

    <header class="auth-card__header auth-registration-card__header">
        <p class="auth-eyebrow">CREATE YOUR ACCOUNT</p>
        <h2>Join Alpha Block Solutions</h2>
        <p>Set up your secure ABS account. We’ll email you an activation link before your first sign-in.</p>
    </header>

    @if($errors->any())
        <div class="auth-message auth-message--error" role="alert">
            <strong>We couldn’t create your account.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="auth-registration-grid">
        <div class="auth-field">
            <label for="name">Full Name</label>
            <div class="auth-input-wrap auth-input-wrap--plain @error('name') is-invalid @enderror">
                <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required autofocus aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
            </div>
        </div>

        <div class="auth-field">
            <label for="email">Email Address</label>
            <div class="auth-input-wrap auth-input-wrap--plain @error('email') is-invalid @enderror">
                <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" inputmode="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
            </div>
        </div>

        <div class="auth-field">
            <label for="country">Country</label>
            <div class="auth-input-wrap auth-input-wrap--plain auth-select-wrap @error('country') is-invalid @enderror">
                <select id="country" name="country" autocomplete="country-name" required aria-invalid="{{ $errors->has('country') ? 'true' : 'false' }}">
                    <option value="" disabled @selected(!old('country'))></option>
                    @foreach(config('countries', []) as $country)
                        <option value="{{ $country['name'] }}" data-dial-code="{{ $country['dial'] }}" @selected(old('country') === $country['name'])>{{ $country['name'] }}</option>
                    @endforeach
                </select>
                <span class="auth-select-chevron" aria-hidden="true"></span>
            </div>
        </div>

        <div class="auth-field">
            <label for="phone">Phone or WhatsApp Number</label>
            <div class="auth-phone-group @error('country_code') is-invalid @enderror @error('phone') is-invalid @enderror">
                <div class="auth-input-wrap auth-country-code-wrap">
                    <input id="country_code" type="tel" name="country_code" value="{{ old('country_code') }}" maxlength="8" autocomplete="tel-country-code" inputmode="tel" placeholder="+971" aria-label="Country calling code" required>
                </div>
                <div class="auth-input-wrap auth-input-wrap--plain auth-phone-number-wrap">
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="24" autocomplete="tel-national" inputmode="tel" required aria-invalid="{{ ($errors->has('country_code') || $errors->has('phone')) ? 'true' : 'false' }}">
                </div>
            </div>
        </div>

        <div class="auth-field">
            <label for="password">Password</label>
            <div class="auth-input-wrap auth-input-wrap--plain @error('password') is-invalid @enderror">
                <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <svg class="auth-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg class="auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2c.5-.1.9-.2 1.4-.2 6 0 9.5 6 9.5 6a17 17 0 0 1-2.2 2.8M6.3 6.3A17.2 17.2 0 0 0 2.5 12S6 18 12 18c1.5 0 2.8-.4 3.9-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            <p class="auth-field-help">Minimum 8 characters with uppercase, lowercase and a number.</p>
        </div>

        <div class="auth-field">
            <label for="password_confirmation">Confirm Password</label>
            <div class="auth-input-wrap auth-input-wrap--plain">
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                <button class="auth-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                    <svg class="auth-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg class="auth-eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2c.5-.1.9-.2 1.4-.2 6 0 9.5 6 9.5 6a17 17 0 0 1-2.2 2.8M6.3 6.3A17.2 17.2 0 0 0 2.5 12S6 18 12 18c1.5 0 2.8-.4 3.9-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
        </div>
    </div>

    <label class="auth-checkbox auth-registration-consent" for="terms">
        <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))>
        <span aria-hidden="true"></span>
        <b>I agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</b>
    </label>

    <button class="auth-submit" type="submit" data-busy-label="Creating your secure account…">
        <span>Create Account</span>
    </button>

    <div class="auth-divider" aria-hidden="true"><span></span><i></i><span></span></div>
    <p class="auth-register">Already have an ABS account? <a href="{{ route('login', $isPulseRegistration ? array_filter(['service' => 'pulse', 'plan' => $plan ?? null]) : []) }}">Sign in</a></p>

    <div class="auth-card__security auth-registration-security">
        <span aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5.1-3.4 8.5-8 10-4.6-1.5-8-4.9-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
        </span>
        <p>Your information is encrypted and used only to manage your ABS account and services.</p>
    </div>
</form>
@endsection
