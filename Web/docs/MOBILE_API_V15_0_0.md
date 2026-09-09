# ABS V15.0.0 — Flutter / Web Backend Handoff

Release: **ABS V15.0.0 — Pulse Points, Gamification & Best Signal Build**

V15 keeps the existing ABS market-data, 15-strategy analysis, confidence/reliability, validation, learning and trade-safety engine. The mobile app must treat the backend as authoritative for market eligibility, strategy qualification, timeframes, thresholds, PP charging and rewards.

## Bootstrap

`GET /api/v1/bootstrap` reports backend build `15.0.0` and advertises the V15 modules `pulse_points`, `pulse_gamification`, `signal_sharing` and `ai_signal_explanations`.

## Pre-plan Pulse Points flow

Authenticated active accounts can call these endpoints even when they do not yet have Pulse plan access:

- `GET /api/v1/pulse/points` — wallet, XP, level, streak, packs, ledger, purchases, missions, achievements, PP-eligible plans and published USDT payment instructions.
- `POST /api/v1/pulse/points/purchases` — submit one PP pack for Admin USDT verification.
- `POST /api/v1/pulse/points/check-in` — daily idempotent PP/XP check-in.
- `POST /api/v1/pulse/points/plans/{plan}/activate` — activate or extend an eligible Pulse plan with PP. Send a stable `idempotency_key` when retrying a request.

PP purchase approval is Admin-controlled and exactly-once. Clients must never calculate or mutate PP balances locally.

## Best Signal scanner contract

After Pulse access is active, `POST /api/v1/pulse/scanner/run` is one action. Do not send user-selected pairs, strategies, signal thresholds or timeframes. The backend evaluates the Admin/package-approved market universe across 15M + 4H and ranks qualified candidates internally.

The response exposes only the successfully unlocked Best Signal. A signed-in scanner run stores sanitized evaluation statuses for other candidates, not unpaid entry/SL/TP/strategy evidence. When no candidate qualifies, `points_charged` is `0`. A newly unlocked winner is charged according to the active plan's `best_signal_points_cost` with an idempotent run debit.

## Signal social + AI

- `POST /api/v1/pulse/signals/{signal}/share` returns the backend-generated social payload and applies the once-per-signal reward rule.
- `POST /api/v1/pulse/signals/{signal}/explain` returns the cached/generated explanation when AI explanations are enabled and configured.

The client should display server responses as authoritative and must not expose internal strategy weights or hidden scanner candidate details.

## Compatibility and deployment

Existing authentication, subscriptions, Binance connection, execution, trades, reports, alerts and CMS APIs remain in place. Deploy the V15 backend first, verify `GET /api/v1/bootstrap`, then point Flutter builds at it. For shared hosting, the protected ABS schema-repair flow and `database/ABS_V15_0_0_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql` can add the V15 schema without deleting existing data.
