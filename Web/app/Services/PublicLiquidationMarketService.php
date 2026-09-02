<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PublicLiquidationMarketService
{
    private function client(): PendingRequest
    {
        $verify = config('services.market.ssl_verify', true);
        $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);

        return Http::baseUrl(rtrim((string) config('services.liquidations.base_url', 'https://xoomar.com'), '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-MarketData/14.6.10'])
            ->withOptions(['verify' => $verify])
            ->connectTimeout((int) config('services.market.connect_timeout', 3))
            ->timeout((int) config('services.market.timeout', 7))
            ->retry(1, 350, throw: false);
    }

    public function summary24h(): array
    {
        $response = $this->client()->get('/api/markets/liquidations');
        if (! $response->successful()) {
            throw new RuntimeException('Public liquidation request failed with HTTP '.$response->status());
        }

        $payload = $response->json();
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($data)) {
            throw new RuntimeException('Public liquidation API returned an unexpected response.');
        }

        $long = is_numeric($data['longUsd'] ?? null) ? max(0.0, (float) $data['longUsd']) : null;
        $short = is_numeric($data['shortUsd'] ?? null) ? max(0.0, (float) $data['shortUsd']) : null;
        $total = is_numeric($data['totalUsd'] ?? null) ? max(0.0, (float) $data['totalUsd']) : null;

        if ($total === null && $long !== null && $short !== null) {
            $total = $long + $short;
        }
        if ($long === null || $short === null || $total === null) {
            throw new RuntimeException('Public liquidation API did not provide the expected 24H totals.');
        }

        return [
            'period' => (string) ($data['period'] ?? '24h'),
            'total_usd' => $total,
            'long_usd' => $long,
            'short_usd' => $short,
            'max_single_usd' => is_numeric($data['maxSingleUsd'] ?? null) ? max(0.0, (float) $data['maxSingleUsd']) : null,
            'updated_at' => is_string($payload['updatedAt'] ?? null) ? $payload['updatedAt'] : null,
            'source' => 'Xoomar aggregated liquidations',
        ];
    }
}
