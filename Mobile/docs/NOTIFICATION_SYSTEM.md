# ABS Notification System

Status: **mobile client complete, backend detector not built yet.**

The Flutter app receives, routes, configures and displays notifications. It
does **not** detect market events. No client-side BTC monitor exists and none
should be added — a phone cannot be the source of truth for market rules.

## Split of responsibility

| Concern | Owner | Status |
|---|---|---|
| FCM token, topics, permission, preferences | Mobile | Done |
| Foreground display, deep-link routing | Mobile | Done |
| Remote Config defaults + parsing | Mobile | Done |
| Market feed monitoring, rule evaluation | **ABS backend** | Not built |
| Cooldown, dedupe, hourly cap enforcement | **ABS backend** | Not built |
| Sending FCM messages | **ABS backend** | Not built |
| Per-user targeting (security, expiry) | **ABS backend** | Not built |

Firebase is on the **Spark** plan and this task added **no Cloud Functions**.
The detector belongs in the existing ABS backend, which already owns the market
feed.

## Remote Config parameters

Both the app and the future backend read the same keys, so one change in the
Firebase console retunes both. Defined once in
`lib/notifications/notification_config.dart`.

| Key | Type | Default |
|---|---|---|
| `notifications_enabled` | bool | `true` |
| `market_fast_move_enabled` | bool | `true` |
| `market_fast_move_default_percent` | double | `3.0` |
| `market_fast_move_window_minutes` | int | `5` |
| `market_fast_move_cooldown_minutes` | int | `30` |
| `btc_fast_move_enabled` | bool | `true` |
| `btc_fast_move_percent` | double | `3.0` |
| `eth_fast_move_enabled` | bool | `true` |
| `eth_fast_move_percent` | double | `4.0` |
| `breaking_news_enabled` | bool | `true` |
| `qualified_signal_notifications_enabled` | bool | `true` |
| `entry_watch_notifications_enabled` | bool | `false` |
| `macro_alerts_enabled` | bool | `true` |
| `macro_alert_lead_minutes` | int | `30` |
| `daily_brief_enabled` | bool | `true` |
| `free_signal_ready_notifications_enabled` | bool | `false` |
| `package_expiry_notifications_enabled` | bool | `true` |
| `max_market_alerts_per_hour` | int | `3` |

The app clamps every value. A malformed or out-of-range server value falls back
to the in-app default rather than disabling alerts or letting a nonsense
threshold through.

## Backend detector flow

```
read Remote Config (same keys)
        ↓
monitor the central ABS market feed
        ↓
evaluate rule per symbol
        ↓
per-symbol cooldown + dedupe + global hourly cap
        ↓
send FCM (topic for broadcast, token for personal)
```

### Rapid market move rule (BTC example)

```
symbol:    BTCUSDT
trigger:   |price change| >= btc_fast_move_percent        (default 3%)
window:    market_fast_move_window_minutes                (default 5 min)
cooldown:  market_fast_move_cooldown_minutes per symbol   (default 30 min)
cap:       max_market_alerts_per_hour across all symbols  (default 3)
dedupe:    one alert per (symbol, window) even across workers
```

Copy must describe the market, never instruct a trade:

```
Title: BTC moved +3.2% in 5 minutes
Body:  Bitcoin is moving quickly. Open ABS for the latest market context.
```

Never "BUY NOW", "SELL NOW", "GUARANTEED", or any profit promise.

## FCM payload contract

Send `data` on every message. `notification` is optional but recommended so the
OS can draw it while the app is backgrounded.

```json
{
  "notification": { "title": "...", "body": "..." },
  "data": {
    "type":  "market_move",
    "route": "market_detail",
    "symbol": "BTCUSDT"
  }
}
```

### Types, routes and required arguments

| `type` | default `route` | argument | Topic (broadcast) |
|---|---|---|---|
| `market_move` | `market_detail` | `symbol` | `abs_market_alerts` |
| `qualified_signal` | `signal_detail` | `signal_id` | `abs_pulse_signals` |
| `entry_watch` | `signals` | — | `abs_entry_watch` |
| `macro_event` | `calendar` | `event_id` | `abs_macro_alerts` |
| `breaking_news` | `news` | `url` (optional) | `abs_breaking_news` |
| `daily_brief` | `pulse` | — | `abs_daily_brief` |
| `package_expiry` | `account` | — | **token only** |
| `security` | `account` | — | **token only** |

Client guarantees:

- A `type` can only reach routes on its own allowlist; a crafted `route` cannot
  redirect a payload somewhere unrelated.
- `signal_detail` without a valid `signal_id` degrades to the signals list;
  `market_detail` without a valid `symbol` degrades to the markets list.
- `symbol` must match `^[A-Z0-9]{2,20}$`; `url` must be absolute http(s).
- An unknown `type` opens the app and navigates nowhere.
- No route performs a trade, opens an execution ticket, or takes any
  irreversible account action.

### Personal notifications

`package_expiry` and `security` must be sent to **device tokens**, never a
topic. A topic would deliver another user's account event to this device and
leaks subscription membership. Topic names contain no emails, user ids or
package names.

## Alert contracts awaiting the backend

1. **Rapid market move** — BTC/ETH plus configurable symbols.
2. **Qualified Pulse signal** — only when the backend says the setup genuinely
   qualified. Entry Watch is a separate, default-off category.
3. **High-impact economic event** — `macro_alert_lead_minutes` before a
   backend-designated high-impact release (CPI, PPI, FOMC).
4. **Breaking market news** — backend-designated high significance only.
5. **Daily ABS Brief** — one scheduled market summary. Never a re-engagement
   nudge.
6. **Package expiry** — e.g. 3 days and 1 day out, backend-controlled.
7. **Security / login** — new device or session, security-relevant changes.
   This category is mandatory and cannot be switched off in the app.

## Manual sending

Firebase Console → Messaging → Notifications campaign works without a rebuild.
Add custom data keys (`type`, `route`, …) in "Additional options" to exercise
deep links. Without them the campaign simply opens the app.

In debug builds the FCM registration token is shown and copyable at
**More → Push Alerts** for Console → "Send test message".
