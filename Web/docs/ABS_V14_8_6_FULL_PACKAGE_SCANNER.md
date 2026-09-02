# ABS V14.8.6 — Full Package Scanner, Select-All Markets & Scan Results Reliability

## Purpose

V14.8.6 corrects the scanner/pair-selection mismatch found on large Pulse packages. A package may contain hundreds of Binance USD-M Futures markets, while the previous runtime still inherited a 20-market scan ceiling and the normal web scan posted the user's saved execution selection. This release separates **package scanning** from **execution/watch selection**.

## Package-wide Market Scan

- Normal **Run Market Scan** evaluates every enabled market included in the authenticated user's current Pulse package.
- The user's saved `selected_pairs` is an execution/watch preference and no longer reduces the normal scan universe.
- Optional targeted web/mobile scanner requests are supported up to the server safety ceiling, which defaults to 1000 symbols.
- If a package contains more markets than the configured safety ceiling, ABS returns a clear configuration message instead of silently dropping markets.

## Large Binance Packages

- Binance kline requests are issued in bounded concurrent batches rather than one strictly serial request for every market.
- Default batch concurrency is 25 and can be adjusted with `PULSE_SCANNER_BATCH_CONCURRENCY`.
- Binance 24H ticker data is retrieved in bulk to enrich scanner results with current price/change context without one ticker request per symbol.

## Reliable Scan Outcomes

- Strategy scores are normalized to a common 0–100 scale based on the strategies actually included in the user's package.
- Reaching the user's daily signal allowance stops creation of additional signals but does **not** stop evaluation of the remaining package markets.
- If Binance candle data cannot be evaluated for any package market, the run is marked failed and does not consume the daily completed-scan quota.
- Stale `running` scan records are automatically released after the configured timeout (15 minutes by default).

## Scanner Results

- The Market Scanner reads the latest scan evaluation summary, not only already-created active signals.
- If one or more markets meet the active filters, qualifying setups are shown first.
- If none meet the current score threshold, the strongest evaluated markets remain visible as **Below Filter** or **No Setup** rather than leaving the screen blank.
- No trading signal is fabricated. Only a qualifying scanner evaluation that passes the configured threshold and quota rules creates a Pulse signal.

## Pair Selection UX

### User

Trading Markets now provides:

- **Select All**
- **Clear All**
- **Popular**
- Search and quote-asset filters

Select All respects the package's `max_selected_pairs` execution/watch limit. If the package permits all available markets, one click selects the full package set.

### Admin

Admin package market assignment now also provides **Select All / Clear All**, making large selected-market packages practical to configure.

## Database

No database migration is required for V14.8.6.
