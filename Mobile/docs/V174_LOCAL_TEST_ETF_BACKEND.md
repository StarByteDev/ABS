# Pulse V1.7.4 Local Test & Optional BTC ETF Feed

## Flutter local QA

1. `flutter clean`, `flutter pub get`, `flutter analyze`, `flutter test`, `flutter run`.
2. Open **Markets** → BTC chart. Confirm the chart fills the card width. Test **1H**, **1D**, **1W**, **1M**, **1Y**; the server only accepts 5m, 15m, 1h, 4h and 1d candles. Pull to refresh and retry unavailable data.
3. Stablecoin liquidity card shows the ABS *estimated 24-hour stablecoin market capitalization change* if available; a cap-only value is labeled as such when the estimated change is unavailable. These are not exchange net inflows/outflows.
4. Home must have no Live Opportunities and no Watchlist. Free Signal, calendar, futures metrics and AdMob slot remain.
5. Compare against logged-out, trader, investor accounts; confirm rewarded-ad verification, cooldown, saved investor records, API access, Firebase, app navigation and native ad configuration remain unchanged.

## Optional CoinGlass ETF backend patch

The original ABS V15.9.1 lacks a public ETF flow endpoint. Deploy only the additive backend patch files against a **backup** of the V15.9.1 web/backend code. The patch registers read-only:

`GET /api/v1/market/etf-flows`

Add `COINGLASS_API_KEY=your_licensed_provider_key` in your SERVER `.env` file. Never put the provider key in Flutter or an app store build. Restart/reload Laravel config (`php artisan config:clear`), then check:

`curl -H 'Accept: application/json' https://YOUR-ABS-DOMAIN/api/v1/market/etf-flows`

The response uses `{ "data": {"net_flow_usd": number|null, "flow_date": "YYYY-MM-DD"|null, "status": "available"|"provider_not_configured"|"provider_unavailable"|"no_recent_trading_session", ...} }`.

When unconfigured, the mobile Home shows 'Not available' without causing crashes, and Markets keeps functioning. The ETF data is U.S. *spot Bitcoin ETF net flows* reported per trading session, NOT an exchange netflow or real-time signal. Provider plans, commercial usage rights and billing are your responsibility.

## Production safety

These mobile edits do not change server migrations, investors, account data or trading strategies. The ETF server patch is read-only and additive; roll back by restoring `routes/api.php`, `config/services.php`, `MarketController.php` from your backup and deleting the new `BtcEtfFlowService.php`.

## Verification

Source/ZIP integrity and PHP syntax checks can be run here. Full Flutter compilation, widget rendering, online API reads, live CoinGlass credentials and device tests must be verified on your machine before publishing.
