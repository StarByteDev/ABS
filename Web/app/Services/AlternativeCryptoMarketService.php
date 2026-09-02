<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AlternativeCryptoMarketService
{
    private function baseUrls(): array
    {
        $configured = config('services.alternative_crypto.base_urls', []);
        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_values(array_unique(array_filter(array_merge(
            (array) $configured,
            [(string) config('services.alternative_crypto.base_url', 'https://api.alternative.me')]
        ))));
    }

    private function client(string $baseUrl): PendingRequest
    {
        $verify = config('services.market.ssl_verify', true);
        $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-MarketData/14.7.2'])
            ->withOptions(['verify' => $verify])
            ->connectTimeout((int) config('services.market.connect_timeout', 3))
            ->timeout((int) config('services.market.timeout', 7))
            ->retry(1, 300, throw: false);
    }

    private function get(string $path, array $query = []): Response
    {
        $errors = [];
        foreach ($this->baseUrls() as $baseUrl) {
            try {
                $response = $this->client($baseUrl)->get($path, $query);
                if ($response->successful()) return $response;
                $errors[] = $baseUrl.' returned HTTP '.$response->status();
            } catch (Throwable $e) {
                $errors[] = $baseUrl.' failed: '.$e->getMessage();
            }
        }

        throw new RuntimeException('Alternative.me crypto market API unavailable. '.implode(' | ', $errors));
    }

    public function global(): array
    {
        $response = $this->get('/v2/global/');
        $payload = $response->json();
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($data)) {
            throw new RuntimeException('Alternative.me global market API returned an unexpected response.');
        }

        return [
            'total_market_cap' => is_numeric(data_get($data, 'quotes.USD.total_market_cap')) ? (float) data_get($data, 'quotes.USD.total_market_cap') : null,
            'total_volume' => is_numeric(data_get($data, 'quotes.USD.total_volume_24h')) ? (float) data_get($data, 'quotes.USD.total_volume_24h') : null,
            'btc_dominance' => is_numeric($data['bitcoin_percentage_of_market_cap'] ?? null) ? (float) $data['bitcoin_percentage_of_market_cap'] : null,
            'active_cryptocurrencies' => is_numeric($data['active_cryptocurrencies'] ?? null) ? (int) $data['active_cryptocurrencies'] : null,
            'source' => 'Alternative.me Crypto API',
        ];
    }
}
