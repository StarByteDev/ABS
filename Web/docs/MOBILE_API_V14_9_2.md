# ABS V14.9.2 — Flutter/Web Backend Handoff

ABS V14.9.2 is the backend release to use for production Flutter application development. The website and mobile app share the same users, subscriptions, Pulse entitlements, central market prices, scanner/signals, Binance connection state, execution/trade lifecycle, CMS content and notification infrastructure.

## Architecture

`Binance Futures -> ABS 1-minute scheduled ingestion -> ABS MySQL -> Website + /api/v1 -> Flutter`

The Flutter client must read market data from ABS APIs. It must not call Binance public market endpoints directly and must never store Binance API secrets as an application-wide mobile configuration value. Per-user Binance credentials are submitted to authenticated ABS endpoints and remain encrypted/server-side according to the existing Binance connection service.

## Mobile-ready API areas

- App bootstrap/version/maintenance configuration
- Registration, activation, login, logout, session management and password recovery
- Account dashboard/profile/notification preferences
- Device registration and push-token storage
- Public ABS market overview, movers and charts
- Watchlist
- Products/services, news/live news, research, learning and economic calendar
- Legal content, contact and newsletter
- Pulse access, packages, subscription quote/request/history
- Pulse dashboard, usage, pairs and strategies
- Scanner run/history/overview
- Signal list/detail/validation/dismiss/execution ticket
- Binance Testnet/Live connections, connection verification and activation
- Risk controls and Pulse settings
- Positions, orders, trade history/detail, trade sync and guarded close
- Emergency stop
- Signal/strategy/learning reports
- Alerts
- Private Member account/statements when entitled

## Bootstrap contract

`GET /api/v1/bootstrap` returns:

- `build: 14.9.2`
- `mobile_api_ready: true`
- central ABS market-data source description
- target market refresh seconds
- minimum/recommended mobile application versions
- mobile maintenance controls
- supported mobile modules
- public Pulse plan catalog and legal links

## Administrator event alerts

Both website registration and mobile API registration trigger the same administrator event email. Website and mobile package-subscription submissions trigger the same administrator subscription email.

Default recipient: `i@armansabir.com`.

Change it under **Admin -> Email Communications**. Registration and subscription alerts have independent Enabled/Disabled controls.

## Production rule

The mobile app should treat ABS as the authoritative application backend. Exchange-confirmed position/order state remains server-side. A mobile UI action must never locally mark a trade filled/closed before ABS reports the confirmed server/exchange state.
