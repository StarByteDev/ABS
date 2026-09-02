# ABS V14.8.17 — Central Price & Signal Intelligence Architecture

## Production objective

ABS now uses one server-owned Binance Futures market-data pipeline for the website, scanner, signal state, validation, reporting, learning and mobile API. A signed-in user or mobile app never starts an upstream Binance public-price/candle request merely by opening a page.

## Central data flow

`Binance Futures -> ABS scheduler -> pulse_market_prices / pulse_market_candles -> Scanner + Signal Validation + Web + API`

The scheduler target is once per minute. Each central run:

1. refreshes one shared latest-price/ticker snapshot for enabled Pulse markets;
2. refreshes due rolling 15M and 4H candle buffers in shared-hosting-friendly batches;
3. refreshes short-retention 1M candles required for recent signal validation;
4. records run health/failure details in `pulse_market_data_runs`;
5. prunes only raw candle detail according to configured retention.

Scanner calculations use **closed** central 15M/4H candles. The 1M buffer is reserved for outcome validation and does not redefine the frozen signal.

## Frozen signal contract

At generation ABS freezes:

- symbol, timeframe, direction and generation time;
- entry, stop loss and TP levels;
- technical score;
- qualified strategy snapshot and per-strategy versions;
- engine strategy-bundle version;
- reliability score and confidence score as known at generation time;
- market-regime/context metadata;
- deterministic signal fingerprint.

A later market scan cannot rewrite these values. The live action/status layer may change as current price changes, but the signal plan remains immutable.

## Validation lifecycle

Validation uses only future closed 1M candles.

`waiting_entry -> active -> resolved`

Rules:

- no TP/SL can count before entry is observed;
- the exact 1M candle that first touches entry is excluded from TP/SL resolution because OHLC cannot prove intraminute ordering;
- subsequent candles may resolve TP, SL, expiry or ambiguity;
- if TP and SL are both touched in the same post-entry minute, outcome is `ambiguous`, never automatically a win;
- MFE, MAE, MFE-R, MAE-R, duration and highest TP level reached are retained in the detailed validation record;
- resolved detailed records are short-retention (7 days by default), while daily aggregates/learning state remain permanent.

## Reporting and learning

Permanent signal reporting is stored by date + user + timeframe + direction. It includes signals, entries, wins, losses, ambiguity, no-entry expiry, post-entry expiry, MFE/MAE and duration. ABS also writes a platform-level `user_id = null` daily rollup deduplicated by signal fingerprint for Admin → Pulse → Signal Intelligence.

Permanent strategy reporting is separated by:

`strategy + version + timeframe + direction + market regime`

To avoid duplicated learning, identical market setups are deduplicated by frozen signal fingerprint before global strategy statistics are updated.

Learning state applies:

- neutral-prior/sample-size protection;
- developing/established evidence levels;
- ambiguity penalty;
- recency weighting;
- market-regime/context use only after the context has sufficient evidence;
- hierarchical fallback to broader strategy/version/timeframe/direction evidence when context is sparse.

## Mobile/API architecture

Flutter/iOS/Android clients consume `/api/v1/pulse/*` only. They do not need Binance public market-data credentials and must not poll Binance directly.

New/expanded endpoints:

- `GET /api/v1/pulse/market-data/health`
- `GET /api/v1/pulse/market-data/prices`
- `GET /api/v1/pulse/signals/{signal}/validation`
- `GET /api/v1/pulse/reports`
- `GET /api/v1/pulse/reports/signals`
- `GET /api/v1/pulse/reports/strategies`
- `GET /api/v1/pulse/reports/learning`
- `POST /api/v1/pulse/scanner/run` with `timeframe=15m`, `4h` or `all` (`all` = 15M + 4H in one user scan allowance)

All user-facing price results are package-authorized and include `observed_at` so web/mobile can display freshness without forcing a new scan.

## HostGator/shared-hosting deployment

After uploading the release and preserving the production `.env`/database backup:

```bash
php artisan abs:repair --seed
php artisan config:clear
php artisan cache:clear
php artisan abs:pulse-market-data
php artisan abs:pulse-validate-signals
```

Configure the hosting control-panel cron to invoke Laravel scheduler **once per minute** using the PHP CLI path supplied by the host, for example:

```cron
* * * * * cd /home/ACCOUNT/path-to-abs && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

The exact PHP binary/path varies by HostGator account. Only one Laravel scheduler cron is needed; Laravel dispatches the market-data, validation, learning and existing ABS maintenance tasks.

### Operational checks

```bash
php artisan abs:doctor
php artisan abs:pulse-market-data
php artisan abs:pulse-validate-signals
php artisan abs:production-check
```

From web/mobile, check `/api/v1/pulse/market-data/health` and ensure `latest_price_observed_at` remains current.

## Retention defaults

- 1M raw candles: 2 days
- 15M rolling candles: 21 days
- 4H rolling candles: 180 days
- detailed resolved signal validation: 7 days
- daily signal metrics: permanent
- daily strategy metrics: permanent
- strategy learning state: permanent/current state

These can be tuned with the V14.8.17 environment variables where exposed, without changing the API contract.
