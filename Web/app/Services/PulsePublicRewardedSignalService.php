<?php

namespace App\Services;

use App\Models\PulsePair;
use App\Models\PulseMarketCandle;
use App\Models\PulseMarketPrice;
use App\Models\PulsePublicSignalUnlock;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseSystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PulsePublicRewardedSignalService
{
    public const VISITOR_COOKIE = 'abs_public_signal_visitor';
    public const TEST_AD_UNIT = '/22639388115/rewarded_web_example';

    public function __construct(private readonly PulseScannerService $scanner) {}

    public function settings(): array
    {
        $input = trim((string) PulseSystemSetting::value('rewarded_web_ad_unit_input', ''));
        $storedPath = trim((string) PulseSystemSetting::value('rewarded_web_ad_unit_path', ''));
        $resolvedPath = $this->resolveAdUnitPath($input) ?: $this->resolveAdUnitPath($storedPath);

        return [
            'enabled' => (bool) PulseSystemSetting::value('public_rewarded_signals_enabled', true),
            'cooldown_minutes' => max(1, (int) PulseSystemSetting::value('public_rewarded_signal_cooldown_minutes', 30)),
            'visibility_mode' => 'until_refresh',
            'min_claim_seconds' => max(3, min(60, (int) PulseSystemSetting::value('public_rewarded_signal_min_claim_seconds', 5))),
            'web_ad_unit_input' => $input,
            'web_ad_unit_path' => $resolvedPath,
            'web_test_mode' => (bool) PulseSystemSetting::value('rewarded_web_test_mode', true),
            'badge' => trim((string) PulseSystemSetting::value('public_rewarded_signal_badge', 'FREE SIGNAL')),
            'title' => trim((string) PulseSystemSetting::value('public_rewarded_signal_title', 'Watch a short ad to unlock your Pulse signal.')),
            'description' => trim((string) PulseSystemSetting::value('public_rewarded_signal_description', 'Your qualified signal appears immediately after the ad completes.')),
            'cta' => trim((string) PulseSystemSetting::value('public_rewarded_signal_cta', 'Watch Ad & Unlock Signal')),
        ];
    }

    /**
     * Accept an Ad Manager rewarded ad-unit path or a copied GPT code snippet.
     * Only the /network-code/ad-unit-code path is extracted; supplied JavaScript
     * is never executed by ABS.
     */
    public function resolveAdUnitPath(?string $input): string
    {
        $input = trim((string) $input);
        if ($input === '') return '';

        $candidates = [$input];
        if (preg_match_all('~[\"\'](/?\d{3,}/[A-Za-z0-9_./-]+)[\"\']~', $input, $matches)) {
            $candidates = array_merge($matches[1], $candidates);
        }
        if (preg_match('~(/\d{3,}/[A-Za-z0-9_./-]+)~', $input, $match)) {
            array_unshift($candidates, $match[1]);
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B\"'");
            if (preg_match('~^/?\d{3,}/[A-Za-z0-9_./-]+$~', $candidate)) {
                return '/'.ltrim($candidate, '/');
            }
        }

        return '';
    }

    /** @return array{raw:string,hash:string,is_new:bool} */
    public function visitor(Request $request): array
    {
        $raw = trim((string) $request->cookie(self::VISITOR_COOKIE, ''));
        $valid = (bool) preg_match('/^[A-Za-z0-9_-]{32,128}$/', $raw);
        if (! $valid) {
            $raw = Str::random(64);
        }

        return ['raw' => $raw, 'hash' => $this->visitorHash($raw), 'is_new' => ! $valid];
    }

    public function status(string $rawVisitor): array
    {
        $settings = $this->settings();
        $latest = PulsePublicSignalUnlock::query()
            ->where('visitor_hash', $this->visitorHash($rawVisitor))
            ->where('status', 'granted')
            ->latest('claimed_at')
            ->first();

        $cooldownRemaining = $latest?->next_available_at?->isFuture()
            ? max(0, now()->diffInSeconds($latest->next_available_at, false))
            : 0;
        // A granted signal is intentionally page-session only. It is returned by
        // claim() and rendered by JavaScript, but status/index never re-exposes it.
        // Refreshing or navigating away therefore hides the signal while the
        // 30-minute browser cooldown remains enforced server-side.
        return [
            ...$settings,
            'available' => $settings['enabled'] && $cooldownRemaining <= 0,
            'cooldown_remaining' => $cooldownRemaining,
            'next_available_at' => $latest?->next_available_at?->toIso8601String(),
            'active_signal' => null,
        ];
    }

    public function createAdSession(string $rawVisitor): array
    {
        $status = $this->status($rawVisitor);
        if (! $status['enabled']) {
            throw new RuntimeException('Free rewarded signals are currently paused.');
        }
        if (! $status['available']) {
            throw new RuntimeException('Your next free signal will be available after the 30-minute cooldown.');
        }
        if (! $status['web_test_mode'] && $status['web_ad_unit_path'] === '') {
            throw new RuntimeException('The rewarded ad unit is not configured yet.');
        }

        // Resolve and reserve a signal before the visitor watches an ad. Technical
        // scanner/feed failures are never exposed to the public page. If no fresh
        // setup qualifies, ABS may use an already-active qualified setup or the
        // highest-scoring non-qualified LONG/SHORT evaluation as ENTRY WATCH. If
        // neither exists, the visitor is asked to check again later and no ad is shown.
        $prepared = $this->prepareSignalForReward();
        $preparedSignal = $prepared['signal'] ?? null;
        $preparedWatch = is_array($prepared['market_watch'] ?? null) ? $prepared['market_watch'] : null;
        if (! $preparedSignal instanceof PulseSignal && ! $preparedWatch) {
            throw new RuntimeException('No new qualified Pulse opportunity is available right now. ABS is monitoring the market and will make the next free signal available as soon as a setup qualifies.');
        }

        $reference = (string) Str::uuid();
        $expiresAt = now()->addMinutes(10);
        $payload = [
            'v' => 1,
            'visitor' => $this->visitorHash($rawVisitor),
            'ref' => $reference,
            'exp' => $expiresAt->timestamp,
            'nonce' => Str::random(24),
            'iat' => now()->timestamp,
            'min' => now()->addSeconds((int) $status['min_claim_seconds'])->timestamp,
            'provider' => 'google_ad_manager',
            'resource_type' => $preparedSignal instanceof PulseSignal ? 'qualified_signal' : 'market_watch',
            'signal_id' => $preparedSignal instanceof PulseSignal ? (int) $preparedSignal->id : 0,
            'watch_run_id' => (int) ($preparedWatch['run_id'] ?? 0),
            'watch_summary_index' => (int) ($preparedWatch['summary_index'] ?? -1),
            'signal_source' => (string) ($prepared['source'] ?? 'active'),
            'signal_notice' => (string) ($prepared['notice'] ?? ''),
        ];

        return [
            'provider_reference' => $reference,
            'claim_token' => $this->signPayload($payload),
            'ad_unit_path' => $status['web_test_mode'] ? self::TEST_AD_UNIT : $status['web_ad_unit_path'],
            'test_mode' => (bool) $status['web_test_mode'],
            'reveal_kind' => $preparedSignal instanceof PulseSignal ? 'qualified_signal' : 'entry_watch',
            'visibility_mode' => 'until_refresh',
            'cooldown_minutes' => (int) $status['cooldown_minutes'],
            'min_claim_seconds' => (int) $status['min_claim_seconds'],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function claim(string $rawVisitor, string $claimToken, array $providerPayload = []): PulsePublicSignalUnlock
    {
        $payload = $this->verifySignedPayload($claimToken);
        $visitorHash = $this->visitorHash($rawVisitor);
        if (! hash_equals((string) ($payload['visitor'] ?? ''), $visitorHash)) {
            throw new RuntimeException('This reward session belongs to a different browser.');
        }
        if (($payload['provider'] ?? '') !== 'google_ad_manager') {
            throw new RuntimeException('Reward provider is invalid.');
        }
        if ((int) ($payload['min'] ?? 0) > now()->timestamp) {
            throw new RuntimeException('The rewarded ad session was claimed too quickly. Please complete the ad before revealing a signal.');
        }
        $reference = trim((string) ($payload['ref'] ?? ''));
        if ($reference === '') {
            throw new RuntimeException('Reward session reference is invalid.');
        }

        $known = PulsePublicSignalUnlock::query()->where('provider_reference', $reference)->first();
        if ($known) {
            if (! hash_equals((string) $known->visitor_hash, $visitorHash)) {
                throw new RuntimeException('Reward session belongs to another browser.');
            }
            return $known;
        }

        $status = $this->status($rawVisitor);
        if (! $status['enabled']) {
            throw new RuntimeException('Free rewarded signals are currently paused.');
        }
        if (! $status['available']) {
            throw new RuntimeException('A free signal was already unlocked recently. Please wait for the cooldown to finish.');
        }

        $signal = null;
        $snapshot = null;
        $resourceType = (string) ($payload['resource_type'] ?? 'qualified_signal');
        $signalContext = [
            'source' => (string) ($payload['signal_source'] ?? 'active'),
            'notice' => trim((string) ($payload['signal_notice'] ?? '')),
        ];

        if ($resourceType === 'market_watch') {
            $snapshot = $this->marketWatchSnapshotFromReservation(
                (int) ($payload['watch_run_id'] ?? 0),
                (int) ($payload['watch_summary_index'] ?? -1),
                $signalContext,
            );
        } else {
            $reservedSignalId = (int) ($payload['signal_id'] ?? 0);
            $signal = $reservedSignalId > 0 ? PulseSignal::query()->find($reservedSignalId) : null;
            if (! $signal || ! $this->isRewardableSignal($signal)) {
                $prepared = $this->prepareSignalForReward(false);
                $signal = $prepared['signal'] ?? null;
                $signalContext = [
                    'source' => (string) ($prepared['source'] ?? 'active'),
                    'notice' => trim((string) ($prepared['notice'] ?? '')),
                ];
            }
            if ($signal instanceof PulseSignal) {
                $snapshot = $this->snapshot($signal, $signalContext);
            }
        }

        if (! is_array($snapshot) || $snapshot === []) {
            throw new RuntimeException('Market conditions changed before the reward completed. No cooldown was applied. Please try again when the next Pulse opportunity or market-watch setup is available.');
        }

        $settings = $this->settings();
        return DB::transaction(function () use ($visitorHash, $reference, $providerPayload, $signal, $snapshot, $settings, $signalContext): PulsePublicSignalUnlock {
            $existing = PulsePublicSignalUnlock::query()->where('provider_reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $latest = PulsePublicSignalUnlock::query()
                ->where('visitor_hash', $visitorHash)
                ->where('status', 'granted')
                ->latest('claimed_at')
                ->lockForUpdate()
                ->first();
            if ($latest?->next_available_at?->isFuture()) {
                throw new RuntimeException('A free signal was already unlocked recently. Please wait for the cooldown to finish.');
            }

            $now = now();
            return PulsePublicSignalUnlock::query()->create([
                'visitor_hash' => $visitorHash,
                'provider' => 'google_ad_manager',
                'provider_reference' => $reference,
                'ad_unit' => $settings['web_test_mode'] ? self::TEST_AD_UNIT : $settings['web_ad_unit_path'],
                'signal_id' => $signal?->id,
                'signal_snapshot' => $snapshot,
                'status' => 'granted',
                'claimed_at' => $now,
                'view_expires_at' => null,
                'next_available_at' => $now->copy()->addMinutes((int) $settings['cooldown_minutes']),
                'meta' => [
                    'platform' => 'web',
                    'provider_payload' => $providerPayload,
                    'signal_source' => $signalContext['source'] ?? 'active',
                    'presentation' => (string) ($snapshot['presentation'] ?? 'qualified_signal'),
                ],
            ]);
        }, 5);
    }

    public function snapshot(PulseSignal $signal, array $context = []): array
    {
        $strategies = collect($signal->strategy_breakdown ?? [])
            ->pluck('name')->filter()->unique()->values()->take(6)->all();

        $market = PulseMarketPrice::query()->where('symbol', $signal->symbol)->first();
        $timeframe = strtolower((string) $signal->timeframe);
        $candles = PulseMarketCandle::query()
            ->where('symbol', $signal->symbol)
            ->where('timeframe', $timeframe)
            ->orderByDesc('open_time_ms')
            ->limit(48)
            ->get(['open_time_ms', 'open', 'high', 'low', 'close', 'volume'])
            ->reverse()
            ->values()
            ->map(fn (PulseMarketCandle $candle): array => [
                'time' => (int) floor(((int) $candle->open_time_ms) / 1000),
                'open' => (float) $candle->open,
                'high' => (float) $candle->high,
                'low' => (float) $candle->low,
                'close' => (float) $candle->close,
                'volume' => (float) $candle->volume,
            ])->all();

        $direction = strtoupper((string) $signal->direction);
        $primaryTarget = collect($signal->take_profit_levels ?? [])->filter(fn ($value) => is_numeric($value))->first();
        $primaryTarget ??= is_numeric($signal->take_profit) ? (float) $signal->take_profit : null;
        $summary = $direction === 'SHORT'
            ? 'Pulse qualified a bearish setup. Monitor price around the planned entry, targets and protective stop shown below.'
            : 'Pulse qualified a bullish setup. Monitor price around the planned entry, targets and protective stop shown below.';

        return [
            'symbol' => $signal->symbol,
            'timeframe' => strtoupper((string) $signal->timeframe),
            'direction' => $direction,
            'entry_price' => (string) $signal->entry_price,
            'stop_loss' => (string) $signal->stop_loss,
            'take_profit' => (string) $signal->take_profit,
            'take_profit_levels' => array_values($signal->take_profit_levels ?? []),
            'score' => round((float) $signal->score, 1),
            'confidence_score' => round((float) ($signal->confidence_score ?? $signal->score), 1),
            'confidence_label' => (string) ($signal->confidence_label ?: 'Qualified'),
            'strategies' => $strategies,
            'generated_at' => $signal->generated_at?->toIso8601String(),
            'expires_at' => $signal->expires_at?->toIso8601String(),
            'current_price' => is_numeric($market?->price) ? (float) $market->price : null,
            'change_percent_24h' => is_numeric($market?->change_percent_24h) ? (float) $market->change_percent_24h : null,
            'high_24h' => is_numeric($market?->high_24h) ? (float) $market->high_24h : null,
            'low_24h' => is_numeric($market?->low_24h) ? (float) $market->low_24h : null,
            'volume_24h' => is_numeric($market?->volume_24h) ? (float) $market->volume_24h : null,
            'key_level' => $primaryTarget,
            'market_bias' => $direction === 'SHORT' ? 'BEARISH' : 'BULLISH',
            'setup_summary' => $summary,
            'availability_notice' => trim((string) ($context['notice'] ?? '')),
            'signal_source' => (string) ($context['source'] ?? 'active'),
            'presentation' => 'qualified_signal',
            'is_qualified_signal' => true,
            'status_label' => 'QUALIFIED',
            'candles' => $candles,
        ];
    }

    private function randomQualifiedSignal(): ?PulseSignal
    {
        return PulseSignal::query()
            ->whereNull('user_id')
            ->where('status', 'active')
            ->whereIn('direction', ['LONG', 'SHORT'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->addMinutes(12)))
            ->where('generated_at', '>=', now()->subHours(12))
            ->inRandomOrder()
            ->first();
    }

    private function latestActiveQualifiedSignal(): ?PulseSignal
    {
        return PulseSignal::query()
            ->whereNull('user_id')
            ->where('status', 'active')
            ->whereIn('direction', ['LONG', 'SHORT'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->addMinutes(12)))
            ->latest('generated_at')
            ->first();
    }

    private function isRewardableSignal(PulseSignal $signal): bool
    {
        return $signal->status === 'active'
            && in_array(strtoupper((string) $signal->direction), ['LONG', 'SHORT'], true)
            && ($signal->expires_at === null || $signal->expires_at->gt(now()->addMinutes(1)));
    }

    /** @return array{signal:?PulseSignal,market_watch:?array,source:string,notice:string} */
    private function prepareSignalForReward(bool $allowScan = true): array
    {
        if ($signal = $this->randomQualifiedSignal()) {
            return ['signal' => $signal, 'market_watch' => null, 'source' => 'active', 'notice' => ''];
        }

        if ($allowScan) {
            try {
                $symbols = PulsePair::query()->where('is_enabled', true)->inRandomOrder()->limit(8)->pluck('symbol')->all();
                if ($symbols !== []) {
                    // Review both supported Pulse timeframes. If nothing reaches the
                    // qualifying threshold, the same completed scanner run can still
                    // provide the highest-scoring ENTRY WATCH setup without creating
                    // a PulseSignal record.
                    $run = $this->scanner->run(null, $symbols, 'all');
                    $signal = $run->bestSignal;
                    if ($signal instanceof PulseSignal && $this->isRewardableSignal($signal)) {
                        return ['signal' => $signal, 'market_watch' => null, 'source' => 'fresh_scan', 'notice' => ''];
                    }

                    if ($watch = $this->highestMarketWatchCandidate($run)) {
                        return [
                            'signal' => null,
                            'market_watch' => $watch,
                            'source' => 'entry_watch',
                            'notice' => 'No setup reached the current Pulse qualification threshold. This is the highest-scoring Entry Watch from the latest market check and is shown for monitoring only — it is not a qualified Pulse signal.',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Keep technical feed/scanner details in server logs. Public users see
                // only the premium market-watch/market-wait state.
                Log::warning('Public rewarded signal scan unavailable; attempting active-signal fallback.', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($fallback = $this->latestActiveQualifiedSignal()) {
            return [
                'signal' => $fallback,
                'market_watch' => null,
                'source' => 'active_fallback',
                'notice' => 'No new Pulse opportunity qualified in the latest market check. Showing the latest active qualified setup instead.',
            ];
        }

        return [
            'signal' => null,
            'market_watch' => null,
            'source' => 'market_wait',
            'notice' => 'No new qualified Pulse opportunity or Entry Watch setup is available right now. ABS is monitoring the market for the next setup.',
        ];
    }

    /** @return array{run_id:int,summary_index:int,score:float,symbol:string,timeframe:string}|null */
    private function highestMarketWatchCandidate(PulseScannerRun $run): ?array
    {
        $candidates = collect(array_values((array) ($run->summary ?? [])))
            ->map(fn ($row, $index) => ['row' => is_array($row) ? $row : [], 'index' => (int) $index])
            ->filter(function (array $item): bool {
                $row = $item['row'];
                $direction = strtoupper((string) ($row['direction'] ?? ''));
                return in_array($direction, ['LONG', 'SHORT'], true)
                    && is_numeric($row['score'] ?? null)
                    && (float) $row['score'] > 0
                    && is_numeric($row['entry_price'] ?? null)
                    && is_numeric($row['stop_loss'] ?? null)
                    && is_numeric($row['take_profit'] ?? null);
            })
            ->sortByDesc(fn (array $item) => (float) ($item['row']['score'] ?? 0))
            ->values();

        $best = $candidates->first();
        if (! is_array($best)) return null;
        $row = $best['row'];
        return [
            'run_id' => (int) $run->id,
            'summary_index' => (int) $best['index'],
            'score' => (float) ($row['score'] ?? 0),
            'symbol' => strtoupper((string) ($row['symbol'] ?? '')),
            'timeframe' => strtolower((string) ($row['timeframe'] ?? '15m')),
        ];
    }

    private function marketWatchSnapshotFromReservation(int $runId, int $summaryIndex, array $context = []): ?array
    {
        if ($runId <= 0 || $summaryIndex < 0) return null;
        $run = PulseScannerRun::query()->find($runId);
        if (! $run || $run->status !== 'completed') return null;
        $summary = array_values((array) ($run->summary ?? []));
        $analysis = $summary[$summaryIndex] ?? null;
        if (! is_array($analysis)) return null;

        $direction = strtoupper((string) ($analysis['direction'] ?? ''));
        if (! in_array($direction, ['LONG', 'SHORT'], true)
            || ! is_numeric($analysis['score'] ?? null)
            || (float) $analysis['score'] <= 0
            || ! is_numeric($analysis['entry_price'] ?? null)
            || ! is_numeric($analysis['stop_loss'] ?? null)
            || ! is_numeric($analysis['take_profit'] ?? null)) {
            return null;
        }

        $symbol = strtoupper((string) ($analysis['symbol'] ?? ''));
        $timeframe = strtolower((string) ($analysis['timeframe'] ?? '15m'));
        $market = PulseMarketPrice::query()->where('symbol', $symbol)->first();
        $candles = PulseMarketCandle::query()
            ->where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->orderByDesc('open_time_ms')
            ->limit(48)
            ->get(['open_time_ms', 'open', 'high', 'low', 'close', 'volume'])
            ->reverse()
            ->values()
            ->map(fn (PulseMarketCandle $candle): array => [
                'time' => (int) floor(((int) $candle->open_time_ms) / 1000),
                'open' => (float) $candle->open,
                'high' => (float) $candle->high,
                'low' => (float) $candle->low,
                'close' => (float) $candle->close,
                'volume' => (float) $candle->volume,
            ])->all();

        $strategies = collect((array) ($analysis['strategies'] ?? []))
            ->map(fn ($item) => is_array($item) ? (string) ($item['name'] ?? $item['slug'] ?? '') : '')
            ->filter()->unique()->values()->take(6)->all();
        $score = round((float) ($analysis['score'] ?? 0), 1);
        $entry = (float) $analysis['entry_price'];
        $target = (float) $analysis['take_profit'];
        $notice = trim((string) ($context['notice'] ?? '')) ?: 'This is the highest-scoring Entry Watch from the latest market check. It has not reached the Pulse qualification threshold and is not a qualified signal.';

        return [
            'symbol' => $symbol,
            'timeframe' => strtoupper($timeframe),
            'direction' => $direction,
            'entry_price' => (string) $entry,
            'stop_loss' => (string) $analysis['stop_loss'],
            'take_profit' => (string) $target,
            'take_profit_levels' => [$target],
            'score' => $score,
            'confidence_score' => $score,
            'confidence_label' => 'ENTRY WATCH',
            'strategies' => $strategies,
            'generated_at' => $run->completed_at?->toIso8601String(),
            'expires_at' => null,
            'current_price' => is_numeric($market?->price) ? (float) $market->price : (is_numeric($analysis['last_price'] ?? null) ? (float) $analysis['last_price'] : $entry),
            'change_percent_24h' => is_numeric($market?->change_percent_24h) ? (float) $market->change_percent_24h : (is_numeric($analysis['change_percent'] ?? null) ? (float) $analysis['change_percent'] : null),
            'high_24h' => is_numeric($market?->high_24h) ? (float) $market->high_24h : null,
            'low_24h' => is_numeric($market?->low_24h) ? (float) $market->low_24h : null,
            'volume_24h' => is_numeric($market?->volume_24h) ? (float) $market->volume_24h : null,
            'key_level' => $entry,
            'market_bias' => $direction === 'SHORT' ? 'BEARISH WATCH' : 'BULLISH WATCH',
            'setup_summary' => 'No qualified Pulse signal is active from this market check. This setup scored highest and is provided only as an Entry Watch so you can monitor whether price and strategy confirmation improve.',
            'availability_notice' => $notice,
            'signal_source' => 'entry_watch',
            'presentation' => 'market_watch',
            'is_qualified_signal' => false,
            'status_label' => 'ENTRY WATCH',
            'candles' => $candles,
        ];
    }

    private function visitorHash(string $raw): string
    {
        return hash_hmac('sha256', $raw, $this->tokenSecret());
    }

    private function signPayload(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $encoded, $this->tokenSecret(), true);
        return $encoded.'.'.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }

    private function verifySignedPayload(string $token): array
    {
        [$encoded, $signature] = array_pad(explode('.', trim($token), 2), 2, null);
        if (! $encoded || ! $signature) {
            throw new RuntimeException('Reward session token is malformed.');
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, $this->tokenSecret(), true)), '+/', '-_'), '=');
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Reward session token is invalid.');
        }
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $payload = json_decode((string) $json, true);
        if (! is_array($payload) || (int) ($payload['exp'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Reward session has expired.');
        }
        return $payload;
    }

    private function tokenSecret(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }
        if ($key === '') {
            throw new RuntimeException('APP_KEY must be configured before rewarded signals can be used.');
        }
        return hash('sha256', $key.'|abs-public-rewarded-signal-v1', true);
    }
}
