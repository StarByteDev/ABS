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

        // Import available sources in priority order. A successful calendar feed may
        // still lack consensus/actual values; later sources may fill those gaps.
        $providers = [
            ['Financial Modeling Prep', $this->primaryConfigured(), fn () => $this->fetchFmpRows($from, $to), 'persistFmpRows'],
            ['Finance Calendar', $this->financeCalendarEnabled(), fn () => $this->fetchFinanceCalendarRows($from, $to), 'persistFinanceCalendarRows'],
            ['Xoomar Macro Calendar', $this->xoomarEnabled(), fn () => $this->fetchXoomarRows($from, $to), 'persistXoomarRows'],
            ['Trading Economics', $this->tradingEconomicsEnabled(), fn () => $this->fetchTradingEconomicsRows($from, $to), 'persistTradingEconomicsRows'],
        ];
        $attempts = [];
        $used = [];
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($providers as [$name, $enabled, $fetch, $persist]) {
            if (! $enabled) continue;
            try {
                $rows = $fetch();
                if (! $rows) throw new RuntimeException('Provider returned an empty calendar.');
                $stats = $this->{$persist}($rows);
                if (($stats['created'] + $stats['updated']) < 1) {
                    throw new RuntimeException('Provider returned no usable events.');
                }
                $used[] = $name;
                foreach ($result as $key => $_) $result[$key] += (int) ($stats[$key] ?? 0);
            } catch (Throwable $e) {
                $attempts[] = $name.': '.$e->getMessage();
                Log::warning('ABS Economic Calendar provider unavailable', ['provider' => $name, 'message' => $e->getMessage()]);
            }
        }

        if (! $used) {
            $message = $attempts
                ? 'Economic Calendar refresh failed. '.implode(' | ', $attempts)
                : 'Economic Calendar has no enabled source.';
            $this->recordSyncStatus('failed', $message);
            throw new RuntimeException($message);
        }

        $this->recordSyncStatus('success', implode(' + ', $used));

        return $result + [
            'provider' => implode(' + ', $used),
            'total' => $result['created'] + $result['updated'],
            'from' => $from,
            'to' => $to,
            'warnings' => $attempts,
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

        $countries = (string) config('services.trading_economics.countries', 'united states,canada,united kingdom,euro area,japan,china');
        $countrySegment = implode(',', array_map(static fn ($country) => rawurlencode(trim($country)), explode(',', $countries)));
        $rangeUrl = $base.'/country/'.$countrySegment.'/'.$from->toDateString().'/'.$to->toDateString();
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
            $actual = $this->withSourceUnit($row['actual'] ?? $row['Actual'] ?? $row['result'] ?? null, $row['unit'] ?? null);
            $forecast = $this->withSourceUnit($row['estimate'] ?? $row['forecast'] ?? $row['consensus'] ?? null, $row['unit'] ?? null);
            $previous = $this->withSourceUnit($row['previous'] ?? null, $row['unit'] ?? null);
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
            $actual = $this->stringValue($row['actual'] ?? $row['Actual'] ?? $row['result'] ?? null);
            $forecast = $this->stringValue($row['consensus'] ?? $row['forecast'] ?? $row['estimate'] ?? null);
            $previous = $this->stringValue($row['prior'] ?? $row['previous'] ?? $row['previousValue'] ?? null);
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

            $actual = $this->stringValue($row['actual'] ?? $row['Actual'] ?? $row['result'] ?? null);
            $forecast = $this->stringValue($row['forecast'] ?? $row['consensus'] ?? null);
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
            $actual = $this->stringValue($row['Actual'] ?? $row['actual'] ?? $row['Actual'] ?? $row['result'] ?? null);
            $forecast = $this->stringValue($row['Forecast'] ?? $row['forecast'] ?? null);
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
            // Join the same release from different providers only when the name,
            // country/currency (when known), and release time are compatible.
            $eventAt = CarbonImmutable::parse($values['event_at']);
            $candidates = EconomicEvent::query()
                ->whereBetween('event_at', [$eventAt->subMinutes(20), $eventAt->addMinutes(20)])
                ->get();
            $normal = $this->eventIdentity($values['title']);
            $existing = $candidates->first(function (EconomicEvent $candidate) use ($values, $normal): bool {
                if ($this->eventIdentity((string) $candidate->title) !== $normal) return false;
                foreach (['country', 'currency'] as $key) {
                    if (! empty($candidate->{$key}) && ! empty($values[$key])) {
                        if ($key === 'country' && $this->normalizeCountry((string) $candidate->{$key})
                            === $this->normalizeCountry((string) $values[$key])) continue;
                        if (strcasecmp(trim((string) $candidate->{$key}), trim((string) $values[$key])) !== 0) return false;
                    }
                }
                return true;
            });
        }

        if (! $existing) {
            EconomicEvent::create($values);
            return [$created + 1, $updated];
        }

        // A partial provider response must NEVER erase a published reading.
        // Respect Admin-managed (manual) events and original provider identity.
        $manual = empty($existing->provider_event_id);
        $sameSource = (string) $existing->provider_event_id === (string) $values['provider_event_id'];
        foreach (['previous_value', 'forecast_value', 'actual_value'] as $field) {
            if ($values[$field] === null || (($manual || ! $sameSource) && filled($existing->{$field}))) {
                $values[$field] = $existing->{$field};
            }
        }
        $analysis = $this->interpreter->interpret(
            (string) $existing->title,
            $values['actual_value'], $values['forecast_value'], $values['previous_value']
        );
        $values['crypto_impact'] = $analysis['crypto_impact'];
        $values['easy_explanation'] = $manual && filled($existing->easy_explanation)
            ? $existing->easy_explanation : $analysis['easy_explanation'];
        $values['crypto_impact_summary'] = $manual && filled($existing->crypto_impact_summary)
            ? $existing->crypto_impact_summary : $analysis['crypto_impact_summary'];
        $values['is_crypto_relevant'] = (bool) $analysis['is_crypto_relevant'] || in_array($existing->impact, ['high', 'medium'], true);

        // The first source retains ownership/provenance, avoiding provider-ID
        // oscillation and duplicate events on the following sync.
        foreach (['provider_event_id', 'source', 'source_url', 'title', 'country', 'currency', 'event_at', 'impact'] as $field) {
            if ($field === 'event_at' && (string) $existing->provider_event_id === (string) $values['provider_event_id']) continue;
            $values[$field] = $existing->{$field} ?: $values[$field];
        }
        $existing->update($values);
        return [$created, $updated + 1];
    }

    private function normalizeCountry(string $country): string
    {
        return match (mb_strtolower(trim($country))) {
            'us', 'usa', 'u.s.', 'united states of america', 'united states' => 'united states',
            'uk', 'gb', 'gbp', 'great britain', 'united kingdom' => 'united kingdom',
            'eu', 'euro area', 'eurozone' => 'euro area',
            'ca', 'can' , 'canada' => 'canada',
            default => mb_strtolower(trim($country)),
        };
    }

    private function eventIdentity(string $title): string
    {
        // Only remove superficial differences. Do not collapse different
        // countries, measures, or seasonal-adjustment variants.
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\pL\pN%]+/u', ' ', mb_strtolower($title))));
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

    private function withSourceUnit(mixed $raw, mixed $unit): ?string
    {
        $value = $this->stringValue($raw);
        if ($value === null) return null;
        if (! is_numeric($raw) || ! is_scalar($unit)) return $value;
        $unit = trim((string) $unit);
        if ($unit === '%') return $value.'%';
        if (in_array($unit, ['K', 'M', 'B', 'T'], true)) return $value.$unit;
        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        // Providers return both scalar readings and structured {value,unit}
        // payloads. Never interpret missing, pending or placeholders as results.
        if (is_array($value)) {
            foreach (['value', 'Value', 'raw', 'display', 'formatted', 'text', 'amount'] as $key) {
                if (array_key_exists($key, $value)) {
                    $reading = $this->stringValue($value[$key]);
                    if ($reading === null) return null;
                    $unit = trim((string) ($value['unit'] ?? $value['Unit'] ?? ''));
                    return mb_substr($reading.($unit === '%' ? '%' : ($unit !== '' ? ' '.$unit : '')), 0, 140);
                }
            }
            return null;
        }
        if ($value === null || is_bool($value)) return null;
        if (! is_scalar($value)) return null;
        $value = trim((string) $value);
        if ($value === '' || in_array(mb_strtolower($value), ['n/a', 'na', '-', '—', 'pending', 'tba', 'tbd', 'null', 'none', 'not available'], true)) return null;
        return mb_substr($value, 0, 140);
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
