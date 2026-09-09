<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-sol'),
    ],
    'rewarded_ads' => [
        'secret' => env('PULSE_REWARDED_AD_SECRET'),
        'admob_key_url' => env('ADMOB_SSV_KEY_URL', 'https://www.gstatic.com/admob/reward/verifier-keys.json'),
    ],
    'fmp' => [
        'api_key' => env('FMP_API_KEY'),
        'economic_calendar_url' => env('FMP_ECONOMIC_CALENDAR_URL', 'https://financialmodelingprep.com/stable/economic-calendar'),
    ],
    'binance' => [
        'base_url' => env('BINANCE_MARKET_BASE_URL', 'https://data-api.binance.vision'),
        'base_urls' => array_filter(array_map('trim', explode(',', env('BINANCE_MARKET_BASE_URLS', 'https://data-api.binance.vision,https://api.binance.com,https://api1.binance.com')))),
    ],
    'coingecko' => ['base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'), 'api_key' => env('COINGECKO_API_KEY')],
    'fear_greed' => ['base_url' => env('FEAR_GREED_BASE_URL', 'https://api.alternative.me')],
    'alternative_crypto' => [
        'base_url' => env('ALTERNATIVE_CRYPTO_BASE_URL', 'https://api.alternative.me'),
        'base_urls' => array_filter(array_map('trim', explode(',', env('ALTERNATIVE_CRYPTO_BASE_URLS', 'https://api.alternative.me')))),
    ],
    'okx_market' => [
        'base_url' => env('OKX_MARKET_BASE_URL', 'https://www.okx.com'),
        'base_urls' => array_filter(array_map('trim', explode(',', env('OKX_MARKET_BASE_URLS', 'https://www.okx.com,https://us.okx.com')))),
    ],
    'liquidations' => ['base_url' => env('LIQUIDATION_API_BASE_URL', 'https://xoomar.com')],
    'binance_futures_market' => [
        'base_url' => env('BINANCE_FUTURES_MARKET_BASE_URL', 'https://fapi.binance.com'),
        'base_urls' => array_filter(array_map('trim', explode(',', env('BINANCE_FUTURES_MARKET_BASE_URLS', 'https://fapi.binance.com')))),
    ],
    'market' => [
        'cache_seconds' => (int) env('MARKET_CACHE_SECONDS', 60),
        'timeout' => (int) env('MARKET_HTTP_TIMEOUT', 7),
        'connect_timeout' => (int) env('MARKET_CONNECT_TIMEOUT', 3),
        'ssl_verify' => filter_var(env('MARKET_SSL_VERIFY', env('APP_ENV', 'production') !== 'local'), FILTER_VALIDATE_BOOL),
    ],
    'news_feeds' => [
        'enabled' => filter_var(env('LIVE_NEWS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'cache_seconds' => (int) env('LIVE_NEWS_CACHE_SECONDS', 300),
        'timeout' => (int) env('LIVE_NEWS_HTTP_TIMEOUT', 6),
        'connect_timeout' => (int) env('LIVE_NEWS_CONNECT_TIMEOUT', 2),
        'sources' => [
            ['name' => 'CoinDesk', 'url' => env('COINDESK_RSS_URL', 'https://www.coindesk.com/arc/outboundfeeds/rss/')],
            ['name' => 'Cointelegraph', 'url' => env('COINTELEGRAPH_RSS_URL', 'https://cointelegraph.com/rss')],
            ['name' => 'Decrypt', 'url' => env('DECRYPT_RSS_URL', 'https://decrypt.co/feed')],
        ],
    ],
];
