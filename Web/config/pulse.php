<?php

$schedulerProfile = strtolower(trim((string) env('PULSE_SCHEDULER_PROFILE', 'standard')));
$hostgatorShared = in_array($schedulerProfile, ['hostgator', 'hostgator_shared', 'shared'], true);
$defaultRefreshSeconds = 60;
$defaultReadMaxAgeSeconds = 300;
$defaultScannerSymbolsPerCycle = $hostgatorShared ? 200 : 100;

return [
    'scheduler' => [
        'profile' => $schedulerProfile,
        'hostgator_shared' => $hostgatorShared,
        'cron_minutes' => max(1, (int) env('PULSE_CRON_MINUTES', 1)),
    ],
    'service_slug' => 'pulse',
    'default_environment' => env('PULSE_DEFAULT_ENVIRONMENT', 'testnet'),
    // Live Futures is controlled primarily from Admin > Pulse Settings and per-plan permissions.
    // This server-level kill switch can force live trading off without editing database settings.
    'allow_live_trading' => ! filter_var(env('PULSE_DISABLE_LIVE_TRADING', false), FILTER_VALIDATE_BOOL),
    'allow_automatic_trading' => filter_var(env('PULSE_ALLOW_AUTOMATIC_TRADING', false), FILTER_VALIDATE_BOOL),
    'scanner' => [
        'timeframe' => env('PULSE_SCANNER_TIMEFRAME', '15m'),
        'candle_limit' => (int) env('PULSE_SCANNER_CANDLE_LIMIT', 120),
        'signal_threshold' => (float) env('PULSE_SIGNAL_THRESHOLD', 65),
        'signal_expiry_minutes' => (int) env('PULSE_SIGNAL_EXPIRY_MINUTES', 90),
        // V14.8.7: old production .env files may still contain PULSE_MAX_PAIRS_PER_RUN=20.
        // Never let that legacy value undercut the modern selected-market scanner.
        'max_pairs_per_run' => max(1000, (int) env('PULSE_MAX_PAIRS_PER_RUN', 1000)),
        'batch_concurrency' => (int) env('PULSE_SCANNER_BATCH_CONCURRENCY', 25),
        'stale_run_minutes' => (int) env('PULSE_SCANNER_STALE_RUN_MINUTES', 15),
    ],

    'market_data' => [
        // V14.8.17: shared-hosting friendly central market-data architecture.
        // One scheduled server task refreshes prices/candles for every web/mobile user.
        'target_price_refresh_seconds' => max(60, (int) env('PULSE_MARKET_PRICE_REFRESH_SECONDS', $defaultRefreshSeconds)),
        'read_max_age_seconds' => max(120, (int) env('PULSE_MARKET_READ_MAX_AGE_SECONDS', $defaultReadMaxAgeSeconds)),
        'lock_seconds' => (int) env('PULSE_MARKET_DATA_LOCK_SECONDS', 55),
        'scanner_timeframes' => ['15m', '4h'],
        'scanner_symbols_per_cycle' => $hostgatorShared ? max(200, (int) env('PULSE_MARKET_SCANNER_SYMBOLS_PER_CYCLE', env('PULSE_MARKET_SCANNER_SYMBOLS_PER_MINUTE', $defaultScannerSymbolsPerCycle))) : (int) env('PULSE_MARKET_SCANNER_SYMBOLS_PER_CYCLE', env('PULSE_MARKET_SCANNER_SYMBOLS_PER_MINUTE', $defaultScannerSymbolsPerCycle)),
        'scanner_candle_limit' => (int) env('PULSE_MARKET_SCANNER_CANDLE_LIMIT', 180),
        'validation_1m_limit' => (int) env('PULSE_MARKET_VALIDATION_1M_LIMIT', 180),
        'http_batch_size' => (int) env('PULSE_MARKET_HTTP_BATCH_SIZE', 25),
        'retention_days' => ['1m' => 2, '15m' => 21, '4h' => 180],
    ],
    'validation' => [
        'detailed_retention_days' => (int) env('PULSE_SIGNAL_VALIDATION_RETENTION_DAYS', 7),
    ],
    'learning' => [
        'prior_samples' => (int) env('PULSE_LEARNING_PRIOR_SAMPLES', 20),
        'minimum_samples' => (int) env('PULSE_LEARNING_MINIMUM_SAMPLES', 20),
        'established_samples' => (int) env('PULSE_LEARNING_ESTABLISHED_SAMPLES', 50),
        'context_minimum_samples' => (int) env('PULSE_LEARNING_CONTEXT_MINIMUM_SAMPLES', 40),
        'daily_decay' => (float) env('PULSE_LEARNING_DAILY_DECAY', 0.97),
    ],
    'settings' => [
        // After a user saves a changed trading-market selection, the pair list
        // remains fixed for this many hours to prevent rapid package/pair hopping.
        'pair_change_lock_hours' => (int) env('PULSE_PAIR_CHANGE_LOCK_HOURS', 50),
    ],
    'risk' => [
        'max_leverage' => (int) env('PULSE_MAX_LEVERAGE', 20),
        'max_risk_per_trade' => (float) env('PULSE_MAX_RISK_PER_TRADE', 5),
        'default_stop_loss_percent' => (float) env('PULSE_DEFAULT_STOP_LOSS_PERCENT', 1.0),
        'default_take_profit_percent' => (float) env('PULSE_DEFAULT_TAKE_PROFIT_PERCENT', 2.0),
        'minimum_notional_buffer_percent' => (float) env('PULSE_MIN_NOTIONAL_BUFFER_PERCENT', 2.0),
    ],
    'automation' => [
        'lock_seconds' => (int) env('PULSE_AUTOMATION_LOCK_SECONDS', 55),
        'max_users_per_run' => (int) env('PULSE_AUTOMATION_MAX_USERS', 25),
        'max_signals_per_user' => (int) env('PULSE_AUTOMATION_MAX_SIGNALS_PER_USER', 3),
    ],
    'binance' => [
        'timeout' => (int) env('PULSE_BINANCE_TIMEOUT', 30),
        'connect_timeout' => (int) env('PULSE_BINANCE_CONNECT_TIMEOUT', 7),
        'ssl_verify' => filter_var(env('PULSE_BINANCE_SSL_VERIFY', true), FILTER_VALIDATE_BOOL),
        'recv_window' => (int) env('PULSE_BINANCE_RECV_WINDOW', 5000),
        'price_protect' => filter_var(env('PULSE_BINANCE_PRICE_PROTECT', false), FILTER_VALIDATE_BOOL),
        'testnet_base_url' => env('PULSE_BINANCE_TESTNET_URL', 'https://demo-fapi.binance.com'),
        'live_base_url' => env('PULSE_BINANCE_LIVE_URL', 'https://fapi.binance.com'),
        'supported_quote_assets' => array_values(array_filter(array_map('trim', explode(',', env('PULSE_BINANCE_QUOTE_ASSETS', 'USDT,USDC'))))),
    ],
];
