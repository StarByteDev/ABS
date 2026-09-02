<?php

namespace Tests\Unit;

use App\Services\AlternativeFearGreedService;
use App\Services\PublicLiquidationMarketService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicHomepageProviderTest extends TestCase
{
    public function test_public_liquidation_provider_parses_24h_long_short_totals(): void
    {
        config(['services.liquidations.base_url' => 'https://xoomar.test']);
        Http::fake([
            'xoomar.test/api/markets/liquidations' => Http::response([
                'data' => [
                    'period' => '24h',
                    'totalUsd' => 210900000,
                    'longUsd' => 124600000,
                    'shortUsd' => 86300000,
                    'maxSingleUsd' => 9500000,
                ],
                'updatedAt' => '2026-08-22T00:00:00.000Z',
                'source' => 'xoomar.com',
            ]),
        ]);

        $data = app(PublicLiquidationMarketService::class)->summary24h();

        $this->assertSame(210900000.0, $data['total_usd']);
        $this->assertSame(124600000.0, $data['long_usd']);
        $this->assertSame(86300000.0, $data['short_usd']);
        $this->assertSame('24h', $data['period']);
    }

    public function test_fear_greed_provider_parses_score_and_label(): void
    {
        config(['services.fear_greed.base_url' => 'https://alternative.test']);
        Http::fake([
            'alternative.test/fng/*' => Http::response([
                'data' => [[
                    'value' => '72',
                    'value_classification' => 'Greed',
                    'timestamp' => '1787356800',
                ]],
                'metadata' => ['error' => null],
            ]),
        ]);

        $data = app(AlternativeFearGreedService::class)->latest();

        $this->assertSame(72, $data['score']);
        $this->assertSame('Greed', $data['label']);
        $this->assertSame('Alternative.me', $data['source']);
    }
}
