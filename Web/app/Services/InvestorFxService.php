<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InvestorFxService
{
    public function normalize(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException('Use a valid three-letter currency code.');
        }
        return $currency;
    }

    public function quoteToUsd(string $currency): array
    {
        $currency = $this->normalize($currency);
        if ($currency === 'USD') {
            return ['currency'=>'USD','rate'=>1.0,'source'=>'native_usd','updated_at'=>now()->toIso8601String()];
        }

        return Cache::remember('abs:investor-fx:'.$currency.':usd', now()->addHours(12), function () use ($currency): array {
            $response = Http::acceptJson()->timeout(6)->retry(1, 200)->get('https://open.er-api.com/v6/latest/'.$currency);
            if (! $response->successful()) {
                throw new RuntimeException('Live FX reference is temporarily unavailable. Enter the locked USD rate manually.');
            }
            $json = $response->json();
            $rate = (float) data_get($json, 'rates.USD', 0);
            if (($json['result'] ?? null) !== 'success' || $rate <= 0) {
                throw new RuntimeException('No USD reference rate was returned for '.$currency.'. Enter the locked rate manually.');
            }
            return [
                'currency'=>$currency,
                'rate'=>$rate,
                'source'=>'exchange_rate_api',
                'updated_at'=>$json['time_last_update_utc'] ?? now()->toIso8601String(),
            ];
        });
    }

    public function convert(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }
}
