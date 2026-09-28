@extends('admin.layout')
@section('title','Ads CMS')
@section('heading','Ads CMS')
@section('description','Manage Google display-ad inventory from one place without editing the Free Signal page code.')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/admin-ads-v1520.css') }}?v={{ @filemtime(public_path('assets/css/admin-ads-v1520.css')) ?: '15.2.0' }}">
@endpush
@section('page-actions')
<a class="button button-ghost" href="{{ route('pulse.free-signal') }}" target="_blank" rel="noopener">Preview Free Signal ↗</a>
@endsection
@section('content')
@php
    $activeCount = collect($settings['placements'])->filter(fn ($row) => $row['enabled'] && trim((string) $row['code']) !== '')->count();
@endphp

<div class="ads-kpi-grid">
    <article class="ads-kpi"><span>Ads CMS</span><b>{{ $settings['enabled'] ? 'LIVE' : 'PAUSED' }}</b><small>master display switch</small></article>
    <article class="ads-kpi"><span>Active placements</span><b>{{ $activeCount }} / {{ count($placements) }}</b><small>Free Signal inventory</small></article>
    <article class="ads-kpi"><span>Google loader</span><b>{{ trim((string)$settings['head_code']) !== '' ? 'READY' : 'NOT SET' }}</b><small>head / library code</small></article>
    <article class="ads-kpi"><span>Rewarded unlock ad</span><b>SEPARATE</b><small><a href="{{ route('admin.pulse.rewarded-signals') }}">Manage rewarded ad →</a></small></article>
</div>

<section class="enterprise-surface ads-command-surface">
    <div class="enterprise-section-head ads-head">
        <div>
            <span class="ads-kicker">GOOGLE DISPLAY INVENTORY · CMS CONTROLLED</span>
            <h2>Display advertising without code edits</h2>
            <p>Paste the Google-issued AdSense or Google Ad Manager snippets below. ABS renders only enabled placements on the public Free Signal experience. The existing rewarded unlock ad remains independently controlled under Rewarded Signal Ads.</p>
        </div>
        <span class="ads-live-pill {{ $settings['enabled'] ? 'is-live' : 'is-paused' }}">{{ $settings['enabled'] ? 'DISPLAY ADS LIVE' : 'DISPLAY ADS PAUSED' }}</span>
    </div>

    <form method="POST" action="{{ route('admin.ads.update') }}" class="ads-cms-form">@csrf @method('PUT')
        <div class="ads-master-grid">
            <label class="ads-field ads-master-control">
                <span>Master display-ad status</span>
                <select name="enabled">
                    <option value="true" @selected(old('enabled', $settings['enabled'] ? 'true' : 'false') === 'true')>Enabled</option>
                    <option value="false" @selected(old('enabled', $settings['enabled'] ? 'true' : 'false') === 'false')>Paused</option>
                </select>
                <small>Pause every CMS display placement instantly without deleting your saved Google code.</small>
            </label>
            <div class="ads-safety-card">
                <b>Safe operating model</b>
                <span>Only Google-looking snippets are accepted. PHP and Blade instructions are rejected. Ads appear only in the predefined ABS placements below.</span>
            </div>
        </div>

        <div class="ads-code-section">
            <div class="ads-section-title">
                <div><span>01</span><h3>Google loader / head code</h3></div>
                <small>Paste the global Google script only when your Google setup provides one.</small>
            </div>
            <label class="ads-field">
                <textarea name="head_code" rows="7" spellcheck="false" placeholder="Paste the Google AdSense / Google Ad Manager head script here…">{{ old('head_code', $settings['head_code']) }}</textarea>
                @error('head_code')<small class="form-error">{{ $message }}</small>@enderror
            </label>
        </div>

        <div class="ads-placement-grid">
            @foreach($placements as $key => $label)
                @php($row = $settings['placements'][$key])
                <article class="ads-placement-card {{ $row['enabled'] ? 'is-enabled' : '' }}">
                    <header>
                        <div><span class="ads-slot-index">0{{ $loop->iteration }}</span><div><b>{{ $label }}</b><small>{{ $key }}</small></div></div>
                        <span class="ads-slot-state">{{ $row['enabled'] ? 'ON' : 'OFF' }}</span>
                    </header>
                    <label class="ads-field">
                        <span>Placement status</span>
                        <select name="{{ $key }}_enabled">
                            <option value="true" @selected(old($key.'_enabled', $row['enabled'] ? 'true' : 'false') === 'true')>Enabled</option>
                            <option value="false" @selected(old($key.'_enabled', $row['enabled'] ? 'true' : 'false') === 'false')>Disabled</option>
                        </select>
                    </label>
                    <label class="ads-field">
                        <span>Google placement code</span>
                        <textarea name="{{ $key }}_code" rows="10" spellcheck="false" placeholder="Paste the Google ad-slot / display snippet for this placement…">{{ old($key.'_code', $row['code']) }}</textarea>
                        @error($key.'_code')<small class="form-error">{{ $message }}</small>@enderror
                    </label>
                </article>
            @endforeach
        </div>

        <div class="ads-actions">
            <div><b>Before publishing</b><span>Use Google test inventory while developing locally. Production delivery and ad eligibility remain subject to your Google account, domain approval, consent requirements and Google policy.</span></div>
            <button class="button button-primary" type="submit">Save Ads CMS</button>
        </div>
    </form>
</section>

<section class="enterprise-section-grid equal ads-help-grid">
    <article class="enterprise-surface">
        <div class="ads-mini-head"><span>DISPLAY ADS</span><h3>What this CMS controls</h3></div>
        <p>Standard Google display inventory on the public Free Signal page: top, inline and footer placements. You can replace code, disable one slot, or pause all display ads from Admin.</p>
    </article>
    <article class="enterprise-surface">
        <div class="ads-mini-head"><span>REWARDED ACCESS</span><h3>What stays separate</h3></div>
        <p>The ad a visitor watches to unlock the Free Signal is still managed under <a href="{{ route('admin.pulse.rewarded-signals') }}">Rewarded Signal Ads</a>. This prevents display-ad configuration from breaking the unlock/reward flow.</p>
    </article>
</section>
@endsection
