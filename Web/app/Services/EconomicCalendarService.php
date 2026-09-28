<?php

namespace App\Services;

use App\Models\EconomicEvent;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
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
        return $this->primaryConfigured()
            || $this->financeCalendarEnabled()
            || $this->xoomarEnabled()
            || $this->tradingEconomicsEnabled();
    }

    public function primaryConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function providerName(): string
    {
        $last = (string) SiteSetting::query()->where('key', 'economic_calendar_last_sync_detail')->value('value');
        if (in_array($last, ['Financial Modeling Prep', 'Finance Calendar', 'Xoomar Macro Calendar', 'Trading Economics'], true)) {
            return $last;
        }

        if ($this->primaryConfigured()) return 'Financial Modeling Prep';
        if ($this->financeCalendarEnabled()) return 'Finance Calendar';
        if ($this->xoomarEnabled()) return 'Xoomar Macro Calendar';
        if ($this->tradingEconomicsEnabled()) return 'Trading Economics';
        return 'Economic Calendar';
    }

    public function sync(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= CarbonImmutable::now(config('app.timezone'))->subDays(30)->startOfDay();
        $to ??= CarbonImmutable::now(config('app.timezone'))->addDays(45)->endOfDay();

        $attempts = [];
        $provider = null;
        $rows = [];

        if ($this->primaryConfigured()) {
            try {
                $rows = $this->fetchFmpRows($from, $to);
                $provider = 'Financial Modeling Prep';
            } catch (Throwable $e) {
                $attempts[] = 'FMP: '.$e->getMessage();
                Log::warning('ABS Economic Calendar FMP source unavailable', ['message' => $e->getMessage()]);
            }
        }

        if ($provider === null && $this->financeCalendarEnabled()) {
            try {
                $rows = $this->fetchFinanceCalendarRows($from, $to);
                $provider = 'Finance Calendar';
            } catch (Throwable $e) {
                $attempts[] = 'Finance Calendar: '.$e->getMessage();
                Log::warning('ABS Economic Calendar Finance Calendar source unavailable', ['message' => $e->getMessage()]);
            }
        }

        if ($provider === null && $this->xoomarEnabled()) {
            try {
                $rows = $this->fetchXoomarRows($from, $to);
                $provider = 'Xoomar Macro Calendar';
            } catch (Throwable $e) {
                $attempts[] = 'Xoomar: '.$e->getMessage();
                Log::warning('ABS Economic Calendar Xoomar source unavailable', ['message' => $e->getMessage()]);
            }
        }

        if ($provider === null && $this->tradingEconomicsEnabled()) {
            try {
                $rows = $this->fetchTradingEconomicsRows($from, $to);
                $provider = 'Trading Economics';
            } catch (Throwable $e) {
                $attempts[] = 'Trading Economics: '.$e->getMessage();
                Log::warning('ABS Economic Calendar Trading Economics source unavailable', ['message' => $e->getMessage()]);
            }
        }

        if ($provider === null) {
            $message = $attempts
                ? 'Economic Calendar refresh failed. '.implode(' | ', $attempts)
                : 'Economic Calendar has no enabled source.';
            $this->recordSyncStatus('failed', $message);
            throw new RuntimeException($message);
        }

        $result = match ($provider) {
            'Financial Modeling Prep' => $this->persistFmpRows($rows),
            'Finance Calendar' => $this->persistFinanceCalendarRows($rows),
            'Xoomar Macro Calendar' => $this->persistXoomarRows($rows),
            default => $this->persistTradingEconomicsRows($rows),
        };

        if (($result['created'] + $result['updated']) < 1) {
            $message = $provider.' returned no usable economic-calendar events for the selected window.';
            $this->recordSyncStatus('failed', $message);
            throw new RuntimeException($message);
        }

        $this->recordSyncStatus('success', $provider);

        return $result + [
            'provider' => $provider,
            'total' => $result['created'] + $result['updated'],
            'from' => $from,
            'to' => $to,
        ];
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

    public function lastSyncStatus(): ?string
    {
        return SiteSetting::query()->where('key', 'economic_calendar_last_sync_status')->value('value');
    }

    private function fetchFmpRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $key = $this->apiKey();
        if ($key === '') throw new RuntimeException('Financial Modeling Prep API key is not configured.');

        $response = $this->http('AlphaBlockSolutions-EconomicCalendar/15.6.5')
            ->get((string) config('services.fmp.economic_calendar_url', 'https://financialmodelingprep.com/stable/economic-calendar'), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'apikey' => $key,
            ]);

        return $this->jsonRows($response, 'Financial Modeling Prep');
    }

    private function fetchFinanceCalendarRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $response = $this->http('AlphaBlockSolutions-MacroCalendar/15.6.5')
            ->get((string) config('services.finance_calendar.calendar_url', 'https://www.financecalendar.com/wp-json/fc/v1/calendar'), [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'limit' => 500,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Finance Calendar returned HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) throw new RuntimeException('Finance Calendar returned an unexpected response.');

        $rows = $payload['events'] ?? $payload['data'] ?? $payload['calendar'] ?? $payload;
        if (! is_array($rows)) throw new RuntimeException('Finance Calendar returned no event collection.');

        return array_values(array_filter($rows, 'is_array'));
    }

    private function fetchXoomarRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $request = $this->http('AlphaBlockSolutions-MacroCalendar/15.6.5');
        $key = trim((string) config('services.xoomar_calendar.api_key', ''));
        if ($key !== '') $request = $request->withHeader('X-API-Key', $key);

        $response = $request->get((string) config('services.xoomar_calendar.calendar_url', 'https://xoomar.com/api/markets/calendar'), [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Xoomar returned HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) throw new RuntimeException('Xoomar returned an unexpected response.');
        $rows = $payload['data'] ?? [];
        if (! is_array($rows)) throw new RuntimeException('Xoomar returned no event collection.');
        return array_values(array_filter($rows, 'is_array'));
    }

    private function fetchTradingEconomicsRows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = rtrim((string) config('services.trading_economics.calendar_url', 'https://api.tradingeconomics.com/calendar'), '/');
        $key = trim((string) config('services.trading_economics.api_key', ''));
        if ($key === '') throw new RuntimeException('Trading Economics API credentials are not configured.');

        $rangeUrl = $base.'/country/united%20states/'.$from->toDateString().'/'.$to->toDateString();
        $response = $this->http('AlphaBlockSolutions-EconomicCalendar/15.6.5')
            ->get($rangeUrl, ['c' => $key, 'f' => 'json']);

        if ($response->successful()) {
            $rows = $response->json();
            if (is_array($rows) && ! isset($rows['error']) && ! isset($rows['Error Message'])) {
                $normalized = array_values(array_filter($rows, 'is_array'));
                if (count($normalized) > 0) return $normalized;
            }
        }

        $snapshot = $this->http('AlphaBlockSolutions-EconomicCalendar/15.6.5')
            ->get($base, ['c' => $key, 'f' => 'json']);

        return $this->jsonRows($snapshot, 'Trading Economics');
    }

    private function http(string $userAgent)
    {
        return Http::acceptJson()
            ->withHeaders(['User-Agent' => $userAgent])
            ->withOptions(['verify' => config('services.market.ssl_verify', true)])
            ->connectTimeout(4)
            ->timeout(12)
            ->retry(1, 250, throw: false);
    }

    private function jsonRows(Response $response, string $provider): array
    {
        if (! $response->successful()) {
            throw new RuntimeException($provider.' returned HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (! is_array($payload)) throw new RuntimeException($provider.' returned an unexpected response.');

        $error = $payload['Error Message'] ?? $payload['error'] ?? $payload['message'] ?? null;
        if (is_string($error) && trim($error) !== '') throw new RuntimeException($provider.': '.trim($error));

        return array_values(array_filter($payload, 'is_array'));
    }

    private function persistFmpRows(array $payload): array
    {
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
            $providerId = 'fmp:'.(string) ($row['id'] ?? sha1($eventAt->utc()->format('Y-m-d H:i:s').'|'.$country.'|'.$currency.'|'.Str::lower($title)));

            [$created, $updated] = $this->upsertEvent($this->eventValues(
                title: $title,
                country: $country,
                currency: $currency ?: null,
                impact: $impact,
                eventAt: $eventAt,
                previous: $previous,
                forecast: $forecast,
                actual: $actual,
                source: 'Financial Modeling Prep',
                sourceUrl: 'https://site.financialmodelingprep.com/developer/docs/stable/economics-calendar',
                providerId: $providerId,
                interpretation: $interpretation,
            ), $created, $updated);
        }

        return compact('created', 'updated', 'skipped');
    }

    private function persistFinanceCalendarRows(array $payload): array
    {
        $created = $updated = $skipped = 0;
        foreach ($payload as $row) {
            if (! is_array($row)) { $skipped++; continue; }

            $title = trim((string) ($row['title'] ?? $row['name'] ?? $row['event'] ?? ''));
            $dateRaw = $row['time_utc'] ?? $row['datetime'] ?? $row['date_time'] ?? $row['date'] ?? null;
            if ($title === '' || ! $dateRaw) { $skipped++; continue; }

            try { $eventAt = CarbonImmutable::parse((string) $dateRaw, 'UTC')->setTimezone(config('app.timezone')); }
            catch (Throwable) { $skipped++; continue; }

            $country = trim((string) ($row['country'] ?? $row['region'] ?? '')) ?: null;
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')) ?: $this->currencyForCountry($country));
            if ($currency === '') $currency = $this->inferCurrency($title);
            $actual = $this->stringValue($row['actual'] ?? null);
            $forecast = $this->stringValue($row['consensus'] ?? $row['forecast'] ?? null);
            $previous = $this->stringValue($row['prior'] ?? $row['previous'] ?? null);
            $impact = $this->interpreter->classifyImportance($title, $this->normalizeImpact($row['impact'] ?? null));
            $interpretation = $this->interpreter->interpret($title, $actual, $forecast, $previous);
            $rawId = $row['id'] ?? $row['series'] ?? $row['slug'] ?? null;
            $providerId = 'fc:'.($rawId ?: sha1($eventAt->utc()->format('Y-m-d H:i:s').'|'.$title));

            [$created, $updated] = $this->upsertEvent($this->eventValues(
                title: $title,
                country: $country,
                currency: $currency ?: null,
                impact: $impact,
                eventAt: $eventAt,
                previous: $previous,
                forecast: $forecast,
                actual: $actual,
                source: 'Finance Calendar',
                sourceUrl: (string) ($row['url'] ?? 'https://www.financecalendar.com/'),
                providerId: $providerId,
                interpretation: $interpretation,
            ), $created, $updated);
        }

        return compact('created', 'updated', 'skipped');
    }

    private function persistXoomarRows(array $payload): array
    {
        $created = $updated = $skipped = 0;
        foreach ($payload as $row) {
            if (! is_array($row)) { $skipped++; continue; }

            $title = trim((string) ($row['eventName'] ?? $row['title'] ?? ''));
            $dateRaw = $row['scheduledAt'] ?? $row['datetime'] ?? null;
            if ($title === '' || ! $dateRaw) { $skipped++; continue; }

            try { $eventAt = CarbonImmutable::parse((string) $dateRaw, 'UTC')->setTimezone(config('app.timezone')); }
            catch (Throwable) { $skipped++; continue; }

            $actual = $this->stringValue($row['actual'] ?? null);
            $forecast = $this->stringValue($row['forecast'] ?? null);
            $previous = $this->stringValue($row['previous'] ?? null);
            $impact = $this->interpreter->classifyImportance($title, $this->normalizeImpact($row['importance'] ?? null));
            $interpretation = $this->interpreter->interpret($title, $actual, $forecast, $previous);
            $providerId = 'xoomar:'.sha1(($row['source'] ?? 'macro').'|'.$eventAt->utc()->format('Y-m-d H:i:s').'|'.Str::lower($title));

            [$created, $updated] = $this->upsertEvent($this->eventValues(
                title: $title,
                country: 'United States',
                currency: 'USD',
                impact: $impact,
                eventAt: $eventAt,
                previous: $previous,
                forecast: $forecast,
                actual: $actual,
                source: 'Official US Macro Schedule',
                sourceUrl: 'https://xoomar.com/markets/calendar',
                providerId: $providerId,
                interpretation: $interpretation,
            ), $created, $updated);
        }

        return compact('created', 'updated', 'skipped');
    }

    private function persistTradingEconomicsRows(array $payload): array
    {
        $created = $updated = $skipped = 0;
        foreach ($payload as $row) {
            if (! is_array($row)) { $skipped++; continue; }

            $title = trim((string) ($row['Event'] ?? $row['event'] ?? $row['Category'] ?? $row['category'] ?? ''));
            $dateRaw = $row['Date'] ?? $row['date'] ?? null;
            if ($title === '' || ! $dateRaw) { $skipped++; continue; }

            try { $eventAt = CarbonImmutable::parse((string) $dateRaw, 'UTC')->setTimezone(config('app.timezone')); }
            catch (Throwable) { $skipped++; continue; }

            $country = trim((string) ($row['Country'] ?? $row['country'] ?? '')) ?: null;
            $currency = strtoupper(trim((string) ($row['Currency'] ?? $row['currency'] ?? '')) ?: $this->currencyForCountry($country));
            $actual = $this->stringValue($row['Actual'] ?? $row['actual'] ?? null);
            $forecast = $this->stringValue($row['Forecast'] ?? $row['forecast'] ?? $row['TEForecast'] ?? null);
            $previous = $this->stringValue($row['Previous'] ?? $row['previous'] ?? null);
            $impact = $this->interpreter->classifyImportance($title, $this->normalizeImpact($row['Importance'] ?? $row['importance'] ?? null));
            $interpretation = $this->interpreter->interpret($title, $actual, $forecast, $previous);
            $rawId = $row['CalendarId'] ?? $row['calendarId'] ?? $row['Id'] ?? null;
            $providerId = 'te:'.($rawId ?: sha1($eventAt->utc()->format('Y-m-d H:i:s').'|'.$country.'|'.$currency.'|'.Str::lower($title)));

            [$created, $updated] = $this->upsertEvent($this->eventValues(
                title: $title,
                country: $country,
                currency: $currency ?: null,
                impact: $impact,
                eventAt: $eventAt,
                previous: $previous,
                forecast: $forecast,
                actual: $actual,
                source: 'Trading Economics',
                sourceUrl: 'https://tradingeconomics.com/calendar',
                providerId: $providerId,
                interpretation: $interpretation,
            ), $created, $updated);
        }

        return compact('created', 'updated', 'skipped');
    }

    private function eventValues(
        string $title,
        ?string $country,
        ?string $currency,
        string $impact,
        CarbonImmutable $eventAt,
        ?string $previous,
        ?string $forecast,
        ?string $actual,
        string $source,
        string $sourceUrl,
        string $providerId,
        array $interpretation,
    ): array {
        return [
            'title' => $title,
            'country' => $country,
            'currency' => $currency,
            'impact' => $impact,
            'event_at' => $eventAt,
            'previous_value' => $previous,
            'forecast_value' => $forecast,
            'actual_value' => $actual,
            'source' => $source,
            'source_url' => $sourceUrl,
            'provider_event_id' => $providerId,
            'crypto_impact' => $interpretation['crypto_impact'],
            'easy_explanation' => $interpretation['easy_explanation'],
            'crypto_impact_summary' => $interpretation['crypto_impact_summary'],
            'is_crypto_relevant' => (bool) $interpretation['is_crypto_relevant'] || in_array($impact, ['high', 'medium'], true),
            'synced_at' => now(),
        ];
    }

    private function upsertEvent(array $values, int $created, int $updated): array
    {
        $existing = EconomicEvent::query()->where('provider_event_id', $values['provider_event_id'])->first();

        if (! $existing) {
            $eventAt = CarbonImmutable::parse($values['event_at']);
            $existing = EconomicEvent::query()
                ->where('title', $values['title'])
                ->whereBetween('event_at', [$eventAt->subMinutes(5), $eventAt->addMinutes(5)])
                ->first();
        }

        if (! $existing) {
            EconomicEvent::create($values);
            $created++;
        } else {
            $existing->update($values);
            $updated++;
        }

        return [$created, $updated];
    }

    private function recordSyncStatus(string $status, ?string $detail = null): void
    {
        if ($status === 'success') {
            SiteSetting::updateOrCreate(['key' => 'economic_calendar_last_sync_at'], [
                'value' => now()->toIso8601String(), 'type' => 'string', 'group' => 'integrations',
            ]);
        }
        SiteSetting::updateOrCreate(['key' => 'economic_calendar_last_sync_status'], [
            'value' => $status, 'type' => 'string', 'group' => 'integrations',
        ]);
        if ($detail !== null) {
            SiteSetting::updateOrCreate(['key' => 'economic_calendar_last_sync_detail'], [
                'value' => Str::limit($detail, 500, '…'), 'type' => 'string', 'group' => 'integrations',
            ]);
        }
    }

    private function financeCalendarEnabled(): bool
    {
        return (bool) config('services.finance_calendar.enabled', true);
    }

    private function xoomarEnabled(): bool
    {
        return (bool) config('services.xoomar_calendar.enabled', true);
    }

    private function tradingEconomicsEnabled(): bool
    {
        return (bool) config('services.trading_economics.fallback_enabled', false)
            && trim((string) config('services.trading_economics.api_key', '')) !== '';
    }

    private function normalizeImpact(mixed $value): ?string
    {
        $value = Str::lower(trim((string) $value));
        return match ($value) {
            'med', 'moderate', '2', 'orange' => 'medium',
            'hi', '3', 'red' => 'high',
            'lo', '1', 'yellow' => 'low',
            default => $value !== '' ? $value : null,
        };
    }

    private function inferCurrency(string $title): string
    {
        $name = Str::lower($title);
        if (Str::contains($name, ['fomc', 'federal reserve', 'us cpi', 'u.s. cpi', 'nonfarm', 'payroll', 'pce', 'united states'])) return 'USD';
        if (Str::contains($name, ['ecb', 'euro area', 'eurozone'])) return 'EUR';
        if (Str::contains($name, ['bank of england', 'boe', 'uk cpi', 'united kingdom'])) return 'GBP';
        if (Str::contains($name, ['bank of japan', 'boj', 'japan cpi'])) return 'JPY';
        return '';
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
        return $value === '' ? null : mb_substr($value, 0, 140);
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
