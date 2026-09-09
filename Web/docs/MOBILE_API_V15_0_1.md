# ABS V15.0.1 — Flutter / Web Backend Handoff

Backend build: **15.0.1**

Use `GET /api/v1/bootstrap` as the mobile release/config source. Website and Flutter share the same authenticated PP wallet and Pulse entitlement state.

## Mobile flow

1. Sign in/register with ABS.
2. `GET /pulse/points` to show PP balance, packs and PP-priced plans. This works before the user owns a paid Pulse plan.
3. If PP is needed, submit `POST /pulse/points/purchases` with the selected pack and USDT transaction reference/proof. Admin approval credits PP exactly once.
4. Activate a plan via `POST /pulse/points/plans/{plan}/activate` with an idempotency key.
5. With active Pulse access, call `POST /pulse/scanner/run` with **no pair/strategy/timeframe/threshold selection**.
6. Display the returned single Best Signal when present. If none qualifies, show `0 PP charged`.
7. Use signal share / optional AI explanation endpoints as enabled.

Do not build UI for user-selected scanner pairs, strategy toggles, timeframe selection or personal signal thresholds. These are Admin/package controls in V15.0.1.

Direct-USDT plan checkout is disabled. USDT appears only in PP pack purchase screens.

## Rewarded ads

Rewarded-ad PP is **OFF by default**. When Admin enables it and the server has `PULSE_REWARDED_AD_SECRET`, a verified completion can be submitted to `POST /pulse/points/rewarded-ad` with `provider_reference` and the server/provider signature. The backend verifies the HMAC and uses the provider reference as an idempotent reward key; unsigned or replayed/unverified completions cannot create extra PP.
