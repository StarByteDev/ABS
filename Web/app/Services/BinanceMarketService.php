<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BinanceMarketService
{
    /**
     * Public market-data hosts are tried in order. No API key is required.
     */
    private function baseUrls(): array
    {
        $configured = config('services.binance.base_urls', []);
        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_values(array_unique(array_filter(array_merge(
            (array) $configured,
            [(string) config('services.binance.base_url', 'https://data-api.binance.vision')]
        ))));
    }

    private function client(string $baseUrl): PendingRequest
    {
        $verify = config('services.market.ssl_verify', true);
        $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-MarketData/12.5'])
            ->withOptions(['verify' => $verify])
            ->connectTimeout((int) config('services.market.connect_timeout', 5))
            ->timeout((int) config('services.market.timeout', 10))
            ->retry(2, 250, throw: false);
    }

    private function get(string $path, array $query = []): Response
    {
        $errors = [];

        foreach ($this->baseUrls() as $baseUrl) {
            try {
                $response = $this->client($baseUrl)->get($path, $query);
                if ($response->successful()) {
                    return $response;
                }
                $errors[] = $baseUrl.' returned HTTP '.$response->status();
            } catch (Throwable $e) {
                $errors[] = $baseUrl.' failed: '.$e->getMessage();
            }
        }

        throw new RuntimeException('All Binance public market-data hosts failed. '.implode(' | ', $errors));
    }

    public function ticker24(array $symbols): array
    {
        $response = $this->get('/api/v3/ticker/24hr', [
            'symbols' => json_encode(array_values($symbols), JSON_THROW_ON_ERROR),
        ]);

        return (array) $response->json();
    }

    public function allTickers24(): array
    {
        return (array) $this->get('/api/v3/ticker/24hr')->json();
    }

    public function klines(string $symbol, string $interval = '1h', int $limit = 80): array
    {
        $response = $this->get('/api/v3/klines', [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => min(max($limit, 10), 500),
        ]);

        return collect($response->json())->map(fn (array $row) => [
            'time' => (int) floor(((int) $row[0]) / 1000),
            'open' => (float) $row[1],
            'high' => (float) $row[2],
            'low' => (float) $row[3],
            'close' => (float) $row[4],
            'volume' => (float) $row[5],
        ])->all();
    }
}
