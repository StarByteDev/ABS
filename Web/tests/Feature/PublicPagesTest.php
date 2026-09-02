<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_available_and_requests_five_verified_headlines(): void
    {
        $this->seed();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Alpha Block Solutions')
            ->assertSee('Navigate Digital')
            ->assertSee('assets/css/abs-app.css', false)
            ->assertSee('assets/js/abs-app.js', false)
            ->assertSee('data-live-news-limit="5"', false)
            ->assertSee('data-chart-range="1d"', false)
            ->assertSee(route('pulse.entry'), false)
            ->assertSee('Latest Verified Headlines');
    }

    public function test_market_api_returns_a_cached_verified_provider_snapshot_without_network_dependency(): void
    {
        Cache::put('abs.market.overview.v6', [
            'core' => [[
                'symbol' => 'BTCUSDT', 'pair' => 'BTC/USDT', 'base' => 'BTC', 'quote' => 'USDT',
                'price' => 65000.0, 'change_percent' => 1.25, 'high' => 66000.0, 'low' => 64000.0,
                'volume' => 1000000.0, 'quote_volume' => 65000000000.0,
            ]],
            'global' => [
                'total_market_cap' => 2500000000000.0,
                'total_volume' => 90000000000.0,
                'btc_dominance' => 52.0,
                'btc_dominance_change_24h' => 0.4,
                'market_cap_change_24h' => 1.1,
                'stablecoin_market_cap' => 245000000000.0,
                'stablecoin_market_cap_change_24h' => 0.3,
                'liquidation_24h_usd' => 315000000.0,
                'liquidation_long_24h_usd' => 205000000.0,
                'liquidation_short_24h_usd' => 110000000.0,
                'liquidation_long_share_24h' => 65.08,
                'liquidation_short_share_24h' => 34.92,
                'open_interest_usd' => 18400000000.0,
                'funding_rate' => 0.01,
                'fear_greed_score' => 72,
                'fear_greed_label' => 'Greed',
                'active_cryptocurrencies' => 10000,
                'source' => 'CoinGecko + Binance',
            ],
            'industries' => [],
            'insights' => ['market_bias' => 'Bullish', 'market_bias_detail' => '83% of core assets positive', 'stablecoin_flow_24h_usd' => 500000000.0, 'volatility_score' => 52.1, 'volatility_label' => 'Moderate', 'key_trend' => 'DeFi', 'key_trend_detail' => 'Leading industry today', 'daily_insight' => 'Momentum remains constructive.'],
            'sentiment' => ['score' => 63, 'label' => 'Bullish', 'bullish' => 63, 'neutral' => 24, 'bearish' => 13],
            'pulse' => ['score' => 68, 'label' => 'Bullish', 'method' => 'test fixture'],
            'source' => 'Binance + CoinGecko', 'is_live' => true, 'is_fallback' => false, 'status' => 'live', 'errors' => [], 'updated_at' => now()->toIso8601String(),
        ], 60);

        $this->getJson('/api/v1/market/overview')
            ->assertOk()
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.is_fallback', false)
            ->assertJsonPath('data.core.0.symbol', 'BTCUSDT');
    }

    public function test_chart_api_reads_each_cached_homepage_time_range_without_external_network(): void
    {
        foreach ([['15m', 96], ['1h', 168], ['4h', 180], ['1d', 365]] as [$interval, $limit]) {
            $candles = [];
            for ($i = 0; $i < $limit; $i++) {
                $candles[] = ['time' => 1700000000 + ($i * 60), 'open' => 100 + $i, 'high' => 102 + $i, 'low' => 99 + $i, 'close' => 101 + $i, 'volume' => 10 + $i];
            }
            Cache::put("abs.market.chart.v2.BTCUSDT.{$interval}.{$limit}", [
                'symbol' => 'BTCUSDT', 'interval' => $interval, 'candles' => $candles,
                'source' => 'Binance', 'is_live' => true, 'is_fallback' => false, 'status' => 'live', 'updated_at' => now()->toIso8601String(),
            ], 60);

            $this->getJson("/api/v1/market/chart/BTCUSDT?interval={$interval}&limit={$limit}")
                ->assertOk()
                ->assertJsonPath('data.interval', $interval)
                ->assertJsonPath('data.is_fallback', false)
                ->assertJsonCount($limit, 'data.candles');
        }
    }
    public function test_registration_and_legal_pages_use_the_final_abs_customer_experience(): void
    {
        $this->seed();

        $this->get('/register?service=pulse')
            ->assertOk()
            ->assertSee('Join Alpha Block Solutions')
            ->assertSee('Phone or WhatsApp Number')
            ->assertSee('Pulse Trial Eligibility')
            ->assertSee('Services in One Place')
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('Privacy Policy')
            ->assertDontSee('Create Account for Pulse')
            ->assertDontSee('Admin-Controlled Access')
            ->assertDontSee('purpose');

        $this->get('/legal/terms')
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Market Information &amp; Educational Use', false)
            ->assertSee('Payments &amp; Plan Activation', false);

        $this->get('/legal/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Information We Collect')
            ->assertSee('Payment Information');
    }

}
