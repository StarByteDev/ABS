<?php

namespace App\Services;

use App\Models\PulseAlert;
use App\Models\PulsePair;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseStrategy;
use App\Models\PulseSystemSetting;
use App\Models\PulseUserSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PulseScannerService
{
    public function __construct(
        private readonly BinanceFuturesService $binance,
        private readonly PulseMarketDataService $market,
        private readonly PulseLearningService $learning,
        private readonly PulseSignalValidationService $validations,
        private readonly PulseAuditService $audit,
        private readonly PulseAccessService $access,
        private readonly PulsePairAccessService $pairAccess,
        private readonly PulseSignalThresholdService $thresholds,
        private readonly BrandedMailService $mail,
    ) {}

    public function run(?User $user = null, ?array $symbols = null, ?string $timeframe = null): PulseScannerRun
    {
        if (! $user) {
            return $this->executeRun($user, $symbols, $timeframe);
        }

        $ttlSeconds = max(360, ((int) config('pulse.scanner.stale_run_minutes', 15) * 60) + 120);
        $lock = Cache::lock('pulse:best-signal:user:'.$user->id, $ttlSeconds);
        if (! $lock->get()) {
            throw new RuntimeException('A Find Best Signal request is already running for this account. Please wait for it to finish.');
        }

        try {
            return $this->executeRun($user, $symbols, $timeframe);
        } finally {
            try { $lock->release(); } catch (\Throwable) {}
        }
    }

    private function executeRun(?User $user = null, ?array $symbols = null, ?string $timeframe = null): PulseScannerRun
    {
        if ($user) {
            $this->access->assertActive($user);
            if (! $this->access->systemEnabled('scanner_enabled', true) || ! $this->access->allows($user, 'scanner', true)) {
                throw new RuntimeException('Pulse scanner access is disabled for this account or installation.');
            }
        }

        // ABS V15: the user-facing scanner is one-click. Users do not select
        // pair, strategy, timeframe, threshold or technical filters. The
        // existing engine still evaluates its unchanged 15m/4h logic internally.
        if ($user) $timeframe = 'all';
        $timeframe ??= (string) config('pulse.scanner.timeframe', '15m');
        $timeframe = strtolower(trim($timeframe));
        // Protect upgrades from legacy .env values such as 1h: V14.8.17 scanner architecture is 15m/4h only.
        if (! in_array($timeframe, ['15m', '4h', 'all'], true)) $timeframe = '15m';
        $scanTimeframes = $timeframe === 'all' ? ['15m', '4h'] : [$timeframe];
        $runTimeframe = $timeframe === 'all' ? '15m+4h' : $timeframe;
        $settings = $user ? PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaultUserSettings($user)) : null;
        $plan = $user?->pulsePlan();
        $staleMinutes = max(5, (int) config('pulse.scanner.stale_run_minutes', 15));
        $safetyMax = max(1000, (int) config('pulse.scanner.max_pairs_per_run', 1000), (int) ($plan?->max_selected_pairs ?? 0));

        // Release interrupted/stale scanner runs so they cannot block a future Best Signal request.
        PulseScannerRun::query()
            ->where('status', 'running')
            ->where('created_at', '<', now()->subMinutes($staleMinutes))
            ->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'Scanner run was released after exceeding the stale-run timeout.',
            ]);

        if ($user) {
            $alreadyRunning = PulseScannerRun::query()
                ->where('user_id', $user->id)
                ->where('status', 'running')
                ->where('created_at', '>=', now()->subMinutes($staleMinutes))
                ->exists();
            if ($alreadyRunning) {
                throw new RuntimeException('A Find Best Signal request is already running for this account. Please wait for it to finish.');
            }
        }

        PulseSignal::query()
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        // Targeted symbols remain available only to internal/system scans. A
        // signed-in user always scans the complete Admin/package-defined universe.
        $explicitSymbols = ! $user && is_array($symbols) && $symbols !== [];
        $requestedSymbols = $explicitSymbols
            ? array_values(array_unique(array_filter(array_map(fn ($symbol) => strtoupper(trim((string) $symbol)), $symbols))))
            : [];

        if (count($requestedSymbols) > $safetyMax) {
            throw new RuntimeException("A targeted scan can contain up to {$safetyMax} markets per request.");
        }

        if ($user) {
            $allowed = $this->pairAccess->allowedPairs($user)->values();
            $allowedSymbols = $allowed->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol))->all();

            // ABS V15: Admin/package market access is authoritative. No user
            // market selection is consulted by the scanner.
            $pairs = $allowed->values();
        } else {
            $query = PulsePair::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('symbol');
            if ($explicitSymbols) $query->whereIn('symbol', $requestedSymbols);
            $pairs = $query->get();
        }

        if ($pairs->count() > $safetyMax) {
            throw new RuntimeException("The selected market set contains {$pairs->count()} markets, above the scanner safety ceiling of {$safetyMax}. Reduce the selected markets or raise the server ceiling.");
        }
        if ($pairs->isEmpty()) {
            throw new RuntimeException('No Admin/package-approved Pulse markets are available for scanning.');
        }

        $strategies = $this->strategiesFor($user);
        if ($strategies->isEmpty()) {
            throw new RuntimeException('No enabled Pulse strategies are available for the current plan.');
        }

        $marketHealth = $this->market->health();
        if (($marketHealth['stale_for_scanning'] ?? true) === true) {
            throw new RuntimeException('ABS central market feed is stale or offline. The scanner is temporarily protected from creating decisions using old prices. Wait for the next one-minute market sync or contact support.');
        }

        $run = PulseScannerRun::create([
            'user_id' => $user?->id,
            'status' => 'running',
            'timeframe' => $runTimeframe,
            'started_at' => now(),
        ]);

        $results = [];
        $created = 0;
        $available = 0;
        $qualifiedCandidates = [];
        $bestSignal = null;
        $batchSize = max(1, min(100, (int) config('pulse.scanner.batch_concurrency', 25)));
        $candleLimit = (int) config('pulse.scanner.candle_limit', 120);
        $threshold = $this->thresholds->score($user, $settings);

        // V14.8.17: scanner reads the shared central market snapshot. It does not
        // call Binance per user. The minute scheduler owns upstream market I/O.
        $tickerMap = $this->market->latestPrices($pairs->pluck('symbol')->all(), (int) config('pulse.market_data.read_max_age_seconds', 300));

        $activeSignals = collect();
        if ($user) {
            $activeSignals = PulseSignal::query()
                ->where('user_id', $user->id)
                ->whereIn('symbol', $pairs->pluck('symbol')->all())
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('generated_at')
                ->get()
                ->keyBy(fn (PulseSignal $signal) => strtoupper($signal->symbol).'|'.strtoupper($signal->direction).'|'.strtolower((string) $signal->timeframe));
        }

        try {
            foreach ($scanTimeframes as $scanTimeframe) {
                foreach ($pairs->chunk($batchSize) as $batch) {
                    $symbolsInBatch = $batch->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol))->values()->all();
                    $payloads = collect($symbolsInBatch)->mapWithKeys(function (string $symbol) use ($scanTimeframe, $candleLimit): array {
                        $rows = $this->market->scannerRows($symbol, $scanTimeframe, $candleLimit);
                        return [$symbol => count($rows) >= 60
                            ? ['data' => $rows, 'error' => null]
                            : ['data' => null, 'error' => 'Central candle buffer is still warming for this market/timeframe.']];
                    })->all();

                    foreach ($batch as $pair) {
                        $symbol = strtoupper((string) $pair->symbol);
                        $payload = $payloads[$symbol] ?? ['data' => null, 'error' => 'Central candle buffer is unavailable.'];
                        if (! is_array($payload['data'] ?? null)) {
                            $results[] = [
                                'symbol' => $symbol,
                                'timeframe' => $scanTimeframe,
                                'direction' => 'UNAVAILABLE',
                                'score' => 0,
                                'error' => (string) ($payload['error'] ?? 'Live candle data is unavailable.'),
                            ];
                            continue;
                        }

                        try {
                            $analysis = $this->analyze($pair, $scanTimeframe, $strategies, $payload['data']);
                            $analysis['timeframe'] = $scanTimeframe;
                            $ticker = $tickerMap->get($symbol);
                            $analysis['last_price'] = $ticker && is_numeric($ticker->price ?? null)
                                ? (float) $ticker->price
                                : (float) $analysis['entry_price'];
                            $analysis['change_percent'] = $ticker && is_numeric($ticker->change_percent_24h ?? null)
                                ? (float) $ticker->change_percent_24h
                                : null;
                            // User-facing scanner-run summaries must never expose
                            // unpaid technical entries/SL/TP or strategy evidence. Internal
                            // system scans retain the legacy full analysis payload.
                            $results[] = $user
                                ? ['symbol' => $symbol, 'timeframe' => $scanTimeframe, 'status' => 'evaluated']
                                : $analysis;
                            $available++;

                            if ($analysis['direction'] === 'NEUTRAL' || $analysis['score'] < $threshold) {
                                continue;
                            }

                            $key = $symbol.'|'.strtoupper((string) $analysis['direction']).'|'.$scanTimeframe;
                            $existingSignal = $activeSignals->get($key);

                            $generatedAt = now();
                            $strategySnapshot = array_values((array) ($analysis['strategies'] ?? []));
                            $strategyVersion = $this->strategyBundleVersion($strategySnapshot);
                            $marketRegime = $this->marketRegime($analysis);
                            $learning = $this->learning->reliabilityFor($strategySnapshot, $scanTimeframe, (string) $analysis['direction'], $marketRegime);
                            $reliability = (float) ($learning['score'] ?? 50);
                            $confidence = round(((float) $analysis['score'] * 0.75) + ($reliability * 0.25), 2);
                            $tpLevels = $this->takeProfitLevels($pair, $analysis);
                            $analysis['market_regime'] = $marketRegime;
                            $analysis['learning_evidence'] = $learning;

                            // Keep every technical evaluation internal, then rank the
                            // qualified candidates. Only the single strongest result is
                            // converted into a user-facing Best Signal after all markets
                            // and both timeframes have been evaluated.
                            $qualifiedCandidates[] = [
                                'pair' => $pair,
                                'symbol' => $symbol,
                                'timeframe' => $scanTimeframe,
                                'analysis' => $analysis,
                                'strategy_snapshot' => $strategySnapshot,
                                'strategy_version' => $strategyVersion,
                                'market_regime' => $marketRegime,
                                'learning' => $learning,
                                'reliability' => $reliability,
                                'confidence' => $confidence,
                                'tp_levels' => $tpLevels,
                                'existing_signal' => $existingSignal instanceof PulseSignal ? $existingSignal : null,
                                'generated_at' => $generatedAt,
                            ];
                        } catch (\Throwable $e) {
                            $results[] = [
                                'symbol' => $symbol,
                                'timeframe' => $scanTimeframe,
                                'direction' => 'UNAVAILABLE',
                                'score' => 0,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                }
            }

            if ($available === 0) {
                throw new RuntimeException("Central candle buffers could not be evaluated for any of the {$pairs->count()} Admin/package markets. No signal was created.");
            }

            if ($qualifiedCandidates !== []) {
                usort($qualifiedCandidates, static function (array $a, array $b): int {
                    $byConfidence = ((float) $b['confidence']) <=> ((float) $a['confidence']);
                    if ($byConfidence !== 0) return $byConfidence;
                    $byTechnical = ((float) data_get($b, 'analysis.score', 0)) <=> ((float) data_get($a, 'analysis.score', 0));
                    if ($byTechnical !== 0) return $byTechnical;
                    return ((float) $b['reliability']) <=> ((float) $a['reliability']);
                });

                $winner = $qualifiedCandidates[0];
                $existing = $winner['existing_signal'];
                if ($existing instanceof PulseSignal) {
                    $bestSignal = $existing;
                    if (! $bestSignal->unlocked_at) {
                        $bestSignal->forceFill(['unlocked_at' => now()])->save();
                    }
                } else {
                    // V15.1 direct-USDT architecture: Best Signal is included with an active package.
                    $bestSignal = DB::transaction(function () use ($winner, $user, $run): PulseSignal {
                        $analysis = $winner['analysis'];
                        $generatedAt = $winner['generated_at'];
                        $fingerprint = hash('sha256', implode('|', [
                            $winner['symbol'], $winner['timeframe'], $analysis['direction'], $analysis['entry_price'], $analysis['stop_loss'],
                            implode(',', $winner['tp_levels']), $winner['strategy_version'], $generatedAt->format('Y-m-d H:i'),
                        ]));

                        $signal = PulseSignal::create([
                            'user_id' => $user?->id,
                            'scanner_run_id' => $run->id,
                            'symbol' => $winner['symbol'],
                            'timeframe' => $winner['timeframe'],
                            'direction' => $analysis['direction'],
                            'entry_price' => $analysis['entry_price'],
                            'stop_loss' => $analysis['stop_loss'],
                            'take_profit' => $analysis['take_profit'],
                            'take_profit_levels' => $winner['tp_levels'],
                            'score' => $analysis['score'],
                            'technical_score' => $analysis['score'],
                            'reliability_score' => $winner['reliability'],
                            'confidence_score' => $winner['confidence'],
                            'confidence_label' => $this->confidenceLabel($winner['confidence']),
                            'status' => 'active',
                            'strategy_breakdown' => $this->signalBreakdown($analysis),
                            'strategy_snapshot' => $winner['strategy_snapshot'],
                            'strategy_version' => $winner['strategy_version'],
                            'signal_fingerprint' => $fingerprint,
                            'generated_at' => $generatedAt,
                            'expires_at' => $generatedAt->copy()->addMinutes((int) config('pulse.scanner.signal_expiry_minutes', 90)),
                            'unlocked_at' => now(),
                        ]);
                        $this->validations->ensureValidation($signal);
                        return $signal;
                    }, 5);

                    $created = 1;

                    if ($user && ($settings?->notification_preferences['signals'] ?? true)) {
                        try {
                            PulseAlert::create([
                                'user_id' => $user->id,
                                'type' => 'signal',
                                'title' => "Best Signal: {$bestSignal->direction} {$bestSignal->symbol}",
                                'message' => "ABS reviewed the Admin-defined market universe across 15M and 4H. {$bestSignal->symbol} {$bestSignal->timeframe} ranked highest with {$bestSignal->confidence_score} confidence.",
                                'severity' => 'info',
                                'action_url' => route('pulse.signals.show', $bestSignal),
                                'data' => ['signal_id' => $bestSignal->id, 'symbol' => $bestSignal->symbol, 'timeframe' => $bestSignal->timeframe, 'access_model' => 'active_package'],
                            ]);
                            $this->mail->qualifiedSignal($user, $bestSignal);
                        } catch (\Throwable $sideEffectError) {
                            $results[] = ['type' => 'warning', 'scope' => 'notification', 'message' => $sideEffectError->getMessage()];
                        }
                    }
                }
            }

            $run->update([
                'status' => 'completed',
                'pairs_scanned' => $pairs->count(),
                'signals_created' => $created,
                'best_signal_id' => $bestSignal?->id,
                'completed_at' => now(),
                'summary' => $results,
                'error_message' => null,
            ]);

            $this->audit->record('scanner.completed', $user, 'PulseScannerRun', $run->id, $settings?->environment, [
                'pairs_scanned' => $pairs->count(),
                'market_timeframes_evaluated' => $available,
                'market_timeframes_unavailable' => max(0, ($pairs->count() * count($scanTimeframes)) - $available),
                'signals_created' => $created,
                'qualified_candidates' => count($qualifiedCandidates),
                'best_signal_id' => $bestSignal?->id,
                'timeframe' => $runTimeframe,
                'timeframes' => $scanTimeframes,
                'admin_universe_scan' => (bool) $user,
                'effective_minimum_score' => $threshold,
            ]);

            return $run->fresh(['signals', 'bestSignal']);
        } catch (\Throwable $e) {
            // If the Best Signal transaction already committed, preserve it as a
            // successful unlock. Never tell the client to retry a completed action.
            if ($bestSignal instanceof PulseSignal) {
                $created = max(1, $created);
                try {
                    $run->update([
                        'status' => 'completed',
                        'signals_created' => $created,
                        'best_signal_id' => $bestSignal->id,
                        'completed_at' => now(),
                        'summary' => array_merge($results, [[
                            'type' => 'warning',
                            'scope' => 'post_unlock',
                            'message' => $e->getMessage(),
                        ]]),
                        'error_message' => null,
                    ]);
                } catch (\Throwable) {
                    // The committed signal remains authoritative.
                }

                try {
                    $this->audit->record('scanner.completed_with_warning', $user, 'PulseScannerRun', $run->id, $settings?->environment, [
                        'best_signal_id' => $bestSignal->id,
                        'warning' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {
                    // Audit persistence must not invalidate a committed signal unlock.
                }

                return $run->fresh(['signals', 'bestSignal']);
            }

            $run->update([
                'status' => 'failed',
                'pairs_scanned' => count($results),
                'signals_created' => $created,
                'best_signal_id' => null,
                'completed_at' => now(),
                'summary' => $results,
                'error_message' => $e->getMessage(),
            ]);
            $this->audit->record('scanner.failed', $user, 'PulseScannerRun', $run->id, $settings?->environment, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function analyze(PulsePair $pair, string $timeframe, ?Collection $strategies = null, ?array $rows = null): array
    {
        $rows ??= $this->market->scannerRows($pair->symbol, $timeframe, (int) config('pulse.scanner.candle_limit', 120));
        if (count($rows) < 60) {
            throw new RuntimeException('Insufficient live candle history.');
        }

        $candles = collect($rows)->map(fn (array $row): array => [
            'open_time' => (int) $row[0], 'open' => (float) $row[1], 'high' => (float) $row[2],
            'low' => (float) $row[3], 'close' => (float) $row[4], 'volume' => (float) $row[5],
        ])->values();

        $closes = $candles->pluck('close')->all();
        $highs = $candles->pluck('high')->all();
        $lows = $candles->pluck('low')->all();
        $volumes = $candles->pluck('volume')->all();
        $lastCandle = $candles->last();
        $previous = $candles->get($candles->count() - 2);
        $last = (float) $lastCandle['close'];
        $ema9 = $this->ema($closes, 9);
        $ema20 = $this->ema($closes, 20);
        $ema50 = $this->ema($closes, 50);
        $rsi = $this->rsi($closes, 14);
        $atr = $this->atr($candles->all(), 14);
        [$macd, $macdSignal] = $this->macd($closes);
        [$bbMid, $bbUpper, $bbLower, $bbWidth] = $this->bollinger($closes, 20, 2);
        $averageVolume = $this->average(array_slice($volumes, -21, 20));
        $lastVolume = (float) end($volumes);
        $recentHigh = max(array_slice($highs, -21, 20));
        $recentLow = min(array_slice($lows, -21, 20));
        $previousHigh = max(array_slice($highs, -22, 20));
        $previousLow = min(array_slice($lows, -22, 20));
        $body = abs((float) $lastCandle['close'] - (float) $lastCandle['open']);
        $range = max((float) $lastCandle['high'] - (float) $lastCandle['low'], 0.00000001);
        $bodyRatio = $body / $range;
        $return5 = ($last / max((float) $closes[count($closes) - 6], 0.00000001) - 1) * 100;
        $atrPercent = ($atr / max($last, 0.00000001)) * 100;

        $strategies ??= PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get();
        $long = 0.0;
        $short = 0.0;
        $maximumWeightedScore = 0.0;
        $breakdown = [];

        foreach ($strategies as $strategy) {
            $weight = max(0.0, (float) ($strategy->pivot?->weight_override ?? $strategy->weight ?? 1));
            $maximumPoints = match ($strategy->slug) {
                'trend-alignment' => 8, 'ema-crossover' => 7, 'rsi-recovery' => 6,
                'macd-momentum' => 7, 'breakout-confirmation' => 9, 'volume-expansion' => 6,
                'atr-volatility' => 4, 'market-structure' => 7, 'trend-pullback' => 6,
                'bollinger-reversion' => 5, 'momentum-continuation' => 6, 'range-compression' => 4,
                'candle-strength' => 5, 'swing-sequence' => 5, 'risk-reward-quality' => 7,
                default => 0,
            };
            $maximumWeightedScore += $maximumPoints * $weight;
            [$bias, $points, $reason] = match ($strategy->slug) {
                'trend-alignment' => $ema20 > $ema50 && $last > $ema20
                    ? ['LONG', 8, 'Price, EMA20 and EMA50 are aligned upward']
                    : ($ema20 < $ema50 && $last < $ema20 ? ['SHORT', 8, 'Price, EMA20 and EMA50 are aligned downward'] : ['NEUTRAL', 0, 'Trend alignment is mixed']),
                'ema-crossover' => $ema9 > $ema20 ? ['LONG', 7, 'EMA9 is above EMA20'] : ($ema9 < $ema20 ? ['SHORT', 7, 'EMA9 is below EMA20'] : ['NEUTRAL', 0, 'EMA crossover is flat']),
                'rsi-recovery' => $rsi >= 52 && $rsi <= 70 ? ['LONG', 6, 'RSI supports positive momentum without an extreme reading'] : ($rsi <= 48 && $rsi >= 30 ? ['SHORT', 6, 'RSI supports negative momentum without an extreme reading'] : ['NEUTRAL', 0, 'RSI is outside the configured recovery zone']),
                'macd-momentum' => $macd > $macdSignal ? ['LONG', 7, 'MACD is above its signal line'] : ($macd < $macdSignal ? ['SHORT', 7, 'MACD is below its signal line'] : ['NEUTRAL', 0, 'MACD is neutral']),
                'breakout-confirmation' => $last > $previousHigh ? ['LONG', 9, 'Close is above the prior 20-candle high'] : ($last < $previousLow ? ['SHORT', 9, 'Close is below the prior 20-candle low'] : ['NEUTRAL', 0, 'No confirmed range breakout']),
                'volume-expansion' => $averageVolume > 0 && $lastVolume >= $averageVolume * 1.25 ? [$last >= $previous['close'] ? 'LONG' : 'SHORT', 6, 'Volume is at least 25% above the recent average'] : ['NEUTRAL', 0, 'Volume is not materially expanded'],
                'atr-volatility' => $atrPercent >= 0.15 && $atrPercent <= 4.5 ? [$last >= $ema20 ? 'LONG' : 'SHORT', 4, 'ATR is inside the configured operating range'] : ['NEUTRAL', -4, 'Volatility is outside the configured operating range'],
                'market-structure' => $recentHigh > $previousHigh && $recentLow >= $previousLow ? ['LONG', 7, 'Recent structure is forming higher highs and higher lows'] : ($recentLow < $previousLow && $recentHigh <= $previousHigh ? ['SHORT', 7, 'Recent structure is forming lower highs and lower lows'] : ['NEUTRAL', 0, 'Market structure is not directional']),
                'trend-pullback' => $ema20 > $ema50 && $last >= $ema20 && abs($last - $ema20) <= $atr ? ['LONG', 6, 'Price is holding near EMA20 within an upward trend'] : ($ema20 < $ema50 && $last <= $ema20 && abs($last - $ema20) <= $atr ? ['SHORT', 6, 'Price is holding near EMA20 within a downward trend'] : ['NEUTRAL', 0, 'No qualified trend pullback']),
                'bollinger-reversion' => $last <= $bbLower && $rsi < 38 ? ['LONG', 5, 'Price is below the lower Bollinger band with weak RSI'] : ($last >= $bbUpper && $rsi > 62 ? ['SHORT', 5, 'Price is above the upper Bollinger band with strong RSI'] : ['NEUTRAL', 0, 'No Bollinger reversion condition']),
                'momentum-continuation' => $return5 >= 0.8 && $last > $ema20 ? ['LONG', 6, 'Five-candle momentum remains positive above EMA20'] : ($return5 <= -0.8 && $last < $ema20 ? ['SHORT', 6, 'Five-candle momentum remains negative below EMA20'] : ['NEUTRAL', 0, 'Momentum continuation is not confirmed']),
                'range-compression' => $bbWidth <= 0.035 ? [$last >= $bbMid ? 'LONG' : 'SHORT', 4, 'Bollinger width indicates range compression'] : ['NEUTRAL', 0, 'Range is not compressed'],
                'candle-strength' => $bodyRatio >= 0.65 ? [$lastCandle['close'] >= $lastCandle['open'] ? 'LONG' : 'SHORT', 5, 'Latest candle has a strong directional body'] : ['NEUTRAL', 0, 'Latest candle body is not decisive'],
                'swing-sequence' => $last > $closes[count($closes)-3] && $closes[count($closes)-3] > $closes[count($closes)-5] ? ['LONG', 5, 'Recent closes form an upward sequence'] : ($last < $closes[count($closes)-3] && $closes[count($closes)-3] < $closes[count($closes)-5] ? ['SHORT', 5, 'Recent closes form a downward sequence'] : ['NEUTRAL', 0, 'Recent swing sequence is mixed']),
                'risk-reward-quality' => $atr > 0 ? [$last >= $ema20 ? 'LONG' : 'SHORT', 7, 'ATR supports a defined stop and reward distance'] : ['NEUTRAL', -5, 'Risk distance cannot be calculated'],
                default => ['NEUTRAL', 0, 'No executable evaluator is configured for this strategy'],
            };

            $points *= $weight;
            if ($bias === 'LONG') $long += max(0, $points);
            elseif ($bias === 'SHORT') $short += max(0, $points);
            elseif ($points < 0) { $long += $points; $short += $points; }

            $breakdown[] = ['name' => $strategy->name, 'slug' => $strategy->slug, 'version' => (string) ($strategy->version ?? '1.0'), 'bias' => $bias, 'points' => round($points, 2), 'reason' => $reason];
        }

        // Normalize against the maximum score of the strategies actually
        // included in this package. A plan with 5 strategies and a plan with
        // all 15 therefore share the same meaningful 0–100 signal threshold.
        $scale = $maximumWeightedScore > 0 ? (100 / $maximumWeightedScore) : 0;
        $long = max(0, min(100, $long * $scale));
        $short = max(0, min(100, $short * $scale));
        $direction = abs($long - $short) < 8 ? 'NEUTRAL' : ($long > $short ? 'LONG' : 'SHORT');
        $score = round(max($long, $short), 2);
        $stopDistance = max($atr * 1.25, $last * ((float) config('pulse.risk.default_stop_loss_percent', 1) / 100));
        $rewardDistance = max($atr * 2.0, $last * ((float) config('pulse.risk.default_take_profit_percent', 2) / 100));
        $stop = $direction === 'SHORT' ? $last + $stopDistance : $last - $stopDistance;
        $target = $direction === 'SHORT' ? $last - $rewardDistance : $last + $rewardDistance;

        return [
            'symbol' => $pair->symbol, 'direction' => $direction, 'score' => $score,
            'long_score' => round($long, 2), 'short_score' => round($short, 2),
            'last_price' => $this->binance->normalizePrice($pair, $last),
            'change_percent' => null,
            'entry_price' => $this->binance->normalizePrice($pair, $last),
            'stop_loss' => $this->binance->normalizePrice($pair, max($stop, 0.00000001)),
            'take_profit' => $this->binance->normalizePrice($pair, max($target, 0.00000001)),
            'indicators' => compact('ema9', 'ema20', 'ema50', 'rsi', 'atr', 'macd', 'macdSignal', 'bbWidth', 'return5', 'atrPercent'),
            'strategies' => $breakdown,
        ];
    }

    private function strategiesFor(?User $user): Collection
    {
        $plan = $user?->pulsePlan();
        if ($plan && $plan->strategies()->exists()) {
            return $plan->strategies()->where('pulse_strategies.is_enabled', true)->wherePivot('is_enabled', true)->orderBy('sort_order')->get();
        }
        return PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get();
    }

    private function ema(array $values, int $period): float
    {
        $multiplier = 2 / ($period + 1);
        $ema = $this->average(array_slice($values, 0, $period));
        foreach (array_slice($values, $period) as $value) $ema = ((float) $value - $ema) * $multiplier + $ema;
        return $ema;
    }

    private function rsi(array $values, int $period): float
    {
        $gains = 0.0; $losses = 0.0; $start = max(1, count($values) - $period);
        for ($i = $start; $i < count($values); $i++) {
            $change = (float) $values[$i] - (float) $values[$i - 1];
            if ($change >= 0) $gains += $change; else $losses += abs($change);
        }
        if ($losses === 0.0) return 100.0;
        $rs = ($gains / $period) / ($losses / $period);
        return 100 - (100 / (1 + $rs));
    }

    private function atr(array $candles, int $period): float
    {
        $ranges = [];
        for ($i = max(1, count($candles) - $period); $i < count($candles); $i++) {
            $current = $candles[$i]; $previousClose = (float) $candles[$i - 1]['close'];
            $ranges[] = max((float) $current['high'] - (float) $current['low'], abs((float) $current['high'] - $previousClose), abs((float) $current['low'] - $previousClose));
        }
        return $this->average($ranges);
    }

    private function macd(array $values): array
    {
        $fast = $this->ema($values, 12); $slow = $this->ema($values, 26); $macd = $fast - $slow;
        $series = [];
        for ($i = max(26, count($values)-20); $i <= count($values); $i++) {
            $slice = array_slice($values, 0, $i);
            if (count($slice) >= 26) $series[] = $this->ema($slice, 12) - $this->ema($slice, 26);
        }
        $signal = count($series) >= 9 ? $this->ema($series, 9) : $macd;
        return [$macd, $signal];
    }

    private function bollinger(array $values, int $period, float $deviations): array
    {
        $slice = array_slice($values, -$period); $mid = $this->average($slice);
        $variance = $this->average(array_map(fn ($v) => ((float)$v - $mid) ** 2, $slice));
        $std = sqrt($variance); $upper = $mid + $deviations*$std; $lower = $mid - $deviations*$std;
        return [$mid, $upper, $lower, $mid > 0 ? ($upper-$lower)/$mid : 0];
    }

    private function average(array $values): float { return $values === [] ? 0.0 : array_sum($values) / count($values); }
    private function signalBreakdown(array $analysis, ?PulseSignal $existingSignal = null): array
    {
        $entry = (float) ($analysis['entry_price'] ?? 0);
        $previousMeta = [];
        if ($existingSignal) {
            foreach ($existingSignal->strategy_breakdown ?? [] as $item) {
                if (is_array($item) && isset($item['_meta']) && is_array($item['_meta'])) {
                    $previousMeta = $item['_meta'];
                    break;
                }
            }
        }
        $scanSequence = $existingSignal
            ? max(2, ((int) ($previousMeta['scan_sequence'] ?? 1)) + 1)
            : 1;

        $meta = [
            '_meta' => [
                'last_price' => isset($analysis['last_price']) && is_numeric($analysis['last_price']) ? (float) $analysis['last_price'] : $entry,
                'change_percent' => isset($analysis['change_percent']) && is_numeric($analysis['change_percent']) ? (float) $analysis['change_percent'] : null,
                'entry_low' => $entry > 0 ? $entry * .997 : 0,
                'entry_high' => $entry > 0 ? $entry * 1.003 : 0,
                'atr_percent' => is_numeric(data_get($analysis, 'indicators.atrPercent')) ? (float) data_get($analysis, 'indicators.atrPercent') : null,
                'scan_sequence' => $scanSequence,
                'market_regime' => $analysis['market_regime'] ?? null,
                'learning_evidence' => $analysis['learning_evidence'] ?? null,
            ],
        ];

        return array_merge(array_values((array) ($analysis['strategies'] ?? [])), [$meta]);
    }

    private function strategyBundleVersion(array $strategies): string
    {
        $identity = collect($strategies)->map(fn ($s) => [
            'slug' => (string) ($s['slug'] ?? ''), 'version' => (string) ($s['version'] ?? '1.0'),
        ])->sortBy('slug')->values()->all();
        return 'engine-14.8.17-'.substr(hash('sha256', json_encode($identity)), 0, 12);
    }

    private function marketRegime(array $analysis): string
    {
        $atr = (float) data_get($analysis, 'indicators.atrPercent', 0);
        $ema20 = (float) data_get($analysis, 'indicators.ema20', 0);
        $ema50 = (float) data_get($analysis, 'indicators.ema50', 0);
        if ($atr >= 2.5) return 'high_volatility';
        if ($atr <= 0.35) return 'low_volatility';
        if ($ema20 > 0 && $ema50 > 0 && abs($ema20 - $ema50) / max($ema50, 0.00000001) >= 0.01) return 'trending';
        return 'balanced';
    }

    private function takeProfitLevels(PulsePair $pair, array $analysis): array
    {
        $entry = (float) ($analysis['entry_price'] ?? 0); $stop = (float) ($analysis['stop_loss'] ?? 0); $final = (float) ($analysis['take_profit'] ?? 0);
        $risk = abs($entry - $stop); if ($entry <= 0 || $risk <= 0 || $final <= 0) return [$final];
        $sign = strtoupper((string) ($analysis['direction'] ?? 'LONG')) === 'SHORT' ? -1 : 1;
        $levels = [$entry + ($sign * $risk), $entry + ($sign * $risk * 1.5), $final];
        return collect($levels)->map(fn ($p) => (float) $this->binance->normalizePrice($pair, max($p, 0.00000001)))->unique()->values()->all();
    }

    private function confidenceLabel(float $score): string { return match (true) { $score >= 85 => 'Strong', $score >= 75 => 'High', $score >= 65 => 'Qualified', default => 'Review' }; }

    private function defaultUserSettings(?User $user = null): array
    {
        return [
            'environment' => config('pulse.default_environment', 'testnet'), 'execution_mode' => 'signal_only',
            'auto_trade_enabled' => false, 'emergency_stop' => false, 'default_leverage' => 3,
            'margin_type' => 'ISOLATED', 'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1,
            'sizing_mode' => 'fixed_notional', 'fixed_notional' => 25, 'fixed_quantity' => null,
            'minimum_signal_score' => $this->thresholds->packageDefault($user), 'default_order_type' => 'MARKET', 'take_profit_percent' => 2,
            'stop_loss_percent' => 1, 'daily_loss_limit' => 0, 'max_open_positions' => 2,
            'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
            'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
        ];
    }
}
