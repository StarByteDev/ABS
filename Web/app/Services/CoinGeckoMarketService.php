<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoinGeckoMarketService
{
    public function client(): PendingRequest
    {
        $verify = config('services.market.ssl_verify', true);
        $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);

        $client = Http::baseUrl(rtrim((string) config('services.coingecko.base_url'), '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-MarketData/12.5'])
            ->withOptions(['verify' => $verify])
            ->connectTimeout((int) config('services.market.connect_timeout', 5))
            ->timeout((int) config('services.market.timeout', 10))
            ->retry(2, 300, throw: false);

        $key = config('services.coingecko.api_key');
        return $key ? $client->withHeaders(['x-cg-demo-api-key' => $key]) : $client;
    }

    public function global(): array
    {
        $response = $this->client()->get('/global');
        if (! $response->successful()) {
            throw new RuntimeException('CoinGecko global request failed with HTTP '.$response->status());
        }

        return (array) data_get($response->json(), 'data', []);
    }

    public function markets(int $perPage = 50): array
    {
        $response = $this->client()->get('/coins/markets', [
            'vs_currency' => 'usd',
            'order' => 'market_cap_desc',
            'per_page' => min(max($perPage, 5), 100),
            'page' => 1,
            'sparkline' => 'true',
            'price_change_percentage' => '24h,7d',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('CoinGecko markets request failed with HTTP '.$response->status());
        }

        return (array) $response->json();
    }
    public function categories(): array
    {
        $response = $this->client()->get('/coins/categories', [
            'order' => 'market_cap_desc',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('CoinGecko categories request failed with HTTP '.$response->status());
        }

        return (array) $response->json();
    }

    public function stablecoins(int $perPage = 100): array
    {
        $response = $this->client()->get('/coins/markets', [
            'vs_currency' => 'usd',
            'category' => 'stablecoins',
            'order' => 'market_cap_desc',
            'per_page' => min(max($perPage, 10), 100),
            'page' => 1,
            'sparkline' => 'false',
            'price_change_percentage' => '24h',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('CoinGecko stablecoin request failed with HTTP '.$response->status());
        }

        return (array) $response->json();
    }

}
