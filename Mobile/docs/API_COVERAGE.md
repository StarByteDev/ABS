# ABS Flutter Mobile — V14.9.2 API Coverage

Base URL: `/api/v1`

## Public

| Area | API |
|---|---|
| Bootstrap | `GET /bootstrap` |
| Registration | `POST /auth/register` |
| Login | `POST /auth/login` |
| Activation resend | `POST /auth/activation/resend` |
| Forgot password | `POST /auth/forgot-password` |
| Market overview | `GET /market/overview` |
| Market movers | `GET /market/movers` |
| Market chart | `GET /market/chart/{symbol}` |
| Services | `GET /products` |
| News | `GET /news`, `GET /news/live`, `GET /news/{slug}` |
| Research | `GET /research`, `GET /research/{slug}` |
| Learning | `GET /learning`, `GET /learning/{slug}` |
| Calendar | `GET /economic-calendar` |
| Legal | `GET /legal/{type}` |
| Search | `GET /search` |
| Newsletter | `POST /newsletter` |
| Contact | `POST /contact` |

Password-reset links are sent by the backend email workflow and can complete on the ABS secure web reset page. The API `POST /auth/reset-password` remains available for future universal/deep-link handling.

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
| Dashboard | `GET /pulse/dashboard` |
| Usage | `GET /pulse/usage` |
| Pairs | `GET /pulse/pairs` |
| Strategies | `GET /pulse/strategies`, `GET /pulse/strategies/overview` |
| Readiness | `GET /pulse/execution/readiness` |
| Ticket | `GET /pulse/execution/ticket` |
| Positions | `GET /pulse/positions` |
| Risk | `GET/PUT /pulse/risk-controls` |
| Settings | `GET/PUT /pulse/settings` |
| Scanner | `GET /pulse/scanner/overview`, `POST /pulse/scanner/run` |
| Signals | `GET /pulse/signals/overview`, `GET /pulse/signals/{id}` |
| Validation | `GET /pulse/signals/{id}/validation` |
| Dismiss | `PATCH /pulse/signals/{id}/dismiss` |
| Execute | `POST /pulse/signals/{id}/execute` |
| Trades | `GET /pulse/trades`, `GET /pulse/trades/{id}` |
| Close/sync | `POST /pulse/trades/{id}/close`, `POST /pulse/trades/sync` |
| Emergency stop | `POST /pulse/emergency-stop` |
| Orders | `GET /pulse/orders` |
| Reports | `GET /pulse/reports`, `GET /pulse/reports/learning` |
| Market health | `GET /pulse/market-data/health` |
| Central prices | `GET /pulse/market-data/prices` |
| Binance | `GET/POST /pulse/binance/connections` + test/activate/delete |
| Alerts | `GET /pulse/alerts` + read/read-all |

## Private Member

| Area | API |
|---|---|
| Account | `GET /private/account` |
| Statement | `GET /private/statements/{statement}` |

Admin APIs remain a web/admin responsibility and are intentionally not exposed as a mobile administrator console in V1.1.
