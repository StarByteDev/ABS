# ABS V14.7.9 — Mobile API Guide

**Base URL:** `https://YOUR-DOMAIN.COM/api/v1`  
**Authentication:** Laravel Sanctum bearer token  
**Contract:** `docs/openapi.yaml` (OpenAPI 3.1)

The API is intended to support a native Flutter/Android/iOS client without exposing exchange credentials or server-only administration secrets.

## Account lifecycle

- Register: `POST /auth/register`
- Resend activation: `POST /auth/activation/resend`
- Login: `POST /auth/login`
- Forgot password: `POST /auth/forgot-password`
- Reset password: `POST /auth/reset-password`
- Current user: `GET /auth/me`
- Sessions: `GET /auth/sessions`
- Revoke session: `DELETE /auth/sessions/{token}`
- Logout current: `POST /auth/logout`
- Logout all: `POST /auth/logout-all`

New accounts remain pending until the signed activation link is used. Authenticated feature routes also require the account to be active.

## App bootstrap / content

- `GET /bootstrap` — app/mobile settings and public bootstrap data.
- `GET /market/overview`, `/market/movers`, `/market/chart/{symbol}` — live market context.
- `GET /news`, `/news/live`, `/news/{article}` — CMS and external verified-source headlines.
- `GET /research`, `/research/{report}` — published research CMS.
- `GET /learning`, `/learning/{lesson}` — published learning CMS.
- `GET /economic-calendar` — economic-event CMS.
- `GET /products`, `/products/{product}` — ABS products/services.
- `GET /content/settings` — public/mobile settings.
- `GET /legal/{type}` — privacy, terms, risk and disclaimer content.
- `POST /newsletter` — newsletter registration.
- `POST /contact` — support/contact enquiry with acknowledgement email.

## User account / mobile devices

- `GET /dashboard`
- `PATCH /profile`
- `GET /notifications` / mark read
- `GET|PUT /notification-preferences`
- `GET|POST /devices`
- `PUT|DELETE /devices/{device}`
- `GET|POST|DELETE /watchlist...`

Device records accept platform, app version, OS version and a push token. V14.7.9 stores push tokens ready for future native push transport; it does not claim FCM/APNs delivery without a configured push provider.

## Pulse mobile parity

Authenticated users with Pulse access and the required plan capability can use:

- Access, plans, membership quote/request/cancel.
- Dashboard and enabled pairs.
- Strategies and signal evidence.
- Scanner runs and scanner execution.
- Signals and signal execution readiness.
- Execution readiness and safety gates.
- Positions, orders and trade history.
- Risk controls and settings.
- Reports.
- Binance connection management/test (secrets are never returned).
- Pulse alerts / read state.
- Trade synchronization, close request and emergency stop where capability/gates permit.

Live and automatic execution remain governed by the same server-side platform, plan, account and user controls as the web application.

### Signed-in screen/action matrix

| Mobile screen or action | Read API | Mutation/action API |
|---|---|---|
| Account dashboard | `GET /dashboard` | — |
| Account profile | `GET /auth/me` | `PATCH /profile` |
| Pulse dashboard | `GET /pulse/dashboard` | — |
| Market Scanner | `GET /pulse/scanner/overview`, `GET /pulse/scanner/runs` | `POST /pulse/scanner/run` |
| Signals | `GET /pulse/signals/overview`, `GET /pulse/signals/{signal}` | `PATCH /pulse/signals/{signal}/dismiss`, `POST /pulse/signals/{signal}/execute` |
| Strategies | `GET /pulse/strategies`, `GET /pulse/strategies/overview` | Settings remain server/plan controlled |
| Trade Execution | `GET /pulse/execution/readiness`, `GET /pulse/execution/ticket` | `POST /pulse/signals/{signal}/execute` |
| Open Positions | `GET /pulse/positions` | `POST /pulse/trades/{trade}/close` |
| Trade History | `GET /pulse/trades`, `GET /pulse/trades/{trade}` | `POST /pulse/trades/sync` |
| Risk Controls | `GET /pulse/risk-controls` | `PUT /pulse/risk-controls`, `POST /pulse/emergency-stop` |
| Alerts & Watchlists | `GET /pulse/alerts`, `GET /watchlist` | Alert read endpoints and watchlist add/remove endpoints |
| Reports & P&L | `GET /pulse/reports` | — |
| Binance Connection | `GET /pulse/binance/connections` | Save, test and delete connection endpoints |
| Settings | `GET /pulse/settings` | `PUT /pulse/settings` |
| Plans & Billing | Access, plan and membership endpoints | Quote, submit and cancel membership request endpoints |
| Notifications & Devices | Notification, preference and device endpoints | Read/update/register/revoke endpoints |

### Premium Pulse screen payloads

The mobile client can render the same five approved member pages without rebuilding financial calculations on-device:

- `GET /pulse/dashboard?period=24h|7d|30d|all` — P&L, trade outcomes, signal engagement, account standing, risk/exposure and open positions.
- `GET /pulse/scanner/overview` — scanner KPIs, filtered setup rows, market conditions, coverage and quality.
- `GET /pulse/signals/overview?selected={id}` — active queue, selected evidence, risk snapshot and 30-day activity.
- `GET /pulse/strategies/overview?period=7d|30d|90d|all` — Momentum, Breakout and Reversal contribution plus global health controls.
- `GET /pulse/execution/ticket?signal_id={id}` — ticket defaults, server calculation, account exposure and every pre-trade safeguard.
- `PATCH /pulse/signals/{signal}/dismiss` — dismiss an owned signal while retaining it in history.

Scanner execution remains `POST /pulse/scanner/run`; exchange submission remains `POST /pulse/signals/{signal}/execute`. The server is authoritative for position limits, connection state, plan capabilities, risk checks and order calculations. A mobile client must never enable submission merely because its local form appears complete.

The execution ticket is marked ready only when the selected signal and ticket geometry are valid; the exchange connection has been tested; safe equity and available-balance metadata are current; the environment, plan, account and system gates permit manual execution; emergency stops are inactive; daily trade/loss and open-position limits have capacity; and the requested quantity passes risk and estimated-margin checks. API keys and secrets are never included in these payloads.

## Private Member API

Invitation-only Private Member accounts can read their assigned account summary and statements through `/private/*` endpoints. Authorization is enforced server-side.

## Response and security expectations

- Send `Authorization: Bearer <token>` for Sanctum-protected endpoints.
- Treat 401 as unauthenticated, 403 as inactive/unauthorized/capability denied, 404 as unavailable resource and 422 as validation failure.
- Never store Binance API secrets in application logs or mobile preferences.
- Use HTTPS only in production.
- Store bearer tokens in Keychain/Keystore secure storage in native applications.
- The mobile app should use `/bootstrap` and `/content/settings` for version/maintenance behavior rather than hard-coding CMS-controlled values.
