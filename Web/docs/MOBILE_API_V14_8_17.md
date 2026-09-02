# ABS Mobile API V14.8.17 — Central Price & Signal Intelligence Architecture

V14.8.17 keeps the Flutter/mobile client behind the ABS API. The mobile app must **not** call Binance public market-data endpoints directly and must not cause a Binance fetch per user.

## Central market data

The server scheduler runs `php artisan schedule:run` every minute. ABS centrally refreshes:

- latest Binance USD-M Futures prices for enabled Pulse markets (one shared ticker snapshot),
- rolling 15M and 4H scanner candle buffers,
- short-retention 1M candles for active/recent signal validation.

Mobile reads the same centrally timestamped records as the website.

### Endpoints

- `GET /api/v1/pulse/market-data/health`
  - central feed freshness, last run state and supported scanner timeframes.
- `GET /api/v1/pulse/market-data/prices?symbols[]=BTCUSDT&symbols[]=ETHUSDT`
  - package-authorized current prices from ABS storage; no upstream Binance request is triggered.
- `POST /api/v1/pulse/scanner/run` with `timeframe=all`
  - one quota-safe scan across **15M + 4H** using central candle buffers.

## Immutable signal lifecycle

Generated signals freeze:

- strategy bundle/version and strategy snapshot,
- generation timestamp,
- direction,
- entry,
- SL,
- TP levels,
- technical score,
- evidence-protected reliability score,
- confidence score.

A later scan cannot rewrite those frozen values.

Signal validation is driven by future 1M candles:

1. `waiting_entry`
2. entry must be hit before any TP/SL outcome can be validated
3. the entry minute is excluded from TP/SL validation because OHLC cannot establish intraminute order
4. later 1M candles validate TP, SL, expiry or ambiguity
5. same-minute TP + SL after entry is `ambiguous`, never automatically a win
6. MFE, MAE, duration and highest TP level reached are recorded

`GET /api/v1/pulse/signals/{signal}/validation` returns the current validation record for the signed-in user.

Detailed validation is retained for approximately 7 days by default. Compact daily aggregates and learning state are permanent.

## Reporting endpoints

- `GET /api/v1/pulse/reports`
  - combined trading + signal-intelligence summary.
- `GET /api/v1/pulse/reports/signals?from=YYYY-MM-DD&to=YYYY-MM-DD`
  - permanent daily signal outcomes plus recent detailed validations.
- `GET /api/v1/pulse/reports/strategies?from=YYYY-MM-DD&to=YYYY-MM-DD`
  - strategy performance separated by strategy version + timeframe + direction.
- `GET /api/v1/pulse/reports/learning`
  - current reliability state, evidence levels and sample sizes.

## Learning protection

Strategy learning uses:

- sample-size protection / neutral prior,
- strategy + version + timeframe + direction separation,
- context/market-regime state only when evidence is sufficient,
- hierarchical fallback for sparse context data,
- recency weighting,
- ambiguity penalty rather than treating ambiguous outcomes as wins.

## Mobile refresh approach

Recommended app behavior:

- poll central price/status endpoints at a UI-appropriate interval (for example 15–30 seconds while the screen is visible),
- use `observed_at` to show freshness,
- do not run a scanner just to refresh prices,
- run scanner only on explicit user scan action / permitted workflow,
- use signal-validation/report endpoints for history instead of reconstructing outcomes on-device.
