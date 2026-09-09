# ABS Mobile API V15.1.1 — ABS News & Macro Calendar

The Laravel backend exposes public mobile-ready content under `/api/v1`.

## ABS News
- `GET /api/v1/news` — published ABS editorial content.
- `GET /api/v1/news/live?limit=20&refresh=1` — current external headlines from configured RSS feeds; titles link to the original publisher.
- `GET /api/v1/economic-calendar?from=YYYY-MM-DD&to=YYYY-MM-DD&crypto_relevant=1` — CPI, PPI, FOMC, labour, GDP, PMI, retail and other economic events.

Economic-event rows include the scheduled `event_at`, `previous_value`, `forecast_value`, `actual_value`, `impact`, source metadata, `easy_explanation`, `crypto_impact` and `crypto_impact_summary` when the V15.1.1 schema is applied.

The response metadata includes the application timezone and a market-risk notice. Forecasts are estimates; actual values can be delayed or revised; crypto-impact wording is simplified educational context and not a prediction.

## Data maintenance
Admin can maintain events manually in **Admin → CMS → Economic Calendar**, or configure a Financial Modeling Prep API key and use **Sync Economic Calendar Now**. When auto-sync is enabled, the existing Laravel scheduler attempts a refresh every 15 minutes.

## Native mobile UI
This package is the Laravel website/backend. A Flutter client should display the fields above in its ABS News screen. Native Flutter source is not included in this archive, so no claim is made that an existing APK/AAB UI was modified by this backend release.
