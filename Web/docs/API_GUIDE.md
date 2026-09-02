# ABS V14.9.2 Web + Flutter Mobile API Guide

Local base URL:

```text
http://127.0.0.1:8000/api/v1
```

The V14.9.2 API supports Pulse Trading Intelligence, the five premium member pages, live market awareness, ABS market news, shared accounts, mobile devices and invitation-only private-member reporting. Laravel Sanctum bearer tokens protect authenticated endpoints. Market endpoints return verified provider data or an explicit unavailable state. Practice mode is the default. Live and automatic execution remain disabled until the required platform, plan, account, connection and user controls are enabled.

> **V14.9.2 mobile handoff:** `GET /bootstrap` now declares `mobile_api_ready`, backend build `14.9.2`, the ABS-central market-data source, target refresh seconds and supported mobile modules. Website and Flutter clients use the same ABS API/data model.

The machine-readable contract is `docs/openapi.yaml`.

## Plan-driven capability model

Pulse plans are created and managed by administrators. Each plan is the maximum access envelope for a user and defines:

- visible Pulse sections (scanner, signals, orders, trades, reports, Binance connection, alerts, plan view and settings);
- mobile API eligibility;
- Practice, manual, Live and automatic trading eligibility;
- scanner, signal, trade, open-position and selected-market limits;
- enabled strategies.

`GET /pulse/access`, `GET /pulse/dashboard` and `GET /pulse/settings` return an `effective_capabilities` object so the future mobile app can hide unavailable controls before calling a restricted endpoint. Restricted routes also enforce the same capability server-side and return HTTP 403. User-level overrides can only disable features that a plan already includes; they cannot grant a feature excluded by the plan.

A clean local installation prepares **Pulse Trial**, **Pulse Intelligence** and **Pulse Professional**. Trial is an onboarding entitlement; the two paid memberships are public requestable plans after Admin publishes their commercial prices. Live and automatic execution still require the separate platform-wide safety switches to be enabled.

## Authentication

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
```

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/auth/register` | Register an ABS account |
| POST | `/auth/login` | Login and issue a Sanctum token |
| GET | `/auth/me` | Current authenticated user |
| POST | `/auth/logout` | Delete the current token |
| PATCH | `/profile` | Update profile or password |
| GET | `/dashboard` | Focused account dashboard payload |

## Public market and content endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/market/overview?refresh=1` | Core prices, changes, global metrics and source status |
| GET | `/market/movers?limit=5&refresh=1` | Liquid Binance USDT spot movers |
| GET | `/market/chart/BTCUSDT?interval=1h&limit=80&refresh=1` | Candlestick data |
| GET | `/products` | Pulse and Private Member Portal only |
| GET | `/news` | Published ABS market articles |
| GET | `/news/live` | Source-attributed external headlines |
| GET | `/news/{slug}` | One published ABS article |
| GET | `/legal/{type}` | Privacy, terms, risk or disclaimer content |
| GET | `/search?q=pulse` | Search active services and ABS market articles |
| POST | `/newsletter` | Subscribe to ABS updates |

## User account endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/watchlist` | Current user's watchlist |
| POST | `/watchlist` | Add a market symbol |
| DELETE | `/watchlist/{symbol}` | Remove a market symbol |
| GET | `/notifications` | Account notifications |
| POST | `/notifications/{id}/read` | Mark one notification read |

## Pulse access and plans

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/pulse/access` | Current Pulse access, plan and effective capabilities |
| GET | `/pulse/plans` | Public administrator-created memberships, limits and capability matrices |
| GET | `/pulse/membership` | Current membership, request history, assigned offers and authenticated payment configuration |
| POST | `/pulse/membership/quote` | Validate a plan/promotion code and return the current commercial quote |
| POST | `/pulse/membership/requests` | Submit a membership request with transaction reference and optional/required proof |
| PATCH | `/pulse/membership/requests/{membershipRequest}` | Cancel an owned open membership request |

The membership endpoints require `auth:sanctum` but do **not** require active Pulse access, allowing expired/no-access users to renew or request a plan. Protected Pulse routes below require active Pulse access.

## Pulse platform endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/pulse/dashboard?period=24h\|7d\|30d\|all` | Premium dashboard with performance, signal, risk and position summary |
| GET | `/pulse/pairs` | Available Binance USD-M markets |
| GET | `/pulse/scanner/overview` | Screen-ready scanner KPIs, results and data-quality summary |
| GET/PUT | `/pulse/settings` | User execution and risk settings |
| GET | `/pulse/scanner/runs` | Scanner history |
| POST | `/pulse/scanner/run` | Run the permission-checked scanner |
| GET | `/pulse/signals` | User signals |
| GET | `/pulse/signals/overview` | Screen-ready signal queue, evidence, risk and activity summary |
| GET | `/pulse/signals/{signal}` | One owned signal |
| PATCH | `/pulse/signals/{signal}/dismiss` | Dismiss an owned active signal and retain history |
| POST | `/pulse/signals/{signal}/execute` | Submit an eligible signal to Binance |
| GET | `/pulse/strategies/overview` | Strategy-family activity and contribution summary |
| GET | `/pulse/execution/readiness` | Manual execution capability/readiness gates |
| GET | `/pulse/execution/ticket` | Selected signal, ticket calculation and complete pre-trade checks |
| GET/PUT | `/pulse/risk-controls` | Read or update risk limits |
| GET | `/pulse/positions` | Local and exchange position snapshot |
| GET | `/pulse/orders` | Exchange orders and positions |
| GET | `/pulse/trades` | Trade records |
| GET | `/pulse/trades/{trade}` | One owned trade |
| POST | `/pulse/trades/{trade}/close` | Guarded reduce-only close |
| POST | `/pulse/trades/sync` | Synchronize exchange state |
| POST | `/pulse/emergency-stop` | Enable or clear user emergency stop |
| GET | `/pulse/reports` | Performance and execution report |
| GET/POST | `/pulse/binance/connections` | Read or save a user exchange connection |
| POST | `/pulse/binance/connections/{connection}/test` | Test credentials and account access |
| DELETE | `/pulse/binance/connections/{connection}` | Remove an exchange connection |
| GET | `/pulse/alerts` | Pulse alerts |
| PATCH | `/pulse/alerts/{alert}/read` | Read one alert |
| PATCH | `/pulse/alerts/read-all` | Read all alerts |

## Private Member Portal endpoints

These routes require an active Private Member Portal account assigned to the authenticated user.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/private/account` | Assigned account value, transactions and statements |
| GET | `/private/statements/{statement}` | One statement owned by the authenticated member |

## Pulse administration endpoints

Administrator-only endpoints are available under `/admin/pulse` for dashboard, access, plans, strategies, pairs, signals, trades, settings, alert broadcasts and audit logs. The full schemas and request bodies are documented in `docs/openapi.yaml`.

## Provider and security behavior

- Market prices and candles are not manufactured when providers fail.
- External news retains publisher attribution and original source URLs.
- Binance secrets are encrypted at rest and are never returned after saving.
- A user can access only their own connections, signals, trades, orders, statements and alerts.
- Live trading requires the platform setting, plan permission, user permission, an active Live connection and accepted risk settings.
- Automatic trading is disabled by default and requires the dedicated platform and account controls to be enabled.
