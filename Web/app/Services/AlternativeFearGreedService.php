<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AlternativeFearGreedService
{
    private function client(): PendingRequest
    {
        $verify = config('services.market.ssl_verify', true);
        $verify = is_bool($verify) ? $verify : filter_var($verify, FILTER_VALIDATE_BOOL);

        return Http::baseUrl(rtrim((string) config('services.fear_greed.base_url', 'https://api.alternative.me'), '/'))
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-MarketData/14.6.10'])
            ->withOptions(['verify' => $verify])
            ->connectTimeout((int) config('services.market.connect_timeout', 3))
            ->timeout((int) config('services.market.timeout', 7))
            ->retry(1, 250, throw: false);
    }

    public function latest(): array
    {
        $response = $this->client()->get('/fng/', ['limit' => 1, 'format' => 'json']);
        if (! $response->successful()) {
            throw new RuntimeException('Fear & Greed request failed with HTTP '.$response->status());
        }

        $row = data_get($response->json(), 'data.0');
        if (! is_array($row) || ! is_numeric($row['value'] ?? null)) {
            throw new RuntimeException('Fear & Greed API returned an unexpected response.');
        }

        return [
            'score' => min(100, max(0, (int) $row['value'])),
            'label' => trim((string) ($row['value_classification'] ?? 'Unknown')) ?: 'Unknown',
            'timestamp' => is_numeric($row['timestamp'] ?? null) ? (int) $row['timestamp'] : null,
            'source' => 'Alternative.me',
        ];
    }
}
