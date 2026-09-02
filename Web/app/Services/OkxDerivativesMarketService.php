<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OkxDerivativesMarketService
{
    private function baseUrls(): array
    {
        $configured = config('services.okx_market.base_urls', []);
        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_values(array_unique(array_filter(array_merge(
            (array) $configured,
            [(string) config('services.okx_market.base_url', 'https://www.okx.com')]
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
        throw new RuntimeException('OKX public derivatives API unavailable. '.implode(' | ', $errors));
    }

    private function firstRow(Response $response): array
    {
        $payload = $response->json();
        if (! is_array($payload) || (string) ($payload['code'] ?? '0') !== '0') {
            throw new RuntimeException('OKX returned an unexpected response.');
        }
        $data = $payload['data'] ?? null;
        $row = is_array($data) ? ($data[0] ?? null) : null;
        if (! is_array($row)) throw new RuntimeException('OKX returned no market-data row.');
        return $row;
    }

    public function snapshot(string $instrument = 'BTC-USDT-SWAP'): array
    {
        $markRow = $this->firstRow($this->get('/api/v5/public/mark-price', [
            'instType' => 'SWAP',
            'instId' => $instrument,
        ]));
        $markPrice = is_numeric($markRow['markPx'] ?? null) ? (float) $markRow['markPx'] : null;

        $openRow = $this->firstRow($this->get('/api/v5/public/open-interest', [
            'instType' => 'SWAP',
            'instId' => $instrument,
        ]));

        $fundRow = $this->firstRow($this->get('/api/v5/public/funding-rate', [
            'instId' => $instrument,
        ]));

        $ratio = null;
        try {
            $ratioResponse = $this->get('/api/v5/rubik/stat/contracts/long-short-account-ratio', [
                'ccy' => 'BTC',
                'period' => '5m',
            ]);
            $payload = $ratioResponse->json();
            $data = is_array($payload) ? ($payload['data'] ?? null) : null;
            $latest = is_array($data) ? ($data[0] ?? null) : null;
            if (is_array($latest)) {
                if (isset($latest['ratio']) && is_numeric($latest['ratio'])) {
                    $ratio = (float) $latest['ratio'];
                } else {
                    foreach (array_reverse($latest) as $value) {
                        if (is_numeric($value)) { $ratio = (float) $value; break; }
                    }
                }
            }
        } catch (Throwable) {
            // The other futures metrics remain usable when the ratio endpoint is restricted.
        }
        if ($ratio !== null && ($ratio <= 0 || $ratio > 100)) {
            $ratio = null;
        }

        $openUsd = null;
        if (is_numeric($openRow['oiUsd'] ?? null)) {
            $openUsd = abs((float) $openRow['oiUsd']);
        } elseif (is_numeric($openRow['oiCcy'] ?? null) && $markPrice !== null) {
            $openUsd = abs((float) $openRow['oiCcy'] * $markPrice);
        } elseif (is_numeric($openRow['oi'] ?? null) && $markPrice !== null) {
            $openUsd = abs((float) $openRow['oi'] * $markPrice);
        }

        $funding = is_numeric($fundRow['fundingRate'] ?? null) ? (float) $fundRow['fundingRate'] * 100 : null;
        $premium = is_numeric($fundRow['premium'] ?? null) ? (float) $fundRow['premium'] * 100 : null;

        return [
            'open_interest_usd' => $openUsd,
            'funding_rate' => $funding,
            'long_short_ratio' => $ratio,
            'perp_premium_basis' => $premium,
            'futures_mark_price' => $markPrice,
            'futures_source' => 'OKX Public Derivatives',
        ];
    }
}
