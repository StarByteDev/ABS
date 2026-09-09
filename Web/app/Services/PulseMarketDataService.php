<?php

namespace App\Services;

use App\Models\PulseMarketCandle;
use App\Models\PulseMarketDataRun;
use App\Models\PulseMarketPrice;
use App\Models\PulsePair;
use App\Models\PulsePlan;
use App\Models\PulseSignal;
use App\Models\PulseSystemSetting;
use App\Models\UserServiceAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PulseMarketDataService
{
    private const REQUIRED_TABLES = ['pulse_market_prices', 'pulse_market_candles', 'pulse_market_data_runs'];

    public function __construct(private readonly BinanceFuturesService $binance) {}

    public function schemaReady(): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if (! Schema::hasTable($table)) return false;
        }
        return true;
    }

    public function missingTables(): array
    {
        return array_values(array_filter(self::REQUIRED_TABLES, fn (string $table): bool => ! Schema::hasTable($table)));
    }

    /**
     * Central scheduled market-data ingestion. This is deliberately user-agnostic:
     * one Binance fetch populates shared prices/candles for web, scanner, validation and mobile APIs.
     */
    public function syncCentral(): PulseMarketDataRun
    {
        if (! $this->schemaReady()) {
            throw new RuntimeException('Pulse central market-data schema is not ready. Run php artisan abs:repair --seed, then retry. Missing: '.implode(', ', $this->missingTables()));
        }

        $lock = Cache::lock('abs:pulse:central-market-data', max(50, (int) config('pulse.market_data.lock_seconds', 55)));
        if (! $lock->get()) {
            throw new RuntimeException('Central market-data synchronization is already running.');
        }

        $run = PulseMarketDataRun::create(['status' => 'running', 'started_at' => now()]);
        try {
            $enabledSymbols = PulsePair::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('symbol')
                ->pluck('symbol')->map(fn ($s) => strtoupper((string) $s))->filter()->unique()->values();

            $pricesUpdated = $this->syncPrices($enabledSymbols);
            $scannerUniverse = $this->scannerUniverse($enabledSymbols);
            $candleSymbolsUpdated = 0;
            $scannerRefresh = [];
            foreach ((array) config('pulse.market_data.scanner_timeframes', ['15m', '4h']) as $timeframe) {
                $timeframe = (string) $timeframe;
                $dueSymbols = $this->scannerSymbolsDue($scannerUniverse, $timeframe);
                $scannerRefresh[$timeframe] = $dueSymbols->count();
                $candleSymbolsUpdated += $this->syncCandleBatch($dueSymbols, $timeframe, (int) config('pulse.market_data.scanner_candle_limit', 180));
            }

            $validationSymbols = PulseSignal::query()
                ->whereIn('status', ['active', 'expired', 'executed'])
                ->whereIn('direction', ['LONG', 'SHORT'])
                ->where('generated_at', '>=', now()->subHours(4))
                ->pluck('symbol')->map(fn ($s) => strtoupper((string) $s))->filter()->unique()->values();
            $validationUpdated = $this->syncCandleBatch($validationSymbols, '1m', (int) config('pulse.market_data.validation_1m_limit', 180));

            $run->update([
                'status' => 'completed',
                'prices_updated' => $pricesUpdated,
                'candle_symbols_updated' => $candleSymbolsUpdated,
                'validation_symbols_updated' => $validationUpdated,
                'summary' => [
                    'enabled_symbols' => $enabledSymbols->count(),
                    'scanner_universe_symbols' => $scannerUniverse->count(),
                    'scheduler_profile' => (string) config('pulse.scheduler.profile', 'standard'),
                    'scanner_symbols_this_cycle' => $scannerRefresh,
                    'scanner_timeframes' => array_values((array) config('pulse.market_data.scanner_timeframes', ['15m', '4h'])),
                    'validation_symbols' => $validationSymbols->count(),
                    'architecture' => 'central-scheduled-v1',
                ],
                'completed_at' => now(),
            ]);

            $this->pruneCandles();
            return $run->fresh();
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function latestPrice(string $symbol, ?int $maxAgeSeconds = null): ?PulseMarketPrice
    {
        if (! Schema::hasTable('pulse_market_prices')) return null;
        $maxAgeSeconds ??= (int) config('pulse.market_data.read_max_age_seconds', 300);
        return PulseMarketPrice::query()
            ->where('symbol', strtoupper($symbol))
            ->where('observed_at', '>=', now()->subSeconds(max(1, $maxAgeSeconds)))
            ->first();
    }

    public function latestPrices(array|Collection $symbols, ?int $maxAgeSeconds = null): Collection
    {
        if (! Schema::hasTable('pulse_market_prices')) return collect();
        $maxAgeSeconds ??= (int) config('pulse.market_data.read_max_age_seconds', 300);
        $symbols = collect($symbols)->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->unique()->values();
        if ($symbols->isEmpty()) return collect();

        return PulseMarketPrice::query()->whereIn('symbol', $symbols->all())
            ->where('observed_at', '>=', now()->subSeconds(max(1, $maxAgeSeconds)))
            ->get()->keyBy('symbol');
    }

    /** Return cached Binance-shaped rows for the existing indicator engine. */
    public function scannerRows(string $symbol, string $timeframe, int $limit = 120): array
    {
        if (! Schema::hasTable('pulse_market_candles')) return [];
        $rows = PulseMarketCandle::query()->where('symbol', strtoupper($symbol))->where('timeframe', strtolower($timeframe))->where('is_closed', true)
            ->orderByDesc('open_time_ms')->limit(max(20, min($limit, 500)))->get()->sortBy('open_time_ms')->values();

        return $rows->map(fn (PulseMarketCandle $c) => [
            (int) $c->open_time_ms, (string) $c->open, (string) $c->high, (string) $c->low,
            (string) $c->close, (string) $c->volume, (int) ($c->close_time_ms ?? $c->open_time_ms),
        ])->all();
    }

    public function minuteCandles(string $symbol, \DateTimeInterface $from, ?\DateTimeInterface $to = null): Collection
    {
        if (! Schema::hasTable('pulse_market_candles')) return collect();
        $fromMs = ((int) $from->format('U')) * 1000;
        $toMs = $to ? ((int) $to->format('U')) * 1000 + 59999 : PHP_INT_MAX;
        return PulseMarketCandle::query()->where('symbol', strtoupper($symbol))->where('timeframe', '1m')->where('is_closed', true)
            ->whereBetween('open_time_ms', [$fromMs, $toMs])->orderBy('open_time_ms')->get();
    }

    public function health(): array
    {
        $missing = $this->missingTables();
        if ($missing !== []) {
            return [
                'architecture' => 'central-scheduled-v2',
                'schema_ready' => false,
                'feed_status' => 'offline',
                'price_age_seconds' => null,
                'stale_for_scanning' => true,
                'missing_tables' => $missing,
                'last_run_status' => 'schema_not_ready',
                'last_run_completed_at' => null,
                'latest_price_observed_at' => null,
                'latest_price_symbols' => 0,
                'target_price_refresh_seconds' => (int) config('pulse.market_data.target_price_refresh_seconds', 60),
                'read_max_age_seconds' => (int) config('pulse.market_data.read_max_age_seconds', 300),
                'scheduler_profile' => (string) config('pulse.scheduler.profile', 'standard'),
                'scheduler_cron_minutes' => (int) config('pulse.scheduler.cron_minutes', 1),
                'scanner_timeframes' => array_values((array) config('pulse.market_data.scanner_timeframes', ['15m', '4h'])),
                'recovery' => 'Run php artisan abs:repair --seed, then php artisan abs:pulse-market-data.',
            ];
        }

        $lastRun = PulseMarketDataRun::query()->latest('id')->first();
        $latestObserved = PulseMarketPrice::query()->max('observed_at');
        $priceCount = PulseMarketPrice::query()->count();
        $target = (int) config('pulse.market_data.target_price_refresh_seconds', 60);
        $ageSeconds = $latestObserved ? max(0, now()->diffInSeconds(\Illuminate\Support\Carbon::parse($latestObserved))) : null;
        $feedStatus = $ageSeconds === null ? 'offline' : ($ageSeconds <= max(120, $target * 2) ? 'healthy' : ($ageSeconds <= max(300, $target * 5) ? 'delayed' : 'offline'));
        return [
            'architecture' => 'central-scheduled-v2',
            'schema_ready' => true,
            'feed_status' => $feedStatus,
            'price_age_seconds' => $ageSeconds,
            'stale_for_scanning' => $feedStatus === 'offline',
            'last_run_status' => $lastRun?->status,
            'last_run_completed_at' => $lastRun?->completed_at,
            'latest_price_observed_at' => $latestObserved,
            'latest_price_symbols' => $priceCount,
            'target_price_refresh_seconds' => $target,
            'read_max_age_seconds' => (int) config('pulse.market_data.read_max_age_seconds', 300),
            'scheduler_profile' => (string) config('pulse.scheduler.profile', 'standard'),
            'scheduler_cron_minutes' => (int) config('pulse.scheduler.cron_minutes', 1),
            'scanner_timeframes' => array_values((array) config('pulse.market_data.scanner_timeframes', ['15m', '4h'])),
        ];
    }

    private function syncPrices(Collection $enabledSymbols): int
    {
        if ($enabledSymbols->isEmpty()) return 0;
        $wanted = array_flip($enabledSymbols->all());
        $observedAt = now();
        $rows = [];
        foreach ($this->binance->publicTickers('live') as $ticker) {
            if (! is_array($ticker)) continue;
            $symbol = strtoupper((string) ($ticker['symbol'] ?? ''));
            if ($symbol === '' || ! isset($wanted[$symbol]) || ! is_numeric($ticker['lastPrice'] ?? null)) continue;
            $rows[] = [
                'symbol' => $symbol,
                'price' => (float) $ticker['lastPrice'],
                'change_percent_24h' => is_numeric($ticker['priceChangePercent'] ?? null) ? (float) $ticker['priceChangePercent'] : null,
                'high_24h' => is_numeric($ticker['highPrice'] ?? null) ? (float) $ticker['highPrice'] : null,
                'low_24h' => is_numeric($ticker['lowPrice'] ?? null) ? (float) $ticker['lowPrice'] : null,
                'volume_24h' => is_numeric($ticker['quoteVolume'] ?? null) ? (float) $ticker['quoteVolume'] : (is_numeric($ticker['volume'] ?? null) ? (float) $ticker['volume'] : null),
                'source' => 'binance_futures', 'observed_at' => $observedAt, 'created_at' => $observedAt, 'updated_at' => $observedAt,
            ];
        }
        if ($rows !== []) {
            DB::table('pulse_market_prices')->upsert($rows, ['symbol'], ['price','change_percent_24h','high_24h','low_24h','volume_24h','source','observed_at','updated_at']);
        }
        return count($rows);
    }

    /**
     * Keep candle ingestion central while avoiding per-user Binance requests.
     * V15.0.3 warms the union of Admin/package-defined market universes because
     * members no longer choose their own pairs. Recent signal markets and the
     * three core markets stay warm for validation and first-run resilience.
     */
    private function scannerUniverse(Collection $enabledSymbols): Collection
    {
        $enabled = $enabledSymbols->map(fn ($s) => strtoupper((string) $s))->filter()->unique()->values();
        if ($enabled->isEmpty()) return collect();

        $wanted = collect(['BTCUSDT','ETHUSDT','SOLUSDT']);
        if (Schema::hasTable('pulse_plans')) {
            $planIds = PulsePlan::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->pluck('id');

            if (Schema::hasTable('user_service_access')) {
                $assigned = UserServiceAccess::query()
                    ->where('service', 'pulse')
                    ->where('status', 'active')
                    ->whereNotNull('pulse_plan_id')
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->pluck('pulse_plan_id');
                $planIds = $planIds->merge($assigned);
            }

            $plans = PulsePlan::query()->with('pairs')->whereIn('id', $planIds->filter()->unique()->values())->get();
            foreach ($plans as $plan) {
                $limit = max(1, (int) ($plan->max_selected_pairs ?: 1));
                if (($plan->pair_access_mode ?: 'all') === 'all') {
                    $wanted = $wanted->merge($enabled->take($limit));
                    continue;
                }

                $wanted = $wanted->merge(
                    $plan->pairs
                        ->filter(fn (PulsePair $pair) => (bool) ($pair->pivot?->is_enabled ?? true))
                        ->pluck('symbol')
                        ->take($limit)
                );
            }
        }

        if (Schema::hasTable('pulse_signals') && Schema::hasColumn('pulse_signals', 'symbol') && Schema::hasColumn('pulse_signals', 'generated_at')) {
            $wanted = $wanted->merge(PulseSignal::query()->where('generated_at', '>=', now()->subDays(2))->pluck('symbol'));
        }

        $wanted = $wanted->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->unique()->values();
        $allowed = array_flip($enabled->all());
        $wanted = $wanted->filter(fn ($symbol) => isset($allowed[$symbol]))->values();

        return $wanted->isNotEmpty() ? $wanted : $enabled;
    }

    private function scannerSymbolsDue(Collection $symbols, string $timeframe): Collection
    {
        if ($symbols->isEmpty()) return collect();
        $seconds = match (strtolower($timeframe)) { '15m' => 900, '4h' => 14400, '1h' => 3600, default => 900 };
        $currentOpenMs = intdiv(now()->getTimestamp(), $seconds) * $seconds * 1000;
        $latest = PulseMarketCandle::query()->select('symbol', DB::raw('MAX(open_time_ms) as latest_open'))
            ->where('timeframe', strtolower($timeframe))->whereIn('symbol', $symbols->all())->groupBy('symbol')->pluck('latest_open','symbol');
        $due = $symbols->filter(fn ($symbol) => (int) ($latest[strtoupper((string) $symbol)] ?? 0) < $currentOpenMs)->values();
        $perRun = max(1, (int) config('pulse.market_data.scanner_symbols_per_cycle', 100));
        return $due->take($perRun)->values();
    }

    private function syncCandleBatch(Collection $symbols, string $timeframe, int $limit): int
    {
        $symbols = $symbols->map(fn ($s) => strtoupper((string) $s))->filter()->unique()->values();
        if ($symbols->isEmpty()) return 0;
        $updated = 0;
        $batchSize = max(5, min(50, (int) config('pulse.market_data.http_batch_size', 25)));
        foreach ($symbols->chunk($batchSize) as $batch) {
            $payloads = $this->binance->publicKlinesBatch($batch->all(), $timeframe, $limit, 'live');
            foreach ($batch as $symbol) {
                $data = $payloads[$symbol]['data'] ?? null;
                if (! is_array($data)) continue;
                $this->storeCandles($symbol, $timeframe, $data);
                $updated++;
            }
        }
        return $updated;
    }

    private function storeCandles(string $symbol, string $timeframe, array $data): void
    {
        $now = now(); $rows = [];
        foreach ($data as $row) {
            if (! is_array($row) || count($row) < 6) continue;
            $rows[] = [
                'symbol' => strtoupper($symbol), 'timeframe' => strtolower($timeframe), 'open_time_ms' => (int) $row[0],
                'close_time_ms' => isset($row[6]) ? (int) $row[6] : null, 'open' => (float) $row[1], 'high' => (float) $row[2],
                'low' => (float) $row[3], 'close' => (float) $row[4], 'volume' => (float) $row[5], 'is_closed' => isset($row[6]) ? ((int) $row[6] <= (int) floor(microtime(true) * 1000)) : true,
                'source' => 'binance_futures', 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('pulse_market_candles')->upsert($chunk, ['symbol','timeframe','open_time_ms'], ['close_time_ms','open','high','low','close','volume','is_closed','source','updated_at']);
        }
    }

    private function pruneCandles(): void
    {
        $retention = (array) config('pulse.market_data.retention_days', ['1m' => 2, '15m' => 21, '4h' => 180]);
        foreach ($retention as $timeframe => $days) {
            $cutoffMs = now()->subDays(max(1, (int) $days))->getTimestamp() * 1000;
            PulseMarketCandle::query()->where('timeframe', (string) $timeframe)->where('open_time_ms', '<', $cutoffMs)->delete();
        }
    }
}
