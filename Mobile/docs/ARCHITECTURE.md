# ABS Mobile Architecture

## Market data

```text
Binance Futures public market data
        |
        | central ABS scheduled collection (~1 minute)
        v
ABS MySQL central prices + candles
        |
        +--> ABS Website
        |
        +--> ABS /api/v1
                 |
                 +--> Flutter Mobile
```

Flutter never requires direct Binance public-market calls.

## Trading

```text
Flutter
   |
   | HTTPS + Sanctum
   v
ABS execution/readiness API
   |
   | server-side package/risk/environment checks
   v
ABS Binance Futures service
   |
   +--> Binance Testnet or LIVE
   |
   +--> ABS trade/order/protection/reconciliation records
```

The application displays the server state; it does not independently infer that an exchange order filled.

## Credential handling

- Sanctum token: mobile secure storage.
- Binance key/secret: transmitted to ABS only when creating/updating a connection.
- Binance secret: never returned by ABS and never stored as application configuration.
- Laravel/database/mail credentials: server only.

## Entitlements

Pulse capabilities are returned and enforced by the V15.1.6 backend. Mobile screens improve UX, but server middleware remains authoritative for scanner, rewarded Free Signal cooldown/claim, signals, orders, trading, reports, Binance and settings access.

## Adaptive trader experience

The mobile presentation layer has two user-selectable modes:

```text
Simple mode
  -> guided next steps
  -> reduced metric density
  -> plain-language explanations
  -> professional details available on demand

Pro mode
  -> denser dashboard metrics
  -> faster signal/position review
  -> full professional context visible by default
```

This preference is stored locally with secure storage. It changes presentation only. It never changes package entitlements, risk checks, Binance permissions, execution readiness, or server-side trading rules.
