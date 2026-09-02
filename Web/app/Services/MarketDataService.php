<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketDataService
{
    public const CORE_SYMBOLS = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT', 'ADAUSDT'];

    public function __construct(
        private readonly BinanceMarketService $binance,
        private readonly CoinGeckoMarketService $coingecko,
        private readonly BinanceDerivativesMarketService $derivatives,
        private readonly AlternativeFearGreedService $fearGreed,
        private readonly PublicLiquidationMarketService $liquidations,
        private readonly AlternativeCryptoMarketService $alternativeCrypto,
        private readonly OkxDerivativesMarketService $okxDerivatives,
        private readonly PulseMarketDataService $pulseMarket,
    ) {}

    public function overview(bool $force = false): array
    {
        $key = 'abs.market.overview.v8';
        if ($force) {
            $this->forgetSafely($key);
            $this->forgetSafely('abs.market.stablecoins.v1');
            $this->forgetSafely('abs.market.liquidations.v1');
            $this->forgetSafely('abs.market.liquidations.v2');
            $this->forgetSafely('abs.market.liquidations.v3');
            $this->forgetSafely('abs.market.futures-insights.v1');
            $this->forgetSafely('abs.market.futures-insights.v2');
            $this->forgetSafely('abs.market.futures-insights.v3');
            $this->forgetSafely('abs.market.fear-greed.v1');
            $this->forgetSafely('abs.market.industries.v1');
            $this->forgetSafely('abs.market.industries.v2');
        }

        return $this->rememberSafely(
            $key,
            now()->addSeconds((int) config('services.market.cache_seconds', 60)),
            function (): array {
                $core = $this->unavailableCore();
                $global = $this->unavailableGlobal();
                $sources = [];
                $errors = [];

                try {
                    $central = $this->pulseMarket->latestPrices(self::CORE_SYMBOLS, (int) config('pulse.market_data.read_max_age_seconds', 300));
                    if ($central->count() < count(self::CORE_SYMBOLS)) {
                        throw new \RuntimeException('ABS central market feed does not yet contain all core symbols.');
                    }
                    $core = collect(self::CORE_SYMBOLS)->map(function (string $symbol) use ($central): array {
                        $row = $central->get($symbol);
                        return $this->normalizeCentralTicker($row);
                    })->values()->all();
                    $sources[] = 'ABS Central / Binance Futures';
                } catch (Throwable $e) {
                    $errors[] = 'ABS central market: '.$e->getMessage();
                    Log::warning('ABS central core market data unavailable; using public fallback for website continuity', ['message' => $e->getMessage()]);
                    try {
                        $rows = $this->binance->ticker24(self::CORE_SYMBOLS);
                        $normalized = collect($rows)->map(fn (array $row) => $this->normalizeTicker($row))->keyBy('symbol');
                        $core = collect(self::CORE_SYMBOLS)->map(fn (string $symbol) => $normalized->get($symbol, $this->unavailableTicker($symbol)))->values()->all();
                        $sources[] = 'Binance public fallback';
                    } catch (Throwable $fallback) {
                        $errors[] = 'Binance fallback: '.$fallback->getMessage();
                    }
                }

                try {
                    $cg = $this->coingecko->global();
                    $global = array_replace($global, [
                        'total_market_cap' => $this->nullableFloat(data_get($cg, 'total_market_cap.usd')),
                        'total_volume' => $this->nullableFloat(data_get($cg, 'total_volume.usd')),
                        'btc_dominance' => $this->nullableFloat(data_get($cg, 'market_cap_percentage.btc')),
                        'market_cap_change_24h' => $this->nullableFloat(data_get($cg, 'market_cap_change_percentage_24h_usd')),
                        'active_cryptocurrencies' => $this->nullableInt(data_get($cg, 'active_cryptocurrencies')),
                        'source' => 'CoinGecko',
                    ]);
                    $sources[] = 'CoinGecko';
                } catch (Throwable $e) {
                    $errors[] = 'CoinGecko: '.$e->getMessage();
                    Log::warning('ABS CoinGecko global data unavailable', ['message' => $e->getMessage()]);
                }

                // Alternative.me provides an independent public global-market endpoint.
                // Use it only to fill fields still missing from CoinGecko so a temporary
                // CoinGecko/rate-limit issue does not blank the homepage.
                if (! is_numeric($global['total_market_cap'] ?? null)
                    || ! is_numeric($global['total_volume'] ?? null)
                    || ! is_numeric($global['btc_dominance'] ?? null)) {
                    try {
                        $alt = $this->alternativeCrypto->global();
                        foreach (['total_market_cap','total_volume','btc_dominance','active_cryptocurrencies'] as $field) {
                            if (! is_numeric($global[$field] ?? null) && is_numeric($alt[$field] ?? null)) {
                                $global[$field] = $alt[$field];
                            }
                        }
                        if (collect(['total_market_cap','total_volume','btc_dominance'])->contains(fn ($field) => is_numeric($alt[$field] ?? null))) {
                            $sources[] = 'Alternative.me Global';
                        }
                    } catch (Throwable $e) {
                        $errors[] = 'Alternative global: '.$e->getMessage();
                        Log::warning('ABS Alternative.me global market fallback unavailable', ['message' => $e->getMessage()]);
                    }
                }

                try {
                    $stablecoins = $this->stablecoinSnapshot();
                    $global = array_replace($global, $stablecoins);
                    if (is_numeric($stablecoins['stablecoin_market_cap'] ?? null)) {
                        $sources[] = 'CoinGecko Stablecoins';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Stablecoins: '.$e->getMessage();
                    Log::warning('ABS stablecoin market data unavailable', ['message' => $e->getMessage()]);
                }

                try {
                    $liquidations = $this->liquidationSnapshot();
                    $global = array_replace($global, $liquidations);
                    if (is_numeric($liquidations['liquidation_24h_usd'] ?? null)) {
                        $sources[] = 'Xoomar Liquidations';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Liquidations: '.$e->getMessage();
                    Log::warning('ABS public liquidation data unavailable', ['message' => $e->getMessage()]);
                }

                try {
                    $futures = $this->futuresSnapshot($core);
                    $global = array_replace($global, $futures);
                    if (is_numeric($futures['open_interest_usd'] ?? null)) {
                        $sources[] = (string) ($futures['futures_source'] ?? 'Futures Insights');
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Futures insights: '.$e->getMessage();
                    Log::warning('ABS futures insights unavailable', ['message' => $e->getMessage()]);
                }

                try {
                    $fearGreed = $this->fearGreedSnapshot();
                    $global = array_replace($global, $fearGreed);
                    if (is_numeric($fearGreed['fear_greed_score'] ?? null)) {
                        $sources[] = 'Alternative.me Fear & Greed';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Fear & Greed: '.$e->getMessage();
                    Log::warning('ABS Fear & Greed data unavailable', ['message' => $e->getMessage()]);
                }

                $industries = [];
                try {
                    $industries = $this->industrySnapshot();
                    if ($industries !== []) {
                        $sources[] = 'CoinGecko Categories';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Industries: '.$e->getMessage();
                    Log::warning('ABS crypto industry data unavailable', ['message' => $e->getMessage()]);
                }

                $btc = collect($core)->firstWhere('symbol', 'BTCUSDT');
                $global['btc_dominance_change_24h'] = $this->estimateDominanceChange(
                    $global['btc_dominance'] ?? null,
                    $global['market_cap_change_24h'] ?? null,
                    $btc['change_percent'] ?? null,
                );

                $validChanges = collect($core)
                    ->pluck('change_percent')
                    ->filter(fn ($value) => is_numeric($value));
                $sentiment = $this->momentum($validChanges->all());
                $pulse = $this->marketPulse($core, $global, $sentiment);
                $insights = $this->dailyInsights($core, $global, $sentiment, $pulse, $industries);
                $globalLive = collect($global)->contains(fn ($value, $key) => in_array($key, ['total_market_cap','total_volume','btc_dominance','liquidation_24h_usd','open_interest_usd','fear_greed_score'], true) && is_numeric($value));
                $isLive = $validChanges->isNotEmpty() || $globalLive || $industries !== [];

                return [
                    'core' => $core,
                    'global' => $global,
                    'industries' => $industries,
                    'insights' => $insights,
                    'sentiment' => $sentiment,
                    'pulse' => $pulse,
                    'source' => $sources ? implode(' + ', array_unique($sources)) : 'unavailable',
                    'is_live' => $isLive,
                    'is_fallback' => false,
                    'status' => $isLive ? ($validChanges->isNotEmpty() && $globalLive ? 'live' : 'partial') : 'unavailable',
                    'errors' => app()->isLocal() ? $errors : [],
                    'updated_at' => now()->toIso8601String(),
                ];
            }
        );
    }

    public function cachedOverview(): array
    {
        try {
            $cached = Cache::get('abs.market.overview.v8');
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable $e) {
            Log::debug('ABS market cache read skipped', ['message' => $e->getMessage()]);
        }

        return [
            'core' => $this->unavailableCore(),
            'global' => $this->unavailableGlobal(),
            'industries' => [],
            'insights' => $this->dailyInsights($this->unavailableCore(), $this->unavailableGlobal(), $this->momentum([]), ['score' => null, 'label' => 'Unavailable'], []),
            'sentiment' => $this->momentum([]),
            'pulse' => [
                'score' => null,
                'label' => 'Unavailable',
                'method' => 'ABS Market Pulse combines sentiment, market breadth, BTC momentum and total-market momentum when live data is available.',
            ],
            'source' => 'connecting',
            'is_live' => false,
            'is_fallback' => false,
            'status' => 'connecting',
            'errors' => [],
            'updated_at' => null,
        ];
    }

    public function cachedMovers(int $limit = 5): array
    {
        $limit = min(max($limit, 1), 20);
        try {
            $cached = Cache::get('abs.market.movers.v3.'.$limit);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable $e) {
            Log::debug('ABS movers cache read skipped', ['message' => $e->getMessage()]);
        }

        return [
            'gainers' => [],
            'losers' => [],
            'updated_at' => null,
            'source' => 'connecting',
            'is_live' => false,
            'is_fallback' => false,
            'status' => 'connecting',
        ];
    }

    public function movers(int $limit = 5, bool $force = false): array
    {
        $limit = min(max($limit, 1), 20);
        $key = 'abs.market.movers.v3.'.$limit;
        if ($force) {
            $this->forgetSafely($key);
        }

        return $this->rememberSafely($key, now()->addSeconds(90), function () use ($limit): array {
            $errors = [];
            try {
                $rows = \App\Models\PulseMarketPrice::query()
                    ->where('observed_at', '>=', now()->subSeconds((int) config('pulse.market_data.read_max_age_seconds', 300)))
                    ->where('volume_24h', '>=', 5000000)
                    ->get()
                    ->map(fn ($row) => $this->normalizeCentralTicker($row))
                    ->reject(fn (array $row) => $this->isExcludedMoverSymbol((string) ($row['symbol'] ?? '')))
                    ->filter(fn (array $row) => is_numeric($row['price']) && is_numeric($row['change_percent']));
                $gainers = $rows->filter(fn (array $row) => (float) $row['change_percent'] > 0)->sortByDesc('change_percent')->take($limit)->values()->all();
                $losers = $rows->filter(fn (array $row) => (float) $row['change_percent'] < 0)->sortBy('change_percent')->take($limit)->values()->all();
                if ($gainers !== [] && $losers !== []) {
                    return [
                        'gainers' => $gainers,
                        'losers' => $losers,
                        'updated_at' => now()->toIso8601String(),
                        'source' => 'ABS Central / Binance Futures',
                        'is_live' => true,
                        'is_fallback' => false,
                        'status' => 'live',
                    ];
                }
                throw new \RuntimeException('ABS central market feed does not yet contain enough liquid movers.');
            } catch (Throwable $e) {
                $errors[] = 'ABS central market: '.$e->getMessage();
                Log::warning('ABS central movers unavailable; trying public fallback', ['message' => $e->getMessage()]);
            }

            try {
                $rows = collect($this->coingecko->markets(100))
                    ->filter(fn (array $row) => is_numeric($row['current_price'] ?? null) && is_numeric($row['price_change_percentage_24h'] ?? null))
                    ->filter(fn (array $row) => (float) ($row['total_volume'] ?? 0) >= 5_000_000)
                    ->map(function (array $row): array {
                        $base = strtoupper((string) ($row['symbol'] ?? ''));
                        return [
                            'symbol' => $base.'USDT',
                            'base' => $base,
                            'pair' => $base.'/USDT',
                            'price' => $this->nullableFloat($row['current_price'] ?? null),
                            'change_percent' => $this->nullableFloat($row['price_change_percentage_24h'] ?? null),
                            'volume' => $this->nullableFloat($row['total_volume'] ?? null),
                            'high' => $this->nullableFloat($row['high_24h'] ?? null),
                            'low' => $this->nullableFloat($row['low_24h'] ?? null),
                        ];
                    });
                $gainers = $rows->filter(fn (array $row) => (float) $row['change_percent'] > 0)->sortByDesc('change_percent')->take($limit)->values()->all();
                $losers = $rows->filter(fn (array $row) => (float) $row['change_percent'] < 0)->sortBy('change_percent')->take($limit)->values()->all();
                if ($gainers !== [] && $losers !== []) {
                    return [
                        'gainers' => $gainers,
                        'losers' => $losers,
                        'updated_at' => now()->toIso8601String(),
                        'source' => 'CoinGecko',
                        'is_live' => true,
                        'is_fallback' => true,
                        'status' => 'live',
                    ];
                }
                throw new \RuntimeException('CoinGecko movers response did not contain both gainers and losers.');
            } catch (Throwable $e) {
                $errors[] = 'CoinGecko: '.$e->getMessage();
                Log::warning('ABS market movers unavailable', ['message' => $e->getMessage()]);
            }

            return [
                'gainers' => [],
                'losers' => [],
                'updated_at' => now()->toIso8601String(),
                'source' => 'unavailable',
                'is_live' => false,
                'is_fallback' => false,
                'status' => 'unavailable',
                'errors' => app()->isLocal() ? $errors : [],
            ];
        });
    }

    public function chart(string $symbol = 'BTCUSDT', string $interval = '1h', int $limit = 80, bool $force = false): array
    {
        $allowed = self::CORE_SYMBOLS;
        $symbol = in_array(strtoupper($symbol), $allowed, true) ? strtoupper($symbol) : 'BTCUSDT';
        $interval = in_array($interval, ['5m', '15m', '1h', '4h', '1d'], true) ? $interval : '1h';
        $limit = min(max($limit, 10), 500);

        $key = "abs.market.chart.v2.$symbol.$interval.$limit";
        if ($force) {
            $this->forgetSafely($key);
        }

        return $this->rememberSafely($key, now()->addSeconds(60), function () use ($symbol, $interval, $limit): array {
            try {
                return [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'candles' => $this->binance->klines($symbol, $interval, $limit),
                    'source' => 'Binance',
                    'is_live' => true,
                    'is_fallback' => false,
                    'status' => 'live',
                    'updated_at' => now()->toIso8601String(),
                ];
            } catch (Throwable $e) {
                Log::warning('ABS market chart unavailable', ['message' => $e->getMessage()]);

                return [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'candles' => [],
                    'source' => 'unavailable',
                    'is_live' => false,
                    'is_fallback' => false,
                    'status' => 'unavailable',
                    'errors' => app()->isLocal() ? [$e->getMessage()] : [],
                    'updated_at' => now()->toIso8601String(),
                ];
            }
        });
    }

    private function stablecoinSnapshot(): array
    {
        $key = 'abs.market.stablecoins.v1';

        return $this->rememberSafely($key, now()->addMinutes(5), function (): array {
            $rows = collect($this->coingecko->stablecoins(100))
                ->filter(fn (array $row) => is_numeric($row['market_cap'] ?? null));

            $current = (float) $rows->sum(fn (array $row) => (float) ($row['market_cap'] ?? 0));
            if ($current <= 0) {
                throw new \RuntimeException('CoinGecko stablecoin category returned no market-cap rows.');
            }

            $previous = (float) $rows->sum(function (array $row): float {
                $cap = (float) ($row['market_cap'] ?? 0);
                $change = $this->nullableFloat($row['market_cap_change_percentage_24h'] ?? null);
                if ($cap <= 0 || $change === null || $change <= -99.99) {
                    return $cap;
                }

                return $cap / (1 + ($change / 100));
            });

            $change = $previous > 0 ? (($current - $previous) / $previous) * 100 : null;

            return [
                'stablecoin_market_cap' => $current,
                'stablecoin_market_cap_change_24h' => $change,
                'stablecoin_source' => 'CoinGecko stablecoin category',
            ];
        });
    }

    private function liquidationSnapshot(): array
    {
        return $this->rememberSafely('abs.market.liquidations.v4', now()->addSeconds(90), function (): array {
            $snapshot = $this->liquidations->summary24h();
            $total = $this->nullableFloat($snapshot['total_usd'] ?? null);
            $long = $this->nullableFloat($snapshot['long_usd'] ?? null);
            $short = $this->nullableFloat($snapshot['short_usd'] ?? null);

            if ($total === null || $long === null || $short === null) {
                throw new \RuntimeException('Public liquidation snapshot is incomplete.');
            }

            $calculatedTotal = $long + $short;
            if ($total <= 0 && $calculatedTotal > 0) {
                $total = $calculatedTotal;
            }

            $shareBase = $calculatedTotal > 0 ? $calculatedTotal : $total;

            return [
                'liquidation_24h_usd' => $total,
                'liquidation_long_24h_usd' => $long,
                'liquidation_short_24h_usd' => $short,
                'liquidation_long_share_24h' => $shareBase > 0 ? ($long / $shareBase) * 100 : null,
                'liquidation_short_share_24h' => $shareBase > 0 ? ($short / $shareBase) * 100 : null,
                'liquidation_max_single_24h_usd' => $this->nullableFloat($snapshot['max_single_usd'] ?? null),
                'liquidation_updated_at' => $snapshot['updated_at'] ?? null,
                'liquidation_source' => $snapshot['source'] ?? 'Xoomar aggregated liquidations',
            ];
        });
    }

    private function futuresSnapshot(array $core): array
    {
        return $this->rememberSafely('abs.market.futures-insights.v3', now()->addMinutes(2), function () use ($core): array {
            $result = [
                'open_interest_usd' => null,
                'funding_rate' => null,
                'long_short_ratio' => null,
                'perp_premium_basis' => null,
                'futures_mark_price' => null,
                'futures_index_price' => null,
                'futures_source' => 'unavailable',
            ];
            $sources = [];

            try {
                $open = $this->derivatives->openInterest('BTCUSDT');
                $premium = $this->derivatives->premiumIndex('BTCUSDT');
                $ratio = [];
                try {
                    $ratio = $this->derivatives->globalLongShortAccountRatio('BTCUSDT', '5m');
                } catch (Throwable $e) {
                    Log::warning('ABS Binance Futures long/short ratio unavailable', ['message' => $e->getMessage()]);
                }

                $openQty = $this->nullableFloat($open['openInterest'] ?? null);
                $markPrice = $this->nullableFloat($premium['markPrice'] ?? null)
                    ?? $this->nullableFloat(collect($core)->firstWhere('symbol', 'BTCUSDT')['price'] ?? null);
                $indexPrice = $this->nullableFloat($premium['indexPrice'] ?? null);
                $funding = $this->nullableFloat($premium['lastFundingRate'] ?? null);
                $longShortRatio = $this->nullableFloat($ratio['longShortRatio'] ?? null);
                if ($longShortRatio !== null && ($longShortRatio <= 0 || $longShortRatio > 100)) $longShortRatio = null;
                $perpPremiumBasis = ($markPrice !== null && $indexPrice !== null && $indexPrice != 0.0)
                    ? (($markPrice - $indexPrice) / $indexPrice) * 100
                    : null;

                $result = array_replace($result, [
                    'open_interest_usd' => ($openQty !== null && $markPrice !== null) ? abs($openQty * $markPrice) : null,
                    'funding_rate' => $funding !== null ? $funding * 100 : null,
                    'long_short_ratio' => $longShortRatio,
                    'perp_premium_basis' => $perpPremiumBasis,
                    'futures_mark_price' => $markPrice,
                    'futures_index_price' => $indexPrice,
                ]);
                $sources[] = 'Binance Futures';
            } catch (Throwable $e) {
                Log::warning('ABS Binance Futures public data unavailable; trying OKX', ['message' => $e->getMessage()]);
            }

            $missing = collect(['open_interest_usd','funding_rate','long_short_ratio','perp_premium_basis'])
                ->contains(fn (string $field) => ! is_numeric($result[$field] ?? null));
            if ($missing) {
                try {
                    $okx = $this->okxDerivatives->snapshot();
                    foreach (['open_interest_usd','funding_rate','long_short_ratio','perp_premium_basis','futures_mark_price'] as $field) {
                        if (! is_numeric($result[$field] ?? null) && is_numeric($okx[$field] ?? null)) {
                            $result[$field] = $okx[$field];
                        }
                    }
                    $sources[] = 'OKX';
                    if (is_numeric($result['long_short_ratio'] ?? null) && ((float) $result['long_short_ratio'] <= 0 || (float) $result['long_short_ratio'] > 100)) {
                        $result['long_short_ratio'] = null;
                    }
                } catch (Throwable $e) {
                    Log::warning('ABS OKX derivatives fallback unavailable', ['message' => $e->getMessage()]);
                }
            }

            $result['futures_source'] = $sources ? implode(' + ', array_unique($sources)) : 'unavailable';
            return $result;
        });
    }

    private function fearGreedSnapshot(): array
    {
        return $this->rememberSafely('abs.market.fear-greed.v1', now()->addMinutes(10), function (): array {
            $row = $this->fearGreed->latest();
            return [
                'fear_greed_score' => $row['score'] ?? null,
                'fear_greed_label' => $row['label'] ?? 'Unavailable',
                'fear_greed_source' => $row['source'] ?? 'Alternative.me',
            ];
        });
    }

    private function industrySnapshot(): array
    {
        return $this->rememberSafely('abs.market.industries.v2', now()->addMinutes(10), function (): array {
            $rows = collect($this->coingecko->categories())
                ->filter(fn ($row) => is_array($row) && is_numeric($row['market_cap'] ?? null));

            $definitions = [
                ['key' => 'defi', 'label' => 'DeFi', 'patterns' => ['decentralized-finance-defi', 'defi']],
                ['key' => 'layer1', 'label' => 'Layer 1', 'patterns' => ['layer-1', 'layer 1']],
                ['key' => 'infrastructure', 'label' => 'Infrastructure', 'patterns' => ['infrastructure', 'blockchain-infrastructure']],
                ['key' => 'gaming', 'label' => 'Gaming', 'patterns' => ['gaming']],
                ['key' => 'ai', 'label' => 'AI & Big Data', 'patterns' => ['artificial-intelligence', 'ai big data', 'artificial intelligence']],
                ['key' => 'nft', 'label' => 'NFT', 'patterns' => ['non-fungible-tokens-nft', 'nft']],
                ['key' => 'payments', 'label' => 'Payments', 'patterns' => ['payments', 'payment-solutions']],
                ['key' => 'metaverse', 'label' => 'Metaverse', 'patterns' => ['metaverse']],
            ];

            $result = [];
            foreach ($definitions as $definition) {
                $row = $rows->first(function (array $row) use ($definition): bool {
                    $haystack = strtolower(trim((string) ($row['id'] ?? '')).' '.trim((string) ($row['name'] ?? '')));
                    return collect($definition['patterns'])->contains(fn (string $pattern) => str_contains($haystack, strtolower($pattern)));
                });
                if (! $row) {
                    continue;
                }
                $result[] = [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'market_cap' => $this->nullableFloat($row['market_cap'] ?? null),
                    'change_24h' => $this->nullableFloat($row['market_cap_change_24h'] ?? $row['market_cap_change_24h_percentage'] ?? null),
                    'source' => 'CoinGecko',
                ];
            }

            return $result;
        });
    }

    private function dailyInsights(array $core, array $global, array $sentiment, array $pulse, array $industries): array
    {
        $changes = collect($core)->pluck('change_percent')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value);
        $ranges = collect($core)->map(function (array $coin): ?float {
            $high = $this->nullableFloat($coin['high'] ?? null);
            $low = $this->nullableFloat($coin['low'] ?? null);
            $price = $this->nullableFloat($coin['price'] ?? null);
            if ($high === null || $low === null || $price === null || $price <= 0) return null;
            return (($high - $low) / $price) * 100;
        })->filter(fn ($value) => is_numeric($value));

        $volatilityPct = $ranges->isNotEmpty() ? (float) $ranges->avg() : null;
        $volatilityScore = $volatilityPct !== null ? min(100.0, max(0.0, $volatilityPct * 12.5)) : null;
        $volatilityLabel = $volatilityScore === null ? 'Unavailable' : ($volatilityScore >= 70 ? 'Elevated' : ($volatilityScore >= 35 ? 'Moderate' : 'Calm'));

        $pulseScore = $this->nullableFloat($pulse['score'] ?? null);
        $marketBias = $pulseScore === null ? 'Unavailable' : ($pulseScore >= 65 ? 'Bullish' : ($pulseScore <= 35 ? 'Defensive' : 'Balanced'));
        $breadthPositive = $changes->isNotEmpty() ? $changes->filter(fn ($value) => $value > 0)->count() / $changes->count() : null;

        $stablecoinCap = $this->nullableFloat($global['stablecoin_market_cap'] ?? null);
        $stablecoinChange = $this->nullableFloat($global['stablecoin_market_cap_change_24h'] ?? null);
        $stablecoinFlow = null;
        if ($stablecoinCap !== null && $stablecoinChange !== null && $stablecoinChange > -99.99) {
            $previous = $stablecoinCap / (1 + ($stablecoinChange / 100));
            $stablecoinFlow = $stablecoinCap - $previous;
        }

        $industry = collect($industries)
            ->filter(fn ($row) => is_numeric($row['change_24h'] ?? null))
            ->sortByDesc('change_24h')
            ->first();
        $keyTrend = $industry['label'] ?? ($breadthPositive !== null && $breadthPositive >= .67 ? 'Broad Risk-On' : ($breadthPositive !== null && $breadthPositive <= .33 ? 'Risk-Off' : 'Mixed Rotation'));
        $keyTrendDetail = $industry ? 'Leading industry today' : 'Derived from core-asset breadth';

        $daily = 'Live market context is still syncing.';
        if ($pulseScore !== null) {
            if ($pulseScore >= 65) {
                $daily = $stablecoinFlow !== null && $stablecoinFlow > 0
                    ? 'Risk appetite is constructive while stablecoin liquidity is expanding.'
                    : 'Momentum remains constructive; watch breadth and futures positioning for confirmation.';
            } elseif ($pulseScore <= 35) {
                $daily = 'Defensive conditions dominate; watch volatility, liquidations and support levels closely.';
            } else {
                $daily = 'Market conditions are balanced; selective sector rotation is more important than broad direction.';
            }
        }

        return [
            'market_bias' => $marketBias,
            'market_bias_detail' => $breadthPositive === null ? 'Waiting for breadth data' : round($breadthPositive * 100).'% of core assets positive',
            'stablecoin_flow_24h_usd' => $stablecoinFlow,
            'volatility_score' => $volatilityScore,
            'volatility_label' => $volatilityLabel,
            'key_trend' => $keyTrend,
            'key_trend_detail' => $keyTrendDetail,
            'daily_insight' => $daily,
        ];
    }

    private function estimateDominanceChange(mixed $dominance, mixed $marketChange, mixed $btcChange): ?float
    {
        if (! is_numeric($dominance) || ! is_numeric($marketChange) || ! is_numeric($btcChange)) {
            return null;
        }

        $current = (float) $dominance;
        $marketFactor = 1 + ((float) $marketChange / 100);
        $btcFactor = 1 + ((float) $btcChange / 100);
        if ($current <= 0 || $marketFactor <= 0 || $btcFactor <= 0) {
            return null;
        }

        $previous = $current * ($marketFactor / $btcFactor);
        return $previous > 0 ? (($current - $previous) / $previous) * 100 : null;
    }

    private function marketPulse(array $core, array $global, array $sentiment): array
    {
        $changes = collect($core)
            ->pluck('change_percent')
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value)
            ->values();

        if ($changes->isEmpty() && ! is_numeric($sentiment['score'] ?? null)) {
            return [
                'score' => null,
                'label' => 'Unavailable',
                'method' => 'ABS Market Pulse combines sentiment, market breadth, BTC momentum and total-market momentum when live data is available.',
            ];
        }

        $sentimentScore = is_numeric($sentiment['score'] ?? null) ? (float) $sentiment['score'] : 50.0;
        $breadthScore = $changes->isNotEmpty()
            ? ($changes->filter(fn ($change) => $change > 0)->count() / $changes->count()) * 100
            : 50.0;

        $btcChange = collect($core)->firstWhere('symbol', 'BTCUSDT')['change_percent'] ?? null;
        $btcMomentum = is_numeric($btcChange)
            ? min(100.0, max(0.0, 50.0 + ((float) $btcChange * 5.0)))
            : 50.0;

        $marketChange = $global['market_cap_change_24h'] ?? null;
        $marketMomentum = is_numeric($marketChange)
            ? min(100.0, max(0.0, 50.0 + ((float) $marketChange * 7.0)))
            : 50.0;

        $score = (int) round(
            ($sentimentScore * 0.35) +
            ($breadthScore * 0.30) +
            ($marketMomentum * 0.20) +
            ($btcMomentum * 0.15)
        );
        $score = min(95, max(5, $score));

        return [
            'score' => $score,
            'label' => $score >= 65 ? 'Bullish' : ($score <= 35 ? 'Defensive' : 'Neutral'),
            'method' => 'ABS Market Pulse is a composite score using market sentiment, positive-market breadth, BTC 24-hour momentum and total-market-cap momentum. It is intentionally separate from Market Sentiment.',
        ];
    }

    private function momentum(array $changes): array
    {
        if ($changes === []) {
            return [
                'score' => null,
                'label' => 'Unavailable',
                'bullish' => null,
                'neutral' => null,
                'bearish' => null,
                'method' => 'ABS market momentum is calculated from the selected assets when live 24-hour changes are available.',
            ];
        }

        $averageChange = collect($changes)->avg() ?? 0;
        $score = (int) round(min(95, max(5, 50 + ($averageChange * 4))));

        if ($score >= 50) {
            $bullish = $score;
            $bearish = (int) round((100 - $score) * 0.35);
            $neutral = max(0, 100 - $bullish - $bearish);
        } else {
            $bearish = 100 - $score;
            $bullish = (int) round($score * 0.35);
            $neutral = max(0, 100 - $bullish - $bearish);
        }

        return [
            'score' => $score,
            'label' => $score >= 65 ? 'Bullish' : ($score <= 35 ? 'Defensive' : 'Neutral'),
            'bullish' => $bullish,
            'neutral' => $neutral,
            'bearish' => $bearish,
            'method' => 'ABS market momentum derived from the average 24-hour change of BTC, ETH, SOL, BNB, XRP and ADA. It is not a third-party Fear & Greed index.',
        ];
    }

    private function forgetSafely(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::warning('ABS market cache clear skipped', ['key' => $key, 'message' => $e->getMessage()]);
        }
    }

    private function rememberSafely(string $key, mixed $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (Throwable $e) {
            Log::warning('ABS cache unavailable; uncached response used', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            return $callback();
        }
    }

    private function normalizeCentralTicker(object $row): array
    {
        $symbol = strtoupper((string) ($row->symbol ?? ''));
        $base = str_ends_with($symbol, 'USDT') ? substr($symbol, 0, -4) : $symbol;
        return [
            'symbol' => $symbol,
            'base' => $base,
            'pair' => $base.'/USDT',
            'price' => $this->nullableFloat($row->price ?? null),
            'change_percent' => $this->nullableFloat($row->change_percent_24h ?? null),
            'volume' => $this->nullableFloat($row->volume_24h ?? null),
            'high' => $this->nullableFloat($row->high_24h ?? null),
            'low' => $this->nullableFloat($row->low_24h ?? null),
        ];
    }

    private function normalizeTicker(array $row): array
    {
        $symbol = (string) ($row['symbol'] ?? '');
        $base = str_ends_with($symbol, 'USDT') ? substr($symbol, 0, -4) : $symbol;

        return [
            'symbol' => $symbol,
            'base' => $base,
            'pair' => $base.'/USDT',
            'price' => $this->nullableFloat($row['lastPrice'] ?? null),
            'change_percent' => $this->nullableFloat($row['priceChangePercent'] ?? null),
            'volume' => $this->nullableFloat($row['quoteVolume'] ?? null),
            'high' => $this->nullableFloat($row['highPrice'] ?? null),
            'low' => $this->nullableFloat($row['lowPrice'] ?? null),
        ];
    }

    private function unavailableCore(): array
    {
        return collect(self::CORE_SYMBOLS)
            ->map(fn (string $symbol) => $this->unavailableTicker($symbol))
            ->all();
    }

    private function unavailableTicker(string $symbol): array
    {
        $base = str_ends_with($symbol, 'USDT') ? substr($symbol, 0, -4) : $symbol;

        return [
            'symbol' => $symbol,
            'base' => $base,
            'pair' => $base.'/USDT',
            'price' => null,
            'change_percent' => null,
            'volume' => null,
            'high' => null,
            'low' => null,
        ];
    }

    private function unavailableGlobal(): array
    {
        return [
            'total_market_cap' => null,
            'total_volume' => null,
            'btc_dominance' => null,
            'btc_dominance_change_24h' => null,
            'market_cap_change_24h' => null,
            'stablecoin_market_cap' => null,
            'stablecoin_market_cap_change_24h' => null,
            'stablecoin_source' => 'unavailable',
            'liquidation_24h_usd' => null,
            'liquidation_long_24h_usd' => null,
            'liquidation_short_24h_usd' => null,
            'liquidation_long_share_24h' => null,
            'liquidation_short_share_24h' => null,
            'liquidation_max_single_24h_usd' => null,
            'liquidation_updated_at' => null,
            'liquidation_source' => 'unavailable',
            'open_interest_usd' => null,
            'funding_rate' => null,
            'long_short_ratio' => null,
            'perp_premium_basis' => null,
            'futures_mark_price' => null,
            'futures_index_price' => null,
            'futures_source' => 'unavailable',
            'fear_greed_score' => null,
            'fear_greed_label' => 'Unavailable',
            'fear_greed_source' => 'unavailable',
            'active_cryptocurrencies' => null,
            'source' => 'unavailable',
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function isExcludedMoverSymbol(string $symbol): bool
    {
        $excludedSuffixes = ['UPUSDT', 'DOWNUSDT', 'BULLUSDT', 'BEARUSDT'];
        $excludedBases = ['USDCUSDT', 'FDUSDUSDT', 'TUSDUSDT', 'USDPUSDT', 'DAIUSDT', 'EURUSDT'];

        return in_array($symbol, $excludedBases, true)
            || collect($excludedSuffixes)->contains(fn (string $suffix) => str_ends_with($symbol, $suffix));
    }
}
