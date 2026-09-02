# Mobile API — ABS V14.8.20

V14.8.20 keeps all V14.8.17+ mobile endpoints and aligns mobile execution readiness with the simplified web experience.

## Execution readiness

`GET /api/v1/pulse/execution/readiness` no longer requires `execution_mode=manual` for an explicit manual signal trade. The response includes:

- `ready` — true when plan access, environment, Binance connection, system execution and Emergency Stop checks pass.
- `managed_setup` — true when the account is still signal-only but ABS can enable guided manual execution automatically on the first confirmed trade.
- `next_step` — `open_trade`, `binance_connection`, `plans`, `risk_controls`, or `environment`.

`POST /api/v1/pulse/signals/{signal}/execute` uses the same managed execution behavior through `PulseTradeService`; mobile clients do not need to send a separate Settings update before a user-confirmed signal order.

All central market-data, signal-validation, reporting and learning APIs remain unchanged.

## Web onboarding parity

The web Binance onboarding now verifies credentials immediately after save and automatically activates the first successful connection. Mobile clients should present the same customer concept: connect once, verify once, then use guided Open Trade without a separate execution-mode setup step.
