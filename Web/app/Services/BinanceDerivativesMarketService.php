<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BinanceDerivativesMarketService
{
    private function baseUrls(): array
    {
        $configured = config('services.binance_futures_market.base_urls', []);
        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_values(array_unique(array_filter(array_merge(
            (array) $configured,
            [(string) config('services.binance_futures_market.base_url', 'https://fapi.binance.com')]
        ))));
    }

    private function client(string $baseUrl): PendingRequest
    {
        $verify = config('services.market.ssl_verify', true);
        $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-MarketData/14.6.11'])
            ->withOptions(['verify' => $verify])
            ->connectTimeout((int) config('services.market.connect_timeout', 3))
            ->timeout((int) config('services.market.timeout', 7))
            ->retry(1, 250, throw: false);
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

        throw new RuntimeException('All Binance Futures public market-data hosts failed. '.implode(' | ', $errors));
    }

    public function openInterest(string $symbol = 'BTCUSDT'): array
    {
        $response = $this->get('/fapi/v1/openInterest', ['symbol' => strtoupper($symbol)]);
        $payload = $response->json();
        if (! is_array($payload) || ! is_numeric($payload['openInterest'] ?? null)) {
            throw new RuntimeException('Binance Futures returned an unexpected open-interest response.');
        }

        return $payload;
    }

    public function premiumIndex(string $symbol = 'BTCUSDT'): array
    {
        $response = $this->get('/fapi/v1/premiumIndex', ['symbol' => strtoupper($symbol)]);
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Binance Futures returned an unexpected premium-index response.');
        }

        return $payload;
    }

    public function globalLongShortAccountRatio(string $symbol = 'BTCUSDT', string $period = '5m'): array
    {
        $response = $this->get('/futures/data/globalLongShortAccountRatio', [
            'symbol' => strtoupper($symbol),
            'period' => $period,
            'limit' => 1,
        ]);
        $payload = $response->json();
        $row = is_array($payload) ? ($payload[0] ?? null) : null;
        if (! is_array($row)) {
            throw new RuntimeException('Binance Futures returned an unexpected long/short-ratio response.');
        }

        $ratio = is_numeric($row['longShortRatio'] ?? null) ? (float) $row['longShortRatio'] : null;
        if (($ratio === null || $ratio <= 0) && is_numeric($row['longAccount'] ?? null) && is_numeric($row['shortAccount'] ?? null) && (float) $row['shortAccount'] > 0) {
            $ratio = (float) $row['longAccount'] / (float) $row['shortAccount'];
            $row['longShortRatio'] = $ratio;
        }
        if ($ratio === null || $ratio <= 0 || $ratio > 100) {
            throw new RuntimeException('Binance Futures returned an invalid long/short-ratio value.');
        }

        return $row;
    }
}
