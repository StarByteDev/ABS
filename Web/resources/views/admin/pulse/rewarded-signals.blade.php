@extends('admin.layout')
@section('title','Rewarded Signal Ads')
@section('heading','Rewarded Signal Ads')
@section('description','Configure the public rewarded market-intelligence experience. Visitors do not need an ABS account and no wallet or credits are used.')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/admin-rewarded-signal-v1510.css') }}?v={{ @filemtime(public_path('assets/css/admin-rewarded-signal-v1510.css')) ?: '15.1.0' }}">
@endpush
@section('content')
<div class="admin-kpi-grid rewarded-admin-kpis">
    <article class="admin-kpi"><span>Unlocks Today</span><b>{{ number_format($stats['today']) }}</b><small>rewarded reveals</small></article>
    <article class="admin-kpi"><span>Unlocks · 30 Days</span><b>{{ number_format($stats['last30']) }}</b><small>successful grants</small></article>
    <article class="admin-kpi"><span>Unique Browsers · 30 Days</span><b>{{ number_format($stats['unique30']) }}</b><small>privacy-preserving IDs</small></article>
    <article class="admin-kpi"><span>Qualified Signals · 30 Days</span><b>{{ number_format($stats['signals30']) }}</b><small>Entry Watch reveals are excluded</small></article>
</div>

<section class="enterprise-surface rewarded-admin-shell">
    <div class="enterprise-section-head rewarded-admin-head">
        <div>
            <span class="rewarded-kicker">PUBLIC ACQUISITION · GOOGLE REWARDED WEB</span>
            <h2>Rewarded Signal Experience</h2>
            <p>One optional rewarded ad reveals the best available Pulse market intelligence. ABS prioritizes a qualified public signal; when none qualifies, it can reveal the highest-scoring Entry Watch without creating or labelling it as a signal. The reveal stays visible until refresh/navigation, while the browser cooldown controls the next free unlock.</p>
        </div>
        <span class="rewarded-status {{ $settings['enabled'] ? 'is-live' : 'is-paused' }}">{{ $settings['enabled'] ? 'LIVE' : 'PAUSED' }}</span>
    </div>

    <div class="rewarded-admin-grid">
        <form method="POST" action="{{ route('admin.pulse.rewarded-signals.update') }}" class="admin-form-grid rewarded-config-card">@csrf @method('PUT')
            <div class="rewarded-config-title full"><b>Access & Timing</b><small>Control availability without changing the Pulse scanner engine.</small></div>
            <label>Feature status
                <select name="enabled"><option value="true" @selected($settings['enabled'])>Enabled</option><option value="false" @selected(!$settings['enabled'])>Paused</option></select>
            </label>
            <label>Google inventory
                <select name="web_test_mode"><option value="true" @selected($settings['web_test_mode'])>Test inventory</option><option value="false" @selected(!$settings['web_test_mode'])>Production inventory</option></select>
            </label>
            <label>Cooldown (minutes)
                <input type="number" name="cooldown_minutes" min="1" max="1440" value="{{ $settings['cooldown_minutes'] }}">
                <small>Recommended: 30 minutes.</small>
            </label>
            <div class="resolved-unit">
                <span>Signal visibility</span>
                <code>Until page refresh / navigation</code>
            </div>

            <div class="rewarded-config-title full"><b>Google Ad Manager</b><small>Paste the rewarded ad-unit path or the copied Google Publisher Tag snippet. ABS extracts only the ad-unit path; pasted JavaScript is never executed.</small></div>
            <label class="full">Rewarded ad unit / copied GPT code
                <textarea name="web_ad_unit_input" rows="5" placeholder="/1234567/abs_rewarded_signal&#10;&#10;or paste the Google Publisher Tag snippet containing that path">{{ old('web_ad_unit_input', $settings['web_ad_unit_input'] ?? '') }}</textarea>
                @error('web_ad_unit_input')<small class="form-error">{{ $message }}</small>@enderror
            </label>
            <div class="resolved-unit full">
                <span>Resolved ad unit</span>
                <code>{{ $settings['web_test_mode'] ? \App\Services\PulsePublicRewardedSignalService::TEST_AD_UNIT : (($settings['web_ad_unit_path'] ?? '') ?: 'Not configured') }}</code>
            </div>

            <div class="rewarded-config-title full"><b>Public Ad Gateway Design</b><small>These fields control the premium opt-in card shown before Google displays the actual ad creative.</small></div>
            <label>Badge<input name="badge" maxlength="80" value="{{ old('badge', $settings['badge']) }}"></label>
            <label>CTA button<input name="cta" maxlength="60" value="{{ old('cta', $settings['cta']) }}"></label>
            <label class="full">Headline<input name="title" maxlength="140" value="{{ old('title', $settings['title']) }}"></label>
            <label class="full">Description<textarea name="description" rows="3" maxlength="320">{{ old('description', $settings['description']) }}</textarea></label>

            <div class="full rewarded-form-actions">
                <button class="button button-primary">Save Rewarded Signal Settings</button>
                <a class="button button-ghost" href="{{ route('pulse.free-signal') }}" target="_blank" rel="noopener">Preview Public Experience</a>
            </div>
        </form>

        <aside class="rewarded-preview-card">
            <span class="rewarded-preview-label">LIVE EXPERIENCE PREVIEW</span>
            <div class="rewarded-preview-orbit"><span>▶</span></div>
            <small>{{ $settings['badge'] }}</small>
            <h3>{{ $settings['title'] }}</h3>
            <p>{{ $settings['description'] }}</p>
            <div class="rewarded-preview-steps"><span><b>01</b> Opt in</span><span><b>02</b> Watch</span><span><b>03</b> Reveal</span></div>
            <button type="button" disabled>{{ $settings['cta'] }}</button>
            <div class="rewarded-device-grid">
                <article><b>Desktop Web</b><span>Supported when Google reports rewarded inventory eligible for the browser/page.</span></article>
                <article><b>Mobile Web</b><span>Supported on eligible mobile-optimized web inventory.</span></article>
            </div>
            <div class="rewarded-admin-note"><b>Google controls the ad creative.</b> ABS controls the branded opt-in, loading, reward-granted, signal reveal and cooldown experience around it.</div>
        </aside>
    </div>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Recent rewarded reveals</h2><p>Anonymous browser hashes are stored instead of raw visitor identifiers. This register supports cooldown enforcement and operational auditing.</p></div></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Time</th><th>Type</th><th>Market</th><th>Direction</th><th>Timeframe</th><th>Provider</th><th>Visibility</th><th>Next Available</th><th>Status</th></tr></thead><tbody>
    @forelse($unlocks as $unlock)
        <tr><td>{{ $unlock->claimed_at?->format('d M Y H:i') }}</td><td><span class="status-pill {{ data_get($unlock->signal_snapshot,'presentation') === 'market_watch' ? 'pending' : 'active' }}">{{ data_get($unlock->signal_snapshot,'presentation') === 'market_watch' ? 'ENTRY WATCH' : 'SIGNAL' }}</span></td><td><b>{{ data_get($unlock->signal_snapshot,'symbol','—') }}</b></td><td>{{ data_get($unlock->signal_snapshot,'direction','—') }}</td><td>{{ data_get($unlock->signal_snapshot,'timeframe','—') }}</td><td>{{ $unlock->provider }}</td><td>Until page refresh</td><td>{{ $unlock->next_available_at?->format('d M H:i') }}</td><td><span class="status-pill active">{{ strtoupper($unlock->status) }}</span></td></tr>
    @empty
        <tr><td colspan="9" class="admin-empty">No rewarded market-intelligence reveal has been unlocked yet.</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $unlocks->links() }}
</section>
@endsection
