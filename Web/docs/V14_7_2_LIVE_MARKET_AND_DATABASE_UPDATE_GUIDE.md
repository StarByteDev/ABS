# ABS V14.7.2 — Live Market Resilience + Admin Database Maintenance Build

**Release date:** 23 August 2026

This release is built from the production V14.7.1 backup/recovery baseline and preserves the confirmed premium homepage, MySQL data model, Admin CMS, Pulse workspace, mobile API, email system, backup/restore and HostGator recovery tools.

## Live market resilience fixes

- Fixed a market-overview cache version mismatch that could leave server-rendered homepage values in a permanent `Syncing` / `Unavailable` state even after a successful refresh.
- Decoupled homepage data loading so one blocked provider no longer prevents unrelated market widgets from updating.
- Added browser-side public fallbacks for global market metrics, Fear & Greed, liquidations, futures metrics, industries and movers.
- Added Alternative.me global-market fallback for Total Market Cap / 24H Volume / BTC Dominance when CoinGecko is temporarily unavailable server-side.
- Added OKX public derivatives fallback for Open Interest, Funding Rate, Long/Short Ratio and Perpetual Premium Basis when Binance Futures cannot be reached from shared hosting.
- Added CoinGecko fallback for Top Gainers / Top Losers when the Binance all-tickers endpoint is unavailable.
- Market Pulse and Market Sentiment can now be derived from the live browser-side core market feed instead of remaining unavailable solely because the PHP server cannot reach the same exchange host.
- No synthetic prices are introduced. A widget stays unavailable only when its applicable public sources cannot return usable data.

## Admin database/update center

A new **Admin → System & Migration → Database & Updates** page provides future in-app maintenance after deploying a newer build:

- **Apply Latest Database Update** — attempts a safety backup, repairs required ABS/Pulse tables and columns, baselines satisfied legacy migrations, runs pending migrations, synchronizes baseline configuration and clears caches.
- **Repair Tables & Columns** — non-destructive schema repair.
- **Content & Configuration Sync** — synchronizes packaged baseline plans/settings without resetting an existing administrator password.
- **Run Pending Migrations** — applies current migration files after legacy-baseline reconciliation.
- **Clear Application Caches** — useful after `.env`, route or configuration changes.
- **Refresh & Test Market Providers** — forces a live refresh and reports market cap, volume, dominance, Fear & Greed, liquidations, OI, funding, long/short ratio, perp basis, industries and movers.

All database maintenance routes remain protected by authentication, active-account checks and the Admin role.

## Production upgrade notes

1. Preserve the live production `.env` and **APP_KEY** when replacing application files.
2. Do **not** initialize a database that already contains live ABS data.
3. After upload, run `php artisan optimize:clear`, or use Admin → Database & Updates → **Clear Laravel Caches**.
4. For future schema changes use **Apply Latest Database Update** from Admin instead of manually deleting/recreating tables.
5. `COINGECKO_API_KEY` remains optional, but adding a CoinGecko Demo API key can improve reliability under rate limiting.

## Bootstrap administrator

For a brand-new database initialized without custom bootstrap values, the packaged defaults are:

- Email: `admin@alphablocksolutions.local`
- Password: `Admin@12345`

If `ABS_ADMIN_EMAIL` / `ABS_ADMIN_PASSWORD` were set in `.env` before the first seed, those values were used instead. Routine repair/seeding does not reset an existing administrator password. Change the bootstrap password immediately on a public deployment.

