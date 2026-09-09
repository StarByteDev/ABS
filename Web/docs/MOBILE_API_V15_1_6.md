# ABS V15.1.6 Mobile API Readiness

Base URL: `/api/v1`

## Purpose
V15.1.6 keeps the Laravel backend as the canonical ABS data, trading, membership, news and analytics service for the upcoming mobile app. The API is ready for mobile client integration; native Flutter screens and native rewarded-ad SDK wiring are a separate mobile-app implementation step.

## Public / no-login
- `GET /bootstrap` — app build, module/capability and navigation bootstrap.
- `GET /market/overview`, `/market/movers`, `/market/chart/{symbol}` — public market data.
- `GET /news`, `/news/live`, `/economic-calendar` — ABS News and macro calendar.
- `GET /legal/{type}` — privacy, terms, risk and market disclaimer.
- `GET /pulse/free-signal/status` — anonymous Free Signal availability/cooldown. Persist the returned `visitor_token` securely on device and send it as `X-ABS-Visitor-Token`.
- `POST /pulse/free-signal/session` — reserve the best available public qualified signal, or clearly-labelled ENTRY WATCH fallback, and issue a one-time reward claim session.
- `POST /pulse/free-signal/claim` — returns the reserved result after the rewarded grant protocol succeeds.

### Rewarded-ad mobile note
The server protocol is mobile-ready. The current deployed ad inventory/configuration is Google Ad Manager rewarded **web** inventory. When the Flutter app is implemented, use the native Google Mobile Ads/AdMob rewarded SDK and connect its verified reward flow to the ABS backend rather than pretending the web creative is a native ad. Do not unlock a signal locally without a server claim.

## Authentication / account
- register, login, activation resend, password reset
- `/auth/me`, sessions, logout/logout-all
- profile, notifications and preferences
- registered devices / push-token records
- watchlist

Authentication uses Sanctum bearer tokens. Active-account middleware applies to protected API routes.

## Direct-USDT Pulse membership
- `GET /pulse/plans`
- `GET /pulse/membership`
- `POST /pulse/membership/quote`
- `POST /pulse/membership/requests`
- `PATCH /pulse/membership/requests/{membershipRequest}`

Commerce remains: user transfers USDT -> submits transaction reference/proof -> Admin verifies -> selected package access activates. There is no active Sparks/points wallet economy.

## Authenticated Pulse features
Subject to package capabilities:
- dashboard and usage
- market/pair universe
- scanner overview/runs/run
- signals, signal detail, dismissal, sharing and explanation
- execution readiness/ticket
- manual trade execution and close
- positions, orders and emergency stop
- Binance connection management/test/activation
- risk controls and Pulse settings
- alerts
- central market-data prices and health
- signal-validation detail

## Reporting & strategy intelligence
- `GET /pulse/reports`
- `GET /pulse/reports/signals`
- `GET /pulse/reports/strategies`
- `GET /pulse/reports/learning`
- `GET /pulse/reports/simulation`

V15.1.6 adds strategy profitability and a research-only "what if all validated signals were followed" model. The simulation reports resolved TP/SL outcomes using an equal-risk/equal-notional R-multiple model. It excludes ambiguous/unresolved outcomes and does not assume leverage, fees, funding, slippage or compounding. It is not a forecast, robot-trading promise or account backtest.

## Live-data pipeline used by web and mobile
The API reads the same centralized ABS database used by the website. Expected scheduler chain:
1. `abs:pulse-market-data` every minute — Binance Futures prices, scanner candle buffers, validation 1m candles.
2. `abs:pulse-validate-signals` every minute — entry-before-outcome validation and TP/SL/ambiguous lifecycle.
3. `abs:pulse-sync` every minute — reconcile Binance orders, positions, protection orders, realized P&L and fees.
4. `abs:pulse-learning` daily — rebuild evidence-protected strategy reliability.
5. `abs:pulse-automation` — according to scheduler profile and trading safety gates.

HostGator shared profile expects the Laravel scheduler host cron to run once per minute.

## Pre-mobile-app handoff commands
After production deployment/migration:
- `php artisan abs:doctor`
- `php artisan abs:scheduler-check`
- `php artisan abs:pulse-execution-check`
- `php artisan abs:production-check --email=YOUR_EMAIL`
- `php artisan abs:pulse-analytics-backfill --days=7`

Do not treat successful static/package validation as proof of live Binance, Google Ads, SMTP, FMP or HostGator cron connectivity; those require production credentials and environment smoke tests.
