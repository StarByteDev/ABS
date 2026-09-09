@extends('layouts.app')
@section('title','Free Pulse Signal — Alpha Block Solutions')
@push('head')
<meta property="og:title" content="ABS Pulse · Free Signal">
<meta property="og:description" content="Watch one rewarded ad and reveal the best available ABS Pulse market setup — a qualified signal when available, otherwise an Entry Watch.">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ route('pulse.free-signal') }}">
<meta property="og:image" content="{{ asset('assets/brand/abs-logo-512.png') }}">
<meta name="twitter:card" content="summary_large_image">
<link rel="stylesheet" href="{{ asset('assets/css/pulse-public-signal-v1515.css') }}?v={{ @filemtime(public_path('assets/css/pulse-public-signal-v1515.css')) ?: '15.1.5' }}">
@endpush
@section('content')
<section class="free-signal-page" data-free-signal-app
    data-session-url="{{ route('pulse.free-signal.session') }}"
    data-claim-url="{{ route('pulse.free-signal.claim') }}"
    data-status-url="{{ route('pulse.free-signal.status') }}"
    data-share-url="{{ route('pulse.free-signal') }}"
    data-cooldown="{{ (int)($status['cooldown_remaining'] ?? 0) }}"
    data-cooldown-minutes="{{ (int)($status['cooldown_minutes'] ?? 30) }}"
    data-cta="{{ e($status['cta'] ?? 'Watch Ad & Reveal Signal') }}">

    <div class="container free-signal-benefits">
        <article><span class="benefit-icon">◎</span><div><b>No registration</b><small>Try Pulse before creating an account.</small></div></article>
        <article><span class="benefit-icon">∞</span><div><b>Keep it visible</b><small>Your revealed signal stays on screen until you refresh or leave this page.</small></div></article>
        <article><span class="benefit-icon">◷</span><div><b>{{ (int)($status['cooldown_minutes'] ?? 30) }}-minute reset</b><small>Return later for another free signal.</small></div></article>
    </div>

    <div class="container free-signal-workspace">
        <aside class="free-signal-ad-rail">
            <section class="pulse-ad-card" data-ad-console>
                <span class="pulse-ad-kicker">ABS PULSE · REWARDED ACCESS</span>
                <div class="pulse-ad-animation" aria-hidden="true">
                    <span class="pulse-orbit p1"></span><span class="pulse-orbit p2"></span><span class="pulse-orbit p3"></span>
                    <div class="pulse-core"><span>▶</span></div>
                    <div class="pulse-wave"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                </div>
                <h2 data-stage-title>{{ $status['title'] ?? 'Watch a short ad. Reveal your free signal.' }}</h2>
                <p data-stage-copy>{{ $status['description'] ?? 'Complete one rewarded ad and your qualified Pulse setup appears instantly.' }}</p>

                <div class="free-signal-cooldown compact" data-side-cooldown @if(($status['cooldown_remaining'] ?? 0) <= 0) hidden @endif>
                    <small>NEXT FREE SIGNAL IN</small>
                    <strong data-cooldown-clock>{{ sprintf('%02d:%02d', intdiv((int)($status['cooldown_remaining'] ?? 0),60), ((int)($status['cooldown_remaining'] ?? 0))%60) }}</strong>
                </div>

                <label class="free-signal-consent">
                    <input type="checkbox" data-reward-consent>
                    <span class="consent-control" aria-hidden="true"><span>✓</span></span>
                    <span class="consent-copy"><strong>Risk acknowledgement</strong><small>I agree to the <a href="{{ route('legal.risk') }}" target="_blank" rel="noopener">Risk Disclosure</a> and <a href="{{ route('legal.disclaimer') }}" target="_blank" rel="noopener">Market Disclaimer</a>.</small></span>
                </label>

                <button type="button" class="free-signal-button" data-watch-ad @disabled(!($status['enabled'] ?? false) || !($status['available'] ?? false))>
                    <span>▶</span> {{ $status['cta'] ?? 'Watch Ad & Reveal Signal' }}
                </button>
                <div class="free-signal-message" data-reward-message aria-live="polite"></div>
                <div class="free-signal-market-state" data-market-state hidden>
                    <span class="market-state-icon">◎</span>
                    <div><strong data-market-state-title>Pulse Market Watch</strong><p data-market-state-copy>ABS is monitoring the configured market universe for the next qualified setup.</p></div>
                </div>
            </section>

            <section class="free-signal-grow-card">
                <span class="grow-icon">↗</span>
                <div><b>Help ABS Pulse grow</b><p>Found the signal useful? Share it with your community after it is revealed.</p></div>
            </section>
        </aside>

        <main class="free-signal-signal-zone">
            <section class="next-signal-banner" data-next-free-panel @if(($status['cooldown_remaining'] ?? 0) <= 0) hidden @endif>
                <span class="next-icon">◷</span>
                <div><small>NEXT FREE SIGNAL AVAILABLE IN</small><strong data-main-cooldown-clock>{{ sprintf('%02d:%02d', intdiv((int)($status['cooldown_remaining'] ?? 0),60), ((int)($status['cooldown_remaining'] ?? 0))%60) }}</strong></div>
                <p>Your current signal stays visible until you refresh or leave this page.</p>
            </section>

            <section class="signal-teaser" data-signal-teaser>
                <div class="signal-teaser-head"><span>TODAY'S PULSE MARKET CHECK</span><b>REVEAL AFTER AD</b></div>
                <div class="signal-teaser-blur" aria-hidden="true">
                    <div class="teaser-top"><i></i><strong>XXXX/USDT</strong><em>BEST AVAILABLE</em></div>
                    <div class="teaser-metrics"><span></span><span></span><span></span></div>
                    <div class="teaser-chart">
                        <svg viewBox="0 0 800 300" preserveAspectRatio="none">
                            <polyline points="0,250 60,230 120,240 180,185 240,205 300,140 360,165 420,120 480,135 540,95 600,115 660,72 720,88 800,45" />
                            <line x1="0" y1="264" x2="800" y2="264"/><line x1="0" y1="198" x2="800" y2="198"/><line x1="0" y1="132" x2="800" y2="132"/>
                        </svg>
                    </div>
                    <div class="teaser-levels"><i></i><i></i><i></i><i></i></div>
                </div>
                <div class="signal-teaser-lock">
                    <div class="lock-ring"><span>⌁</span></div>
                    <h2>A Pulse market setup is waiting.</h2>
                    <p>Watch the rewarded ad to reveal the best available setup. ABS shows a qualified signal when one exists; otherwise it clearly labels the highest-scoring setup as Entry Watch.</p>
                    <button type="button" class="teaser-watch" data-teaser-watch>▶ Watch Ad & Reveal Signal</button>
                </div>
            </section>

            <section class="signal-result" data-signal-reveal hidden>
                <header class="signal-result-head">
                    <div class="signal-symbol-lockup"><span class="signal-coin" data-signal-coin>ABS</span><div><small data-signal-eyebrow>ABS PULSE · FREE SIGNAL</small><h2 data-signal-symbol>—</h2><p><b data-signal-direction>—</b> · <span data-signal-timeframe>—</span></p></div></div>
                    <div class="signal-badges"><span data-signal-confidence-label>QUALIFIED</span><strong data-signal-score>—</strong></div>
                </header>

                <div class="signal-market-grid">
                    <article class="signal-price-card"><small>CURRENT PRICE</small><strong data-signal-current-price>—</strong><span data-signal-change>—</span></article>
                    <article class="signal-range-card"><small>24H RANGE</small><div><span>High</span><b data-signal-high>—</b></div><div><span>Low</span><b data-signal-low>—</b></div><div><span>Volume</span><b data-signal-volume>—</b></div></article>
                    <article class="signal-key-card"><small>KEY LEVEL TO WATCH</small><strong data-signal-key-level>—</strong><p data-signal-key-copy>Monitor price around the planned Pulse level and the risk controls below.</p></article>
                </div>

                <div class="signal-core-grid">
                    <section class="signal-setup-card">
                        <h3 data-signal-setup-heading>Trade Setup</h3>
                        <div><span>Entry</span><b data-signal-entry>—</b></div>
                        <div data-tp-row="1"><span>Take Profit 1</span><b data-signal-tp1>—</b></div>
                        <div data-tp-row="2"><span>Take Profit 2</span><b data-signal-tp2>—</b></div>
                        <div data-tp-row="3"><span>Take Profit 3</span><b data-signal-tp3>—</b></div>
                        <div class="stop"><span>Stop Loss</span><b data-signal-sl>—</b></div>
                    </section>
                    <section class="signal-chart-card">
                        <div class="chart-heading"><span>LIVE MARKET STRUCTURE</span><small data-chart-timeframe>—</small></div>
                        <div class="signal-chart" data-signal-chart><div class="chart-placeholder">Chart loads with the unlocked signal.</div></div>
                    </section>
                </div>

                <div class="signal-availability-note" data-signal-availability-note hidden><span>◎</span><p data-signal-availability-copy></p></div>

                <div class="signal-context-grid">
                    <article><span class="context-icon">⚡</span><div><small>MOMENTUM</small><strong data-signal-bias>—</strong><p data-signal-strategies>Pulse strategy engine</p></div></article>
                    <article><span class="context-icon">◇</span><div><small>SETUP SUMMARY</small><p data-signal-summary>—</p></div></article>
                </div>

                <section class="signal-share-card">
                    <div><small data-share-kicker>SHARE THIS SIGNAL</small><h3 data-share-title>Help others discover ABS Pulse.</h3><p data-share-copy>Share the signal and this Free Signal page with your network.</p></div>
                    <div class="signal-share-actions">
                        <button type="button" data-share-network="x">𝕏 <span>X</span></button>
                        <button type="button" data-share-network="facebook">f <span>Facebook</span></button>
                        <button type="button" data-share-network="whatsapp">◉ <span>WhatsApp</span></button>
                        <button type="button" data-share-network="telegram">➤ <span>Telegram</span></button>
                        <button type="button" data-share-network="linkedin">in <span>LinkedIn</span></button>
                        <button type="button" data-share-network="reddit">● <span>Reddit</span></button>
                        <button type="button" data-share-network="more">↗ <span>More Apps</span></button>
                        <button type="button" class="copy-link" data-share-network="copy">⌁ <span>Copy Link</span></button>
                    </div>
                    <div class="share-feedback" data-share-feedback aria-live="polite"></div>
                </section>
            </section>
        </main>
    </div>

    <section class="container free-signal-membership">
        <div><span>WANT CONTINUOUS ACCESS?</span><h2>Move from one free signal to full ABS Pulse access.</h2><p>Choose a Pulse package, transfer USDT, submit the transaction reference, and Admin verification activates your access.</p></div>
        <div>
            <a class="button button-primary" href="{{ auth()->check() ? route('pulse.plans') : route('register',['service'=>'pulse']) }}">View Pulse Packages</a>
            @auth<a class="button button-ghost" href="{{ route('pulse.membership.index') }}">My Payments</a>@endauth
        </div>
    </section>

    <section class="container free-signal-risk-note">
        <b>Market & Risk Notice:</b> Alpha Block Solutions provides market intelligence for informational and educational purposes only. Nothing on ABS is financial, investment, legal or tax advice. Digital assets and derivatives are high risk. <a href="{{ route('legal.risk') }}">Read the full Risk Disclosure.</a>
    </section>
</section>
@endsection
@push('scripts')
<script src="{{ asset('assets/js/pulse-public-signal-v1515.js') }}?v={{ @filemtime(public_path('assets/js/pulse-public-signal-v1515.js')) ?: '15.1.5' }}" defer></script>
@endpush
