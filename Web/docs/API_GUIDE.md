# Alpha Block Solutions API Guide — V15.1.6

ABS exposes the Laravel backend at `/api/v1` for the website and the upcoming mobile app. See `docs/openapi.yaml` for the complete operation list and `docs/MOBILE_API_V15_1_6.md` for the mobile handoff.

## Commerce
Paid Pulse access uses **direct USDT → transaction reference/proof → Admin verification → package activation**. There is no active customer Sparks/points wallet and no per-signal points charge.

## Anonymous rewarded Free Signal
The public website remains available at `/pulse/free-signal`. V15.1.6 also exposes a guest API protocol:

- `GET /api/v1/pulse/free-signal/status`
- `POST /api/v1/pulse/free-signal/session`
- `POST /api/v1/pulse/free-signal/claim`

A qualified public/system signal is preferred. If no setup reaches the qualification threshold, ABS may return the highest-scoring evaluated LONG/SHORT setup as **ENTRY WATCH**, explicitly not a qualified signal. If neither exists, no ad/cooldown should be consumed.

The current deployed rewarded creative uses Google Ad Manager rewarded web inventory. Native Flutter rewarded-ad SDK/SSV wiring is part of the upcoming mobile-client implementation; the Laravel data/claim protocol is ready for that integration.

## Pulse mobile reporting
Authenticated members with report capability can use:

- `/pulse/reports`
- `/pulse/reports/signals`
- `/pulse/reports/strategies`
- `/pulse/reports/learning`
- `/pulse/reports/simulation`

The V15.1.6 simulation is a research-only equal-risk/equal-notional model based on resolved validated signals. It is not a forecast, a guaranteed robot result or an account backtest.

## Operations
The central one-minute pipeline is: market data → signal validation → Binance trade reconciliation. Strategy learning is rebuilt daily. Use `php artisan abs:pulse-execution-check` and `php artisan abs:production-check` before/after deployment to verify live environment health.
