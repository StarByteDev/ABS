<?php

namespace App\Services;

use App\Models\BinanceConnection;
use App\Models\PulsePair;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BinanceFuturesService
{
    public function publicPing(string $environment = 'live'): bool
    {
        $this->request('GET', '/fapi/v1/ping', [], null, $environment);
        return true;
    }

    public function publicKlines(string $symbol, string $interval = '15m', int $limit = 120, string $environment = 'live'): array
    {
        return $this->request('GET', '/fapi/v1/klines', [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => max(20, min($limit, 1500)),
        ], null, $environment);
    }

    /**
     * Fetch public candle history for several symbols concurrently.
     *
     * The caller controls the batch size so a large package can be evaluated
     * without issuing hundreds of serial HTTP requests or opening an
     * unbounded number of sockets at once.
     *
     * @return array<string,array{data:?array,error:?string}>
     */
    public function publicKlinesBatch(array $symbols, string $interval = '15m', int $limit = 120, string $environment = 'live'): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(
            fn ($symbol) => strtoupper(trim((string) $symbol)),
            $symbols,
        ))));
        if ($symbols === []) return [];

        $limit = max(20, min($limit, 1500));
        $url = $this->baseUrl($environment).'/fapi/v1/klines';
        $timeout = max(20, (int) config('pulse.binance.timeout', 30));
        $connectTimeout = max(7, (int) config('pulse.binance.connect_timeout', 8));
        $verify = (bool) config('pulse.binance.ssl_verify', true);

        try {
            $responses = Http::pool(function ($pool) use ($symbols, $interval, $limit, $url, $timeout, $connectTimeout, $verify) {
                $requests = [];
                foreach ($symbols as $symbol) {
                    $requests[] = $pool->as($symbol)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->connectTimeout($connectTimeout)
                        ->withOptions(['verify' => $verify])
                        ->get($url, ['symbol' => $symbol, 'interval' => $interval, 'limit' => $limit]);
                }
                return $requests;
            });
        } catch (\Throwable $e) {
            return collect($symbols)->mapWithKeys(fn ($symbol) => [$symbol => [
                'data' => null,
                'error' => $e->getMessage(),
            ]])->all();
        }

        $payloads = [];
        foreach ($symbols as $symbol) {
            $response = $responses[$symbol] ?? null;
            if (! is_object($response) || ! method_exists($response, 'successful')) {
                $payloads[$symbol] = ['data' => null, 'error' => $response instanceof \Throwable ? $response->getMessage() : 'Binance candle request failed.'];
                continue;
            }
            if (! $response->successful()) {
                $payloads[$symbol] = ['data' => null, 'error' => (string) ($response->json('msg') ?: 'Binance candle request returned HTTP '.$response->status().'.')];
                continue;
            }
            $decoded = $response->json();
            $payloads[$symbol] = is_array($decoded)
                ? ['data' => $decoded, 'error' => null]
                : ['data' => null, 'error' => 'Binance returned an unexpected candle response.'];
        }

        return $payloads;
    }

    public function publicTickers(string $environment = 'live'): array
    {
        return $this->request('GET', '/fapi/v1/ticker/24hr', [], null, $environment);
    }

    public function publicTicker(string $symbol, string $environment = 'live'): array
    {
        return $this->request('GET', '/fapi/v1/ticker/24hr', ['symbol' => strtoupper($symbol)], null, $environment);
    }

    public function exchangeInfo(string $environment = 'live'): array
    {
        return $this->request('GET', '/fapi/v1/exchangeInfo', [], null, $environment);
    }

    public function serverTime(string $environment = 'live'): int
    {
        $payload = $this->request('GET', '/fapi/v1/time', [], null, $environment);
        return (int) ($payload['serverTime'] ?? floor(microtime(true) * 1000));
    }

    public function testConnection(BinanceConnection $connection): array
    {
        $account = $this->account($connection);
        $configuration = $this->accountConfiguration($connection);

        return [
            'connected' => true,
            'environment' => $connection->environment,
            'can_trade' => (bool) ($configuration['canTrade'] ?? false),
            'total_wallet_balance' => (float) ($account['totalWalletBalance'] ?? 0),
            'available_balance' => (float) ($account['availableBalance'] ?? 0),
            'assets' => collect($account['assets'] ?? [])
                ->filter(fn (array $asset): bool => abs((float) ($asset['walletBalance'] ?? 0)) > 0)
                ->values()
                ->all(),
        ];
    }

    public function account(BinanceConnection $connection): array
    {
        return $this->signed('GET', '/fapi/v3/account', [], $connection);
    }

    public function accountConfiguration(BinanceConnection $connection): array
    {
        return $this->signed('GET', '/fapi/v1/accountConfig', [], $connection);
    }

    public function openOrders(BinanceConnection $connection, ?string $symbol = null): array
    {
        return $this->signed('GET', '/fapi/v1/openOrders', array_filter([
            'symbol' => $symbol ? strtoupper($symbol) : null,
        ]), $connection);
    }

    public function openAlgoOrders(BinanceConnection $connection, ?string $symbol = null): array
    {
        return $this->signed('GET', '/fapi/v1/openAlgoOrders', array_filter([
            'algoType' => 'CONDITIONAL',
            'symbol' => $symbol ? strtoupper($symbol) : null,
        ]), $connection);
    }

    public function positionRisk(BinanceConnection $connection, ?string $symbol = null): array
    {
        return $this->signed('GET', '/fapi/v3/positionRisk', array_filter([
            'symbol' => $symbol ? strtoupper($symbol) : null,
        ]), $connection);
    }

    public function userTrades(
        BinanceConnection $connection,
        string $symbol,
        ?int $startTime = null,
        int $limit = 1000,
        string|int|null $orderId = null,
    ): array
    {
        return $this->signed('GET', '/fapi/v1/userTrades', array_filter([
            'symbol' => strtoupper($symbol),
            'startTime' => $startTime,
            'orderId' => $orderId,
            'limit' => max(1, min($limit, 1000)),
        ]), $connection);
    }

    public function queryOrder(BinanceConnection $connection, string $symbol, string|int $orderId): array
    {
        return $this->signed('GET', '/fapi/v1/order', [
            'symbol' => strtoupper($symbol),
            'orderId' => $orderId,
        ], $connection);
    }

    public function queryAlgoOrder(BinanceConnection $connection, string|int $algoId): array
    {
        return $this->signed('GET', '/fapi/v1/algoOrder', ['algoId' => $algoId], $connection);
    }

    public function changeLeverage(BinanceConnection $connection, string $symbol, int $leverage): array
    {
        return $this->signed('POST', '/fapi/v1/leverage', [
            'symbol' => strtoupper($symbol),
            'leverage' => max(1, min($leverage, (int) config('pulse.risk.max_leverage', 20))),
        ], $connection);
    }

    public function changeMarginType(BinanceConnection $connection, string $symbol, string $marginType): array
    {
        $marginType = strtoupper($marginType);
        if (! in_array($marginType, ['ISOLATED', 'CROSSED'], true)) {
            throw new RuntimeException('Margin type must be ISOLATED or CROSSED.');
        }

        try {
            return $this->signed('POST', '/fapi/v1/marginType', [
                'symbol' => strtoupper($symbol),
                'marginType' => $marginType,
            ], $connection);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '-4046') || str_contains(strtolower($e->getMessage()), 'no need to change')) {
                return ['code' => 200, 'msg' => 'Margin type already configured.'];
            }
            throw $e;
        }
    }

    public function placeOrder(BinanceConnection $connection, array $parameters): array
    {
        $parameters['symbol'] = strtoupper((string) ($parameters['symbol'] ?? ''));
        $parameters['side'] = strtoupper((string) ($parameters['side'] ?? ''));
        $parameters['type'] = strtoupper((string) ($parameters['type'] ?? 'MARKET'));

        if ($parameters['symbol'] === '' || ! in_array($parameters['side'], ['BUY', 'SELL'], true)) {
            throw new RuntimeException('A valid symbol and BUY/SELL side are required.');
        }

        // Binance validates quantity/price against live symbol filters. Normalize immediately
        // before signing so a stale database precision cannot create -1111 BAD_PRECISION.
        $parameters = $this->normalizeLiveOrderParameters($connection->environment, $parameters);

        try {
            return $this->signed('POST', '/fapi/v1/order', $parameters, $connection);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $precisionRejected = str_contains($message, 'Binance -1111:')
                || str_contains($message, 'Binance -4023:')
                || str_contains($message, 'Binance -4014:')
                || str_contains($message, 'Binance -4029:')
                || str_contains($message, 'Binance -4030:');

            if (! $precisionRejected) throw $e;

            // These errors mean Binance rejected the request before order acceptance, so one
            // safe retry with freshly downloaded exchange filters cannot duplicate a trade.
            $parameters = $this->normalizeLiveOrderParameters($connection->environment, $parameters, true);
            return $this->signed('POST', '/fapi/v1/order', $parameters, $connection);
        }
    }

    public function placeAlgoOrder(BinanceConnection $connection, array $parameters): array
    {
        $parameters['algoType'] = 'CONDITIONAL';
        $parameters['symbol'] = strtoupper((string) ($parameters['symbol'] ?? ''));
        $parameters['side'] = strtoupper((string) ($parameters['side'] ?? ''));
        $parameters['type'] = strtoupper((string) ($parameters['type'] ?? ''));

        if ($parameters['symbol'] === ''
            || ! in_array($parameters['side'], ['BUY', 'SELL'], true)
            || ! in_array($parameters['type'], ['STOP_MARKET', 'TAKE_PROFIT_MARKET', 'STOP', 'TAKE_PROFIT', 'TRAILING_STOP_MARKET'], true)) {
            throw new RuntimeException('A valid conditional algo order is required.');
        }

        $parameters = $this->normalizeLiveOrderParameters($connection->environment, $parameters);
        try {
            return $this->signed('POST', '/fapi/v1/algoOrder', $parameters, $connection);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if (! str_contains($message, 'Binance -1111:') && ! str_contains($message, 'Binance -4014:') && ! str_contains($message, 'Binance -4029:')) throw $e;
            $parameters = $this->normalizeLiveOrderParameters($connection->environment, $parameters, true);
            return $this->signed('POST', '/fapi/v1/algoOrder', $parameters, $connection);
        }
    }

    public function cancelOrder(BinanceConnection $connection, string $symbol, string|int $orderId): array
    {
        return $this->signed('DELETE', '/fapi/v1/order', [
            'symbol' => strtoupper($symbol),
            'orderId' => $orderId,
        ], $connection);
    }

    public function cancelAlgoOrder(BinanceConnection $connection, string|int $algoId): array
    {
        return $this->signed('DELETE', '/fapi/v1/algoOrder', ['algoId' => $algoId], $connection);
    }

    public function cancelAllOpenOrders(BinanceConnection $connection, string $symbol): array
    {
        return $this->signed('DELETE', '/fapi/v1/allOpenOrders', ['symbol' => strtoupper($symbol)], $connection);
    }

    public function cancelAllAlgoOpenOrders(BinanceConnection $connection, string $symbol): array
    {
        return $this->signed('DELETE', '/fapi/v1/algoOpenOrders', ['symbol' => strtoupper($symbol)], $connection);
    }

    public function placeProtectionOrders(
        BinanceConnection $connection,
        string $symbol,
        string $positionSide,
        string $entrySide,
        float $takeProfit,
        float $stopLoss,
    ): array {
        $closeSide = strtoupper($entrySide) === 'BUY' ? 'SELL' : 'BUY';
        $base = [
            'symbol' => strtoupper($symbol),
            'side' => $closeSide,
            'positionSide' => strtoupper($positionSide),
            'closePosition' => 'true',
            'workingType' => 'MARK_PRICE',
            'priceProtect' => config('pulse.binance.price_protect', false) ? 'true' : 'false',
        ];

        $tp = $this->placeAlgoOrder($connection, $base + [
            'type' => 'TAKE_PROFIT_MARKET',
            'triggerPrice' => $this->formatNumber($takeProfit),
        ]);

        try {
            $sl = $this->placeAlgoOrder($connection, $base + [
                'type' => 'STOP_MARKET',
                'triggerPrice' => $this->formatNumber($stopLoss),
            ]);
        } catch (\Throwable $e) {
            if (isset($tp['algoId'])) {
                try {
                    $this->cancelAlgoOrder($connection, $tp['algoId']);
                } catch (\Throwable) {
                    // Preserve the original protection failure.
                }
            }
            throw $e;
        }

        return ['take_profit' => $tp, 'stop_loss' => $sl];
    }

    public function closePosition(
        BinanceConnection $connection,
        string $symbol,
        string $entrySide,
        float $quantity,
        string $positionSide = 'BOTH',
    ): array {
        $parameters = [
            'symbol' => strtoupper($symbol),
            'side' => strtoupper($entrySide) === 'BUY' ? 'SELL' : 'BUY',
            'type' => 'MARKET',
            'quantity' => $this->formatNumber(abs($quantity)),
            'positionSide' => strtoupper($positionSide),
            'newOrderRespType' => 'RESULT',
        ];
        if (strtoupper($positionSide) === 'BOTH') {
            $parameters['reduceOnly'] = 'true';
        }

        return $this->placeOrder($connection, $parameters);
    }

    public function snapshot(BinanceConnection $connection): array
    {
        $account = $this->account($connection);
        $configuration = $this->accountConfiguration($connection);
        $positions = collect($this->positionRisk($connection))
            ->filter(fn (array $position): bool => abs((float) ($position['positionAmt'] ?? 0)) > 0)
            ->values()
            ->all();

        return [
            'environment' => $connection->environment,
            'account' => [
                'can_trade' => (bool) ($configuration['canTrade'] ?? false),
                'total_wallet_balance' => (float) ($account['totalWalletBalance'] ?? 0),
                'available_balance' => (float) ($account['availableBalance'] ?? 0),
                'total_unrealized_profit' => (float) ($account['totalUnrealizedProfit'] ?? 0),
                'total_margin_balance' => (float) ($account['totalMarginBalance'] ?? 0),
            ],
            'positions' => $positions,
            'open_orders' => $this->openOrders($connection),
            'open_algo_orders' => $this->openAlgoOrders($connection),
        ];
    }

    public function syncPairsFromExchange(string $environment = 'live'): int
    {
        $info = $this->exchangeInfo($environment);
        $count = 0;
        $activeSymbols = [];
        $supportedQuotes = array_map('strtoupper', (array) config('pulse.binance.supported_quote_assets', ['USDT', 'USDC']));

        foreach ($info['symbols'] ?? [] as $symbol) {
            $quoteAsset = strtoupper((string) ($symbol['quoteAsset'] ?? ''));
            if (($symbol['contractType'] ?? null) !== 'PERPETUAL'
                || ! in_array($quoteAsset, $supportedQuotes, true)
                || ($symbol['status'] ?? null) !== 'TRADING') {
                continue;
            }

            $filters = collect($symbol['filters'] ?? [])->keyBy('filterType');
            $priceFilter = $filters->get('PRICE_FILTER', []);
            $lotFilter = $filters->get('LOT_SIZE', []);
            $marketLotFilter = $filters->get('MARKET_LOT_SIZE', $lotFilter);
            $notionalFilter = $filters->get('MIN_NOTIONAL', []);

            $symbolName = strtoupper((string) $symbol['symbol']);
            $activeSymbols[] = $symbolName;
            PulsePair::updateOrCreate(
                ['symbol' => $symbolName],
                [
                    'base_asset' => $symbol['baseAsset'],
                    'quote_asset' => $quoteAsset,
                    'price_precision' => (int) ($symbol['pricePrecision'] ?? 2),
                    'quantity_precision' => (int) ($symbol['quantityPrecision'] ?? 3),
                    'tick_size' => $priceFilter['tickSize'] ?? null,
                    // Store LOT_SIZE as the default executable quantity rule. MARKET_LOT_SIZE
                    // is applied dynamically only to MARKET orders in placeOrder().
                    'step_size' => $lotFilter['stepSize'] ?? ($marketLotFilter['stepSize'] ?? null),
                    'minimum_quantity' => $lotFilter['minQty'] ?? ($marketLotFilter['minQty'] ?? null),
                    'minimum_notional' => $notionalFilter['notional'] ?? null,
                    'last_synced_at' => now(),
                ],
            );
            $count++;
        }

        // Markets previously synchronized from Binance but no longer reported as
        // active perpetual contracts must not remain executable in Pulse.
        if ($activeSymbols !== []) {
            PulsePair::query()
                ->whereNotNull('last_synced_at')
                ->whereIn('quote_asset', $supportedQuotes)
                ->whereNotIn('symbol', $activeSymbols)
                ->update(['is_enabled' => false]);
        }

        return $count;
    }

    public function refreshPairRules(PulsePair $pair, string $environment): PulsePair
    {
        try {
            $rules = $this->symbolRules($environment, $pair->symbol);
            $lot = (array) ($rules['lot'] ?? []);
            $price = (array) ($rules['price'] ?? []);
            $notional = (array) ($rules['notional'] ?? []);

            $pair->update([
                'price_precision' => (int) ($rules['price_precision'] ?? $pair->price_precision),
                'quantity_precision' => (int) ($rules['quantity_precision'] ?? $pair->quantity_precision),
                'tick_size' => $price['tickSize'] ?? $pair->tick_size,
                'step_size' => $lot['stepSize'] ?? $pair->step_size,
                'minimum_quantity' => $lot['minQty'] ?? $pair->minimum_quantity,
                'minimum_notional' => $notional['notional'] ?? $notional['minNotional'] ?? $pair->minimum_notional,
                'last_synced_at' => now(),
            ]);

            return $pair->fresh();
        } catch (\Throwable) {
            // Execution can still use the last known filters; placeOrder performs a
            // second live normalization and safe precision-error retry.
            return $pair;
        }
    }

    public function normalizeQuantity(PulsePair $pair, float $quantity): float
    {
        $step = (float) ($pair->step_size ?: 0);
        if ($step <= 0) {
            return round($quantity, max(0, (int) $pair->quantity_precision));
        }

        $normalized = floor(($quantity + 1e-12) / $step) * $step;
        $normalized = max($normalized, (float) ($pair->minimum_quantity ?: 0));
        return round($normalized, max(0, (int) $pair->quantity_precision));
    }

    public function normalizePrice(PulsePair $pair, float $price): float
    {
        $tick = (float) ($pair->tick_size ?: 0);
        if ($tick <= 0) {
            return round($price, max(0, (int) $pair->price_precision));
        }

        return round(round($price / $tick) * $tick, max(0, (int) $pair->price_precision));
    }


    /**
     * Apply current Binance PRICE_FILTER / LOT_SIZE rules directly to a new order.
     * LIMIT orders use LOT_SIZE; MARKET orders prefer MARKET_LOT_SIZE when present.
     */
    private function normalizeLiveOrderParameters(string $environment, array $parameters, bool $fresh = false): array
    {
        $symbol = strtoupper((string) ($parameters['symbol'] ?? ''));
        if ($symbol === '') return $parameters;

        try {
            $rules = $this->symbolRules($environment, $symbol, $fresh);
        } catch (\Throwable) {
            // Keep the existing normalized values if exchangeInfo is temporarily unavailable.
            return $parameters;
        }

        $type = strtoupper((string) ($parameters['type'] ?? 'MARKET'));
        $lot = $type === 'MARKET' && (float) data_get($rules, 'market_lot.stepSize', 0) > 0
            ? (array) ($rules['market_lot'] ?? [])
            : (array) ($rules['lot'] ?? []);

        if (isset($parameters['quantity']) && is_numeric($parameters['quantity'])) {
            $quantity = abs((float) $parameters['quantity']);
            $step = (float) ($lot['stepSize'] ?? 0);
            $minQty = (float) ($lot['minQty'] ?? 0);
            $maxQty = (float) ($lot['maxQty'] ?? 0);
            if ($step > 0) $quantity = floor(($quantity + 1e-12) / $step) * $step;
            if ($minQty > 0) $quantity = max($quantity, $minQty);
            if ($maxQty > 0) $quantity = min($quantity, $maxQty);
            $quantityDecimals = $step > 0
                ? min(max(0, (int) ($rules['quantity_precision'] ?? 8)), $this->decimalPlaces((string) ($lot['stepSize'] ?? '1')))
                : max(0, (int) ($rules['quantity_precision'] ?? 8));
            $parameters['quantity'] = $this->formatFixed($quantity, $quantityDecimals);
        }

        foreach (['price', 'stopPrice', 'triggerPrice', 'activationPrice'] as $priceKey) {
            if (! isset($parameters[$priceKey]) || ! is_numeric($parameters[$priceKey])) continue;
            $price = (float) $parameters[$priceKey];
            $tick = (float) data_get($rules, 'price.tickSize', 0);
            if ($tick > 0) $price = round($price / $tick) * $tick;
            $priceDecimals = $tick > 0
                ? min(max(0, (int) ($rules['price_precision'] ?? 8)), $this->decimalPlaces((string) data_get($rules, 'price.tickSize', '1')))
                : max(0, (int) ($rules['price_precision'] ?? 8));
            $parameters[$priceKey] = $this->formatFixed($price, $priceDecimals);
        }

        return $parameters;
    }

    private function symbolRules(string $environment, string $symbol, bool $fresh = false): array
    {
        $cacheKey = 'pulse:binance:symbol-rules:'.strtolower($environment).':'.strtoupper($symbol);
        if ($fresh) Cache::forget($cacheKey);

        return Cache::remember($cacheKey, 300, function () use ($environment, $symbol): array {
            $info = $this->exchangeInfo($environment);
            foreach ((array) ($info['symbols'] ?? []) as $row) {
                if (strtoupper((string) ($row['symbol'] ?? '')) !== strtoupper($symbol)) continue;
                $filters = collect($row['filters'] ?? [])->keyBy('filterType');
                return [
                    'price_precision' => (int) ($row['pricePrecision'] ?? 8),
                    'quantity_precision' => (int) ($row['quantityPrecision'] ?? 8),
                    'price' => (array) $filters->get('PRICE_FILTER', []),
                    'lot' => (array) $filters->get('LOT_SIZE', []),
                    'market_lot' => (array) $filters->get('MARKET_LOT_SIZE', []),
                    'notional' => (array) ($filters->get('MIN_NOTIONAL', $filters->get('NOTIONAL', []))),
                ];
            }
            throw new RuntimeException('Binance exchange rules are unavailable for '.$symbol.'.');
        });
    }

    private function decimalPlaces(string $step): int
    {
        $step = rtrim(trim($step), '0');
        if (! str_contains($step, '.')) return 0;
        return max(0, strlen(substr(strrchr($step, '.'), 1)));
    }

    private function formatFixed(float $value, int $decimals): string
    {
        return number_format($value, max(0, min(12, $decimals)), '.', '');
    }

    private function signed(string $method, string $path, array $parameters, BinanceConnection $connection): array
    {
        if (! $connection->is_active) {
            throw new RuntimeException('The Binance connection is inactive.');
        }

        $parameters = array_filter($parameters, fn (mixed $value): bool => $value !== null && $value !== '');
        $parameters['timestamp'] = $this->signedTimestamp($connection->environment);
        $parameters['recvWindow'] = min(60000, max(1000, (int) config('pulse.binance.recv_window', 5000)));

        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $parameters['signature'] = hash_hmac('sha256', $query, (string) $connection->api_secret);

        return $this->request($method, $path, $parameters, $connection, $connection->environment);
    }

    private function request(
        string $method,
        string $path,
        array $parameters = [],
        ?BinanceConnection $connection = null,
        string $environment = 'live',
    ): array {
        $request = $this->client($path === '/fapi/v1/exchangeInfo');
        if ($connection) {
            $request = $request->withHeaders(['X-MBX-APIKEY' => (string) $connection->api_key]);
        }

        try {
            $response = match (strtoupper($method)) {
                'GET', 'DELETE' => $request->send(strtoupper($method), $this->baseUrl($environment).$path, ['query' => $parameters]),
                default => $request->asForm()->send(strtoupper($method), $this->baseUrl($environment).$path, ['form_params' => $parameters]),
            };

            $response->throw();
            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new RuntimeException('Binance returned an unexpected response format.');
            }
            return $decoded;
        } catch (RequestException $e) {
            $message = $e->response?->json('msg') ?: $e->getMessage();
            $code = $e->response?->json('code');
            throw new RuntimeException(trim(($code !== null ? "Binance {$code}: " : '').$message), previous: $e);
        }
    }


    private function signedTimestamp(string $environment): int
    {
        $offset = Cache::remember("pulse:binance:time-offset:{$environment}", 30, function () use ($environment): int {
            try {
                return $this->serverTime($environment) - (int) floor(microtime(true) * 1000);
            } catch (\Throwable) {
                return 0;
            }
        });

        return (int) floor(microtime(true) * 1000) + (int) $offset;
    }

    private function client(bool $largePayload = false): PendingRequest
    {
        $configuredTimeout = (int) config('pulse.binance.timeout', 30);
        $timeout = $largePayload ? max(60, $configuredTimeout) : max(20, $configuredTimeout);

        return Http::acceptJson()
            ->timeout($timeout)
            ->connectTimeout(max(7, (int) config('pulse.binance.connect_timeout', 8)))
            ->withOptions(['verify' => (bool) config('pulse.binance.ssl_verify', true)])
            ->retry(3, 500, throw: false);
    }

    private function baseUrl(string $environment): string
    {
        $url = $environment === 'testnet'
            ? (string) config('pulse.binance.testnet_base_url')
            : (string) config('pulse.binance.live_base_url');

        if ($url === '') {
            throw new RuntimeException('Binance Futures base URL is not configured.');
        }

        return rtrim($url, '/');
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 12, '.', ''), '0'), '.');
    }
}
