# ABS Flutter Mobile V1.4.1 — V15.7.4 API Coverage

Base URL: `/api/v1`

The mobile client remains server-authoritative: it does not create a parallel market-data,
signal, investment-accounting or Binance execution engine.

## Public

| Area | API |
|---|---|
| Bootstrap | `GET /bootstrap` |
| Rewarded Free Signal | `GET /pulse/free-signal/status`, `POST /pulse/free-signal/session`, `POST /pulse/free-signal/claim` |
| Registration / login | `POST /auth/register`, `POST /auth/login` |

Pending email activation: the mobile app keeps the registration token and exposes only public/basic features until verification. Login also sends `allow_unverified_basic_access: true` so a compatible backend can return a limited-access token for a pending account.

| Activation / password recovery | `POST /auth/activation/resend`, `POST /auth/forgot-password` |
| Market | `GET /market/overview`, `GET /market/movers`, `GET /market/chart/{symbol}` |
| ABS content | `GET /products`, `/news`, `/news/live`, `/research`, `/learning` and detail routes |
| Economic calendar | `GET /economic-calendar` |
| Legal / search | `GET /legal/{type}`, `GET /search` |
| Contact / newsletter | `POST /contact`, `POST /newsletter` |

The V1.4.1 Free Signal recovery chain preserves backend qualification first. If the public
flow returns no setup, the app can recover a persisted reveal, use the strongest active
member signal for an eligible Pulse member, or finally show **BTCUSDT 4H market context**
from `/market/chart/BTCUSDT` without issuing entry, stop-loss or take-profit levels.

## Authenticated account

| Area | API |
|---|---|
| Identity | `GET /auth/me` |
| Logout | `POST /auth/logout`, `POST /auth/logout-all` |
| Sessions | `GET /auth/sessions`, `DELETE /auth/sessions/{token}` |
| Account dashboard | `GET /dashboard` |
| Profile/security | `PATCH /profile` |
| Notifications | `GET /notifications`, `POST /notifications/{id}/read` |
| Preferences | `GET/PUT /notification-preferences` |
| Devices | `GET/POST /devices`, `DELETE /devices/{device}` |
| Watchlist | `GET/POST /watchlist`, `DELETE /watchlist/{symbol}` |

## Membership

| Area | API |
|---|---|
| Pulse access | `GET /pulse/access` |
| Membership | `GET /pulse/membership` |
| Quote | `POST /pulse/membership/quote` |
| Request | `POST /pulse/membership/requests` |
| Cancel request | `PATCH /pulse/membership/requests/{id}` |

## Pulse trading

| Area | API |
|---|---|
| Dashboard / usage | `GET /pulse/dashboard`, `GET /pulse/usage` |
| Pairs | `GET /pulse/pairs` |
| Readiness / ticket | `GET /pulse/execution/readiness`, `GET /pulse/execution/ticket` |
| Positions | `GET /pulse/positions` |
| Risk | `GET/PUT /pulse/risk-controls` |
| Settings | `GET/PUT /pulse/settings` |
| Scanner | `GET /pulse/scanner/overview`, `POST /pulse/scanner/run` |
| Signals | `GET /pulse/signals/overview`, `GET /pulse/signals/{id}` |
| Share / explain | `POST /pulse/signals/{id}/share`, `POST /pulse/signals/{id}/explain` |
| Validation / dismiss / execute | signal detail action routes |
| Trades | `GET /pulse/trades`, `GET /pulse/trades/{id}` |
| Close / sync | `POST /pulse/trades/{id}/close`, `POST /pulse/trades/sync` |
| Emergency stop | `POST /pulse/emergency-stop` |
| Orders | `GET /pulse/orders` |
| Reports | `GET /pulse/reports`, `/signals`, `/strategies`, `/simulation`, `/learning` |
| Market health / prices | `GET /pulse/market-data/health`, `GET /pulse/market-data/prices` |
| Binance | `GET/POST /pulse/binance/connections` + test/activate/delete |
| Alerts | `GET /pulse/alerts` + read/read-all |

## Private Investor — V15.7.4

| Area | API / behavior |
|---|---|
| Investor account | `GET /private/account` |
| Monthly statement | `GET /private/statements/{statement}` |
| Investor request | tries `POST /private/requests`, then `POST /private/account/requests` when exposed by the deployed backend |
| Request fallback | secure web investor portal if the mobile request route is not exposed |

The V1.4.1 investor UI can consume both the earlier compact account payload and richer
V15.7.x fields. It separates original-currency principal, USD-at-effective-date reporting,
agreed monthly rate/effective date, current-month progress, Profit Paid, Capital Withdrawal,
statements, transactions and requests whenever those fields are returned.

### Accounting semantics preserved from V15.7.4

- daily performance accrues from the agreed monthly rate;
- due months are settled idempotently under the backend schedule;
- **Profit Paid** is an external profit distribution with zero investor-capital effect;
- **Capital Withdrawal** means principal return only;
- completed due months can automatically create/reconcile statements;
- principal remains in its original currency/amount while ABS can retain the USD equivalent
  captured at the investment effective date for consolidated reporting.

Admin-only controls remain a web/admin responsibility and are not replicated as a mobile
administrator console.
