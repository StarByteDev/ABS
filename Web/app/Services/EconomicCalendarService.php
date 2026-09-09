<?php

namespace App\Services;

use App\Models\EconomicEvent;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EconomicCalendarService
{
    public function __construct(private readonly MacroImpactInterpreter $interpreter) {}

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function providerName(): string
    {
        return 'Financial Modeling Prep';
    }

    public function sync(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $key = $this->apiKey();
        if ($key === '') throw new RuntimeException('Economic Calendar API key is not configured. Add the FMP API key in Admin → Economic Calendar CMS.');

        $from ??= CarbonImmutable::now(config('app.timezone'))->subDays(30)->startOfDay();
        $to ??= CarbonImmutable::now(config('app.timezone'))->addDays(45)->endOfDay();

        $response = Http::acceptJson()
            ->withHeaders(['User-Agent' => 'AlphaBlockSolutions-EconomicCalendar/15.1.6'])
            ->withOptions(['verify' => config('services.market.ssl_verify', true)])
            ->connectTimeout(4)->timeout(12)
            ->get((string) config('services.fmp.economic_calendar_url', 'https://financialmodelingprep.com/stable/economic-calendar'), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'apikey' => $key,
            ]);

        if (! $response->successful()) throw new RuntimeException('Economic Calendar provider returned HTTP '.$response->status().'.');
        $payload = $response->json();
        if (! is_array($payload)) throw new RuntimeException('Economic Calendar provider returned an unexpected response.');
        if (isset($payload['Error Message']) || isset($payload['error'])) throw new RuntimeException((string) ($payload['Error Message'] ?? $payload['error']));

        $created = $updated = $skipped = 0;
        foreach ($payload as $row) {
            if (! is_array($row)) { $skipped++; continue; }
            $title = trim((string) ($row['event'] ?? $row['name'] ?? $row['title'] ?? ''));
            $dateRaw = $row['date'] ?? $row['eventDate'] ?? $row['datetime'] ?? null;
            if ($title === '' || ! $dateRaw) { $skipped++; continue; }

            try { $eventAt = CarbonImmutable::parse((string) $dateRaw, 'UTC')->setTimezone(config('app.timezone')); }
            catch (Throwable) { $skipped++; continue; }

            $country = trim((string) ($row['country'] ?? '')) ?: null;
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')) ?: $this->currencyForCountry($country));
            $actual = $this->stringValue($row['actual'] ?? null);
            $forecast = $this->stringValue($row['estimate'] ?? $row['forecast'] ?? null);
            $previous = $this->stringValue($row['previous'] ?? null);
            $interpretation = $this->interpreter->interpret($title, $actual, $forecast, $previous);
            $impact = $this->interpreter->classifyImportance($title, $this->stringValue($row['impact'] ?? $row['importance'] ?? null));
            $providerId = (string) ($row['id'] ?? sha1($eventAt->utc()->format('Y-m-d H:i:s').'|'.$country.'|'.$currency.'|'.Str::lower($title)));

            $values = [
                'title' => $title,
                'country' => $country,
                'currency' => $currency ?: null,
                'impact' => $impact,
                'event_at' => $eventAt,
                'previous_value' => $previous,
                'forecast_value' => $forecast,
                'actual_value' => $actual,
                'source' => $this->providerName(),
                'source_url' => 'https://site.financialmodelingprep.com/developer/docs/stable/economics-calendar',
                'provider_event_id' => $providerId,
                'crypto_impact' => $interpretation['crypto_impact'],
                'easy_explanation' => $interpretation['easy_explanation'],
                'crypto_impact_summary' => $interpretation['crypto_impact_summary'],
                'is_crypto_relevant' => (bool) $interpretation['is_crypto_relevant'] || $impact === 'high',
                'synced_at' => now(),
            ];

            $existing = EconomicEvent::query()->where('provider_event_id', $providerId)->first();
            if (! $existing) {
                EconomicEvent::create($values);
                $created++;
            } else {
                $existing->update($values);
                $updated++;
            }
        }

        SiteSetting::updateOrCreate(['key' => 'economic_calendar_last_sync_at'], [
            'value' => now()->toIso8601String(), 'type' => 'string', 'group' => 'integrations',
        ]);
        SiteSetting::updateOrCreate(['key' => 'economic_calendar_last_sync_status'], [
            'value' => 'success', 'type' => 'string', 'group' => 'integrations',
        ]);

        return compact('created', 'updated', 'skipped') + ['total' => $created + $updated, 'from' => $from, 'to' => $to];
    }

    public function saveApiKey(?string $key): void
    {
        $key = trim((string) $key);
        if ($key === '') return;
        SiteSetting::updateOrCreate(['key' => 'economic_calendar_fmp_api_key'], [
            'value' => Crypt::encryptString($key), 'type' => 'encrypted', 'group' => 'integrations',
        ]);
    }

    public function clearApiKey(): void
    {
        SiteSetting::query()->where('key', 'economic_calendar_fmp_api_key')->delete();
    }

    public function setAutoSync(bool $enabled): void
    {
        SiteSetting::updateOrCreate(['key' => 'economic_calendar_auto_sync'], [
            'value' => $enabled ? '1' : '0', 'type' => 'boolean', 'group' => 'integrations',
        ]);
    }

    public function autoSyncEnabled(): bool
    {
        $db = SiteSetting::query()->where('key', 'economic_calendar_auto_sync')->value('value');
        if ($db !== null) return filter_var($db, FILTER_VALIDATE_BOOL);
        return true;
    }

    public function lastSyncAt(): ?string
    {
        return SiteSetting::query()->where('key', 'economic_calendar_last_sync_at')->value('value');
    }

    private function apiKey(): string
    {
        $env = trim((string) config('services.fmp.api_key', ''));
        if ($env !== '') return $env;
        try {
            $encrypted = SiteSetting::query()->where('key', 'economic_calendar_fmp_api_key')->value('value');
            if (! $encrypted) return '';
            return trim(Crypt::decryptString((string) $encrypted));
        } catch (Throwable $e) {
            Log::warning('ABS economic calendar API key could not be decrypted', ['message' => $e->getMessage()]);
            return '';
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_bool($value)) return $value ? '1' : '0';
        if (! is_scalar($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function currencyForCountry(?string $country): string
    {
        $country = Str::lower(trim((string) $country));
        return match ($country) {
            'united states', 'us', 'usa' => 'USD',
            'euro area', 'eurozone' => 'EUR',
            'united kingdom', 'uk' => 'GBP',
            'japan' => 'JPY',
            'canada' => 'CAD',
            'australia' => 'AUD',
            'new zealand' => 'NZD',
            'switzerland' => 'CHF',
            'china' => 'CNY',
            default => '',
        };
    }
}
