@extends('layouts.app')
@section('title','Alpha Block Solutions — Digital Asset Market Intelligence')
@section('content')
@php
    $preferredMarketOrder = ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA'];
    $homeCore = collect($market['core'] ?? [])->sortBy(function ($coin) use ($preferredMarketOrder) {
        $position = array_search($coin['base'] ?? '', $preferredMarketOrder, true);
        return $position === false ? 99 : $position;
    })->values();
    $btc = $homeCore->firstWhere('base', 'BTC') ?? [];
    $marketStripCoins = $homeCore->reject(fn ($coin) => ($coin['base'] ?? '') === 'BTC')->take(5)->values();

    $sentimentScore = data_get($market, 'sentiment.score');
    $sentimentBullish = data_get($market, 'sentiment.bullish');
    $sentimentNeutral = data_get($market, 'sentiment.neutral');
    $sentimentBearish = data_get($market, 'sentiment.bearish');
    $pulseScore = data_get($market, 'pulse.score');
    $pulseLabel = data_get($market, 'pulse.label', 'Connecting');

    $marketCap = data_get($market, 'global.total_market_cap');
    $marketVolume = data_get($market, 'global.total_volume');
    $marketChange = data_get($market, 'global.market_cap_change_24h');
    $btcDominance = data_get($market, 'global.btc_dominance');
    $btcDominanceChange = data_get($market, 'global.btc_dominance_change_24h');
    $fearGreedScore = data_get($market, 'global.fear_greed_score');
    $fearGreedLabel = data_get($market, 'global.fear_greed_label', 'Syncing');

    $liquidationLong = data_get($market, 'global.liquidation_long_24h_usd');
    $liquidationShort = data_get($market, 'global.liquidation_short_24h_usd');
    $liquidationLongShare = data_get($market, 'global.liquidation_long_share_24h');
    $liquidationShortShare = data_get($market, 'global.liquidation_short_share_24h');
    $openInterest = data_get($market, 'global.open_interest_usd');
    $fundingRate = data_get($market, 'global.funding_rate');
    $longShortRatio = data_get($market, 'global.long_short_ratio');
    $perpPremiumBasis = data_get($market, 'global.perp_premium_basis');

    $insights = collect($market['insights'] ?? []);
    $industries = collect($market['industries'] ?? [])->keyBy('key');
    $industryDefinitions = [
        ['key' => 'defi', 'label' => 'DeFi', 'icon' => '◎'],
        ['key' => 'layer1', 'label' => 'Layer 1', 'icon' => '⬡'],
        ['key' => 'infrastructure', 'label' => 'Infrastructure', 'icon' => '◈'],
        ['key' => 'gaming', 'label' => 'Gaming', 'icon' => '⌁'],
        ['key' => 'ai', 'label' => 'AI & Big Data', 'icon' => '✣'],
        ['key' => 'nft', 'label' => 'NFT', 'icon' => '⬢'],
        ['key' => 'payments', 'label' => 'Payments', 'icon' => '◉'],
        ['key' => 'metaverse', 'label' => 'Metaverse', 'icon' => '✧'],
    ];

    $pulsePlans = $plans->where('is_public', true)->where('is_trial', false)->take(4)->values();

    $formatPlanPrice = static function ($plan): string {
        if (! $plan) return 'Contact';
        $price = max(0, (float) ($plan->monthly_price ?? 0));
        return $price > 0 ? number_format($price, 2).' USDT' : 'Admin set';
    };
    $formatPrice = static function ($value): string {
        if (! is_numeric($value)) return '—';
        return number_format((float) $value, (float) $value < 1 ? 4 : 2);
    };
    $formatChange = static function ($value): string {
        if (! is_numeric($value)) return '—';
        return ((float) $value >= 0 ? '+' : '').number_format((float) $value, 2).'%';
    };
    $formatCompactUsd = static function ($value): string {
        if (! is_numeric($value)) return '—';
        $value = (float) $value;
        if (abs($value) >= 1e12) return '$'.number_format($value / 1e12, 2).'T';
        if (abs($value) >= 1e9) return '$'.number_format($value / 1e9, 2).'B';
        if (abs($value) >= 1e6) return '$'.number_format($value / 1e6, 1).'M';
        return '$'.number_format($value, 0);
    };
    $formatSignedCompactUsd = static function ($value) use ($formatCompactUsd): string {
        if (! is_numeric($value)) return '—';
        $value = (float) $value;
        return ($value >= 0 ? '+' : '-').$formatCompactUsd(abs($value));
    };
    $metricTone = static fn ($value): string => is_numeric($value) && (float) $value < 0 ? 'negative' : 'positive';
@endphp

<section class="final-home-hero" aria-labelledby="final-home-title">
    <div class="container final-hero-grid">
        <div class="final-hero-copy">
            <span class="final-eyebrow">INTELLIGENCE FOR DIGITAL ASSET MARKETS</span>
            <h1 id="final-home-title"><span>Navigate Digital</span><span>Markets with</span><span class="final-hero-gold">Confidence</span></h1>
            <p>Verified insights, disciplined analysis, and real-time market context—empowering you to make smarter decisions in digital asset markets.</p>
            <div class="final-hero-actions">
                <a class="final-button final-button-gold" href="{{ route('pulse.entry') }}">Explore Pulse <span aria-hidden="true">→</span></a>
                <a class="final-button final-button-outline" href="{{ route('pulse.free-signal') }}">Free Signal · Watch Ad <span aria-hidden="true">→</span></a>
                <a class="final-button final-button-outline" href="{{ route('news.index') }}">ABS News <span aria-hidden="true">→</span></a>
            </div>
            <div class="final-trusted-by" aria-label="Market data and editorial sources">
                <small>MARKET DATA SOURCES</small>
                <div><span class="trusted-binance">◆ BINANCE</span><span>◉ CoinGecko</span><span>◈ Alternative.me</span><span>⬡ Xoomar</span></div>
            </div>
        </div>

        <div class="final-market-board" aria-label="Live digital asset market overview">
            <div class="final-market-primary">
                <div class="final-market-title"><b>BTC/USDT</b><span>Spot</span></div>
                <div class="final-market-price-row">
                    <strong data-hero-market-price>{{ $formatPrice($btc['price'] ?? null) }}</strong>
                    <em data-hero-market-change class="{{ $metricTone($btc['change_percent'] ?? null) }}">{{ $formatChange($btc['change_percent'] ?? null) }} (24h)</em>
                </div>
                <div class="final-market-meta">
                    <span>24H High <b data-hero-market-high>{{ $formatPrice($btc['high'] ?? null) }}</b></span>
                    <span>24H Low <b data-hero-market-low>{{ $formatPrice($btc['low'] ?? null) }}</b></span>
                    <span>24H Vol <b data-hero-market-volume>{{ is_numeric($btc['volume'] ?? null) ? number_format((float) $btc['volume'] / 1e9, 3).'B USDT' : '—' }}</b></span>
                </div>
                <div class="final-chart-tabs chart-tabs" aria-label="Chart range">
                    <button type="button" data-chart-range="1h">1H</button>
                    <button class="active" type="button" data-chart-range="1d">1D</button>
                    <button type="button" data-chart-range="7d">1W</button>
                    <button type="button" data-chart-range="1m">1M</button>
                    <button type="button" data-chart-range="1y">1Y</button>
                    <button type="button" data-chart-range="all">ALL</button>
                </div>
                <div id="market-chart" class="market-chart final-live-chart" data-chart-symbol="BTCUSDT"><div class="chart-loading">Connecting to live market data…</div></div>
            </div>

            <aside class="final-market-side">
                <article class="final-side-card final-pulse-card">
                    <small>Market Pulse</small>
                    <strong data-pulse-label>{{ $pulseLabel }}</strong>
                    <div class="final-pulse-score"><span>Score <b data-pulse-score>{{ is_numeric($pulseScore) ? (int) $pulseScore : '—' }}</b><i>/100</i></span><svg viewBox="0 0 100 32" aria-hidden="true"><polyline points="2,26 13,19 23,22 34,10 44,18 55,15 65,21 76,11 87,13 98,6"/></svg></div>
                </article>

                <article class="final-side-card final-sentiment-card">
                    <small>Market Sentiment</small>
                    <div class="final-sentiment">
                        <span class="gauge {{ is_numeric($sentimentScore) ? '' : 'gauge-unavailable' }}" style="--score:{{ is_numeric($sentimentScore) ? (int) $sentimentScore : 0 }}"><b data-sentiment-score>{{ is_numeric($sentimentScore) ? (int) $sentimentScore : '—' }}</b></span>
                        <ul>
                            <li><i class="bullish"></i><b data-sentiment-bullish>{{ is_numeric($sentimentBullish) ? (int) $sentimentBullish : '—' }}</b><span>Bullish</span></li>
                            <li><i class="neutral"></i><b data-sentiment-neutral>{{ is_numeric($sentimentNeutral) ? (int) $sentimentNeutral : '—' }}</b><span>Neutral</span></li>
                            <li><i class="bearish"></i><b data-sentiment-bearish>{{ is_numeric($sentimentBearish) ? (int) $sentimentBearish : '—' }}</b><span>Bearish</span></li>
                        </ul>
                    </div>
                </article>

                <article class="final-side-card final-liquidation-card">
                    <small>24H Liquidations</small>
                    <div class="final-split-metrics">
                        <div class="final-split-metric long"><small>Longs (24H)</small><strong data-liquidation-long>{{ is_numeric($liquidationLong) ? $formatCompactUsd($liquidationLong) : 'Syncing…' }}</strong><em data-liquidation-long-share class="positive">{{ is_numeric($liquidationLongShare) ? number_format((float) $liquidationLongShare, 1).'% of 24H' : 'Aggregated live' }}</em><span class="metric-bars" aria-hidden="true"></span></div>
                        <div class="final-split-metric short"><small>Shorts (24H)</small><strong data-liquidation-short>{{ is_numeric($liquidationShort) ? $formatCompactUsd($liquidationShort) : 'Syncing…' }}</strong><em data-liquidation-short-share class="negative">{{ is_numeric($liquidationShortShare) ? number_format((float) $liquidationShortShare, 1).'% of 24H' : 'Aggregated live' }}</em><span class="metric-bars" aria-hidden="true"></span></div>
                    </div>
                </article>

                <article class="final-side-card final-futures-card">
                    <small>Futures Insights</small>
                    <div class="final-futures-metrics-grid" aria-label="BTC futures positioning metrics">
                        <div class="final-split-metric"><small>Open Interest (OI)</small><strong data-open-interest>{{ is_numeric($openInterest) ? $formatCompactUsd($openInterest) : 'Syncing…' }}</strong><em>BTC USD-M</em><span class="metric-bars" aria-hidden="true"></span></div>
                        <div class="final-split-metric"><small>Funding Rate</small><strong data-funding-rate>{{ is_numeric($fundingRate) ? number_format((float) $fundingRate, 3).'%' : 'Syncing…' }}</strong><em data-funding-label>{{ is_numeric($fundingRate) ? ((float) $fundingRate >= 0 ? 'Positive' : 'Negative') : 'Live futures' }}</em><svg class="future-mini-line" viewBox="0 0 100 26" aria-hidden="true"><polyline points="2,21 14,16 25,18 38,12 49,14 61,8 72,11 84,5 98,7"/></svg></div>
                        <div class="final-split-metric final-positioning-metric">
                            <small>Long / Short Ratio</small>
                            <strong data-long-short-ratio>{{ is_numeric($longShortRatio) ? number_format((float) $longShortRatio, 2) : 'Syncing…' }}</strong>
                            <em data-long-short-label>{{ is_numeric($longShortRatio) ? ((float) $longShortRatio >= 1 ? 'Long-heavy' : 'Short-heavy') : 'Global accounts' }}</em>
                        </div>
                        <div class="final-split-metric final-positioning-metric basis">
                            <small>Perp Premium Basis</small>
                            <strong data-perp-premium-basis>{{ is_numeric($perpPremiumBasis) ? (((float) $perpPremiumBasis >= 0 ? '+' : '').number_format((float) $perpPremiumBasis, 3).'%') : 'Syncing…' }}</strong>
                            <em data-perp-premium-label>{{ is_numeric($perpPremiumBasis) ? ((float) $perpPremiumBasis >= 0 ? 'Above index' : 'Below index') : 'Mark vs index' }}</em>
                        </div>
                    </div>
                </article>
            </aside>

            <div class="final-coin-strip" data-market-strip data-market-link="{{ route('markets') }}">
                @foreach($marketStripCoins as $coin)
                    @php $change = $coin['change_percent'] ?? null; @endphp
                    <div class="mini-market" data-market-symbol="{{ $coin['symbol'] ?? '' }}">
                        <span class="coin-icon coin-{{ strtolower($coin['base'] ?? '') }}">{{ substr($coin['base'] ?? '•', 0, 1) }}</span>
                        <div><small>{{ $coin['pair'] ?? '' }}</small><strong>{{ $formatPrice($coin['price'] ?? null) }}</strong><em class="{{ $metricTone($change) }}">{{ $formatChange($change) }}</em></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="final-trust-strip" aria-label="Alpha Block Solutions platform strengths">
    <div class="container final-trust-grid">
        <article><span class="final-line-icon">✓</span><div><b>Verified Intelligence</b><small>Sourced from credible, trusted providers.</small></div></article>
        <article><span class="final-line-icon">◷</span><div><b>Real-Time Market Context</b><small>Live data and context when you need it.</small></div></article>
        <article><span class="final-line-icon">▤</span><div><b>Professional Research</b><small>Disciplined analysis for informed decisions.</small></div></article>
        <article><span class="final-line-icon">▣</span><div><b>Secure Member Access</b><small>Your data and privacy are always protected.</small></div></article>
    </div>
</section>

<section class="container final-headlines" aria-labelledby="headline-title">
    <header class="final-section-heading"><h2 id="headline-title">Latest Verified Headlines</h2><a href="{{ route('news.index') }}">View All ABS News <span>→</span></a></header>
    <div class="final-headline-grid" data-live-news-list data-live-news-mode="home" data-live-news-limit="5">
        @forelse($news as $item)
            <a class="final-headline-card" data-live-news-item data-external="{{ ($item['is_external'] ?? false) ? '1' : '0' }}" href="{{ $item['url'] }}" @if($item['is_external'] ?? false) target="_blank" rel="noopener noreferrer" @endif>
                <img src="{{ $item['image_url'] ?: asset('assets/images/home/news-'.(($loop->index % 4) + 1).'.png') }}" alt="" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('assets/images/home/news-'.(($loop->index % 4) + 1).'.png') }}'">
                <div><small>{{ $item['published_at'] ? $item['published_at']->diffForHumans(short: true) : 'LATEST' }} <i>•</i> <span>{{ strtoupper($item['source_name'] ?: $item['category'] ?: 'MARKETS') }}</span></small><h3>{{ $item['title'] }}</h3><p>{{ $item['excerpt'] ?: 'Open the verified report for the complete market context and source details.' }}</p></div>
            </a>
        @empty
            <div class="content-empty full-span" data-live-news-empty><b>Loading verified market headlines…</b><span>Published ABS and verified-source updates will appear here.</span></div>
        @endforelse
    </div>
</section>

<section class="container final-advantages" id="platform">
    <header><h2>One Intelligence Platform. <span>Multiple Market Advantages.</span></h2></header>
    <div class="final-advantage-grid">
        <article><span class="final-advantage-icon">✓</span><div class="final-advantage-copy"><h3>Market Pulse</h3><p>Real-time market pulse and sentiment.</p></div></article>
        <article><span class="final-advantage-icon">▤</span><div class="final-advantage-copy"><h3>Verified Headlines</h3><p>Curated news from trusted sources.</p></div></article>
        <article><span class="final-advantage-icon">⌕</span><div class="final-advantage-copy"><h3>Research &amp; Insights</h3><p>In-depth research on assets, sectors, and trends.</p></div></article>
        <article><span class="final-advantage-icon">♧</span><div class="final-advantage-copy"><h3>Alerts &amp; Watchlists</h3><p>Custom alerts and watchlists to track what matters.</p></div></article>
        <article><span class="final-advantage-icon">▦</span><div class="final-advantage-copy"><h3>Calculators &amp; Tools</h3><p>Powerful tools for scenario planning and analysis.</p></div></article>
        <article><span class="final-advantage-icon">♙</span><div class="final-advantage-copy"><h3>Member Intelligence</h3><p>Exclusive insights and reports for members.</p></div></article>
    </div>
</section>

<section class="container final-industries" aria-labelledby="industries-title">
    <header class="final-mini-section-heading"><h2 id="industries-title">Top Crypto Industries</h2></header>
    <div class="final-industry-grid" data-industries-grid>
        @foreach($industryDefinitions as $definition)
            @php $industry = $industries->get($definition['key']); $industryChange = data_get($industry, 'change_24h'); @endphp
            <article data-industry-key="{{ $definition['key'] }}"><span class="industry-icon">{{ $definition['icon'] }}</span><h3>{{ $definition['label'] }}</h3><strong data-industry-cap>{{ is_numeric(data_get($industry, 'market_cap')) ? $formatCompactUsd(data_get($industry, 'market_cap')) : 'Syncing…' }}</strong><em data-industry-change class="{{ $metricTone($industryChange) }}">{{ is_numeric($industryChange) ? $formatChange($industryChange) : 'Live category' }}</em></article>
        @endforeach
        <article class="final-industry-art" aria-hidden="true"><span>✧</span><i></i><i></i><i></i></article>
    </div>
</section>

<section class="container final-market-intelligence" aria-label="Daily market intelligence">
    <div class="final-market-snapshot">
        <div class="final-snapshot-top">
            <article><small>Total Market Cap</small><strong data-market-cap>{{ is_numeric($marketCap) ? '$'.number_format((float) $marketCap / 1e12, 2).'T' : '—' }}</strong><em data-market-cap-change class="{{ $metricTone($marketChange) }}">{{ $formatChange($marketChange) }}</em><svg viewBox="0 0 90 28" aria-hidden="true"><polyline points="2,25 13,21 23,22 34,15 44,17 55,11 66,13 77,6 88,4"/></svg></article>
            <article><small>24H Trading Volume</small><strong data-market-volume>{{ is_numeric($marketVolume) ? '$'.number_format((float) $marketVolume / 1e9, 2).'B' : '—' }}</strong><em class="positive">Live market</em><svg viewBox="0 0 90 28" aria-hidden="true"><polyline points="2,24 13,22 24,23 35,17 46,19 57,14 67,10 79,12 88,5"/></svg></article>
            <article><small>BTC Dominance</small><strong data-btc-dominance>{{ is_numeric($btcDominance) ? number_format((float) $btcDominance, 1).'%' : '—' }}</strong><em data-btc-dominance-change class="{{ $metricTone($btcDominanceChange) }}">{{ is_numeric($btcDominanceChange) ? $formatChange($btcDominanceChange) : 'Live global share' }}</em></article>
            <article class="fear-greed-metric"><small>Fear &amp; Greed Index <span>· Alternative.me</span></small><strong data-fear-greed-score>{{ is_numeric($fearGreedScore) ? (int) $fearGreedScore : '—' }}</strong><em data-fear-greed-label>{{ $fearGreedLabel }}</em><span class="fear-greed-meter"><i style="--fear-score:{{ is_numeric($fearGreedScore) ? (int) $fearGreedScore : 50 }}"></i></span></article>
        </div>
        <div class="final-snapshot-insights">
            <article><small>Market Bias</small><strong data-market-bias>{{ $insights->get('market_bias', 'Syncing…') }}</strong><p data-market-bias-detail>{{ $insights->get('market_bias_detail', 'Waiting for breadth data') }}</p></article>
            <article><small>Stablecoin Flow (24H)</small><strong data-stablecoin-flow>{{ is_numeric($insights->get('stablecoin_flow_24h_usd')) ? $formatSignedCompactUsd($insights->get('stablecoin_flow_24h_usd')) : 'Syncing…' }}</strong><p>Estimated net market-cap flow.</p></article>
            <article><small>Volatility Score</small><strong data-volatility-score>{{ is_numeric($insights->get('volatility_score')) ? number_format((float) $insights->get('volatility_score'), 1) : '—' }}</strong><em data-volatility-label>{{ $insights->get('volatility_label', 'Syncing') }}</em><p>Core-asset 24H range pressure.</p></article>
            <article><small>Key Trend</small><strong data-key-trend>{{ $insights->get('key_trend', 'Syncing…') }}</strong><em data-key-trend-detail>{{ $insights->get('key_trend_detail', 'Live category rotation') }}</em></article>
            <article class="daily-insight"><span class="insight-bulb">☼</span><div><small>Daily Insight</small><p data-daily-insight>{{ $insights->get('daily_insight', 'Live market context is still syncing.') }}</p></div></article>
        </div>
    </div>

    <div class="final-market-movers">
        <h2>Market Movers (24H)</h2>
        <div class="final-movers-columns">
            <article><small>Top Gainers (24H)</small><div data-gainers-list>
                @forelse(collect($movers['gainers'] ?? [])->take(5) as $coin)
                    @php $change = $coin['change_percent'] ?? null; $width = is_numeric($change) ? min(100, max(20, abs((float)$change) * 8)) : 20; @endphp
                    <div class="final-mover-row"><span class="mover-dot gain">{{ substr($coin['base'] ?? '•',0,1) }}</span><b>{{ $coin['base'] ?? '—' }}</b><i><u style="width:{{ $width }}%"></u></i><em class="positive">{{ $formatChange($change) }}</em></div>
                @empty
                    <div class="movers-loading" data-movers-empty>Syncing gainers…</div>
                @endforelse
            </div></article>
            <article><small>Top Losers (24H)</small><div data-losers-list>
                @forelse(collect($movers['losers'] ?? [])->take(5) as $coin)
                    @php $change = $coin['change_percent'] ?? null; $width = is_numeric($change) ? min(100, max(20, abs((float)$change) * 8)) : 20; @endphp
                    <div class="final-mover-row"><span class="mover-dot lose">{{ substr($coin['base'] ?? '•',0,1) }}</span><b>{{ $coin['base'] ?? '—' }}</b><i><u style="width:{{ $width }}%"></u></i><em class="negative">{{ $formatChange($change) }}</em></div>
                @empty
                    <div class="movers-loading">Syncing losers…</div>
                @endforelse
            </div></article>
        </div>
    </div>
</section>

<section class="container final-plans" id="plans" aria-labelledby="plans-title">
    <header><h2 id="plans-title">Choose Your Pulse Package</h2><p>Choose 1, 3, 7 or 30 days of Pulse access. Transfer the package price in USDT and Admin activates access after transaction verification.</p></header>
    <div class="final-plan-grid final-plan-grid-four">
        @forelse($pulsePlans as $plan)
        <article class="{{ $plan->is_featured || $plan->slug === 'pulse-momentum' ? 'recommended' : '' }}">
            <span class="plan-kicker">{{ strtoupper($plan->name) }}</span>@if($plan->badge)<span class="{{ $plan->is_featured || $plan->slug === 'pulse-momentum' ? 'final-recommended' : 'final-plan-badge trial' }}">{{ strtoupper($plan->badge) }}</span>@endif
            <p>{{ $plan->description }}</p>
            <div class="final-plan-price"><strong>{{ $formatPlanPrice($plan) }}</strong><span>/ {{ $plan->access_days }} {{ $plan->access_days === 1 ? 'day' : 'days' }}</span></div>
            <ul><li>15-strategy 15M + 4H analysis</li><li>Up to {{ number_format((int)$plan->max_selected_pairs) }} package markets</li><li>No daily scan or signal quotas</li><li>Best Signal included with active package</li><li>Admin-managed strategy intelligence</li></ul>
            @auth<a class="final-plan-button" href="{{ route('pulse.membership.checkout',$plan) }}">Pay with USDT</a>@else<a class="final-plan-button" href="{{ route('register',['service'=>'pulse']) }}">Get Started</a>@endauth
        </article>
        @empty
        <article><span class="plan-kicker">PULSE ACCESS</span><p>Packages are being prepared.</p><a class="final-plan-button" href="{{ route('register',['service'=>'pulse']) }}">Create Account</a></article>
        @endforelse
    </div>
</section>

<section class="container final-cta" id="contact">
    <div class="final-cta-mark"><img src="{{ asset('assets/brand/abs-logo-512.png') }}" alt="" width="52" height="52"></div>
    <div><h2>Turn Market Noise into <span>Actionable Intelligence</span></h2><p>Join traders, investors, and institutions who rely on Alpha Block Solutions.</p></div>
    <a class="final-button final-button-gold" href="{{ route('register') }}">Get Started with Pulse <span aria-hidden="true">→</span></a>
</section>
@endsection
