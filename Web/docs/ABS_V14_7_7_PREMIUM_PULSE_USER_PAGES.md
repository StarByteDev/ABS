# ABS V14.7.7 — Premium Pulse User Pages + Mobile API Parity

**Release date:** 24 August 2026  
**Baseline:** ABS V14.7.6  
**Database migration:** None

V14.7.7 implements the five approved authenticated Pulse pages in the existing Alpha Block Solutions identity. It keeps the approved logo, navy/cyan/gold theme, page density, professional decision-support language and 1676×939 desktop composition while replacing display-only values with authenticated database and exchange state.

## Completed member pages

| Page | Web route | Primary authenticated data |
|---|---|---|
| Dashboard | `/pulse/dashboard` | Period P&L, closed outcomes, executed trades, signal activity, account standing, risk/exposure and open positions |
| Market Scanner | `/pulse/scanner` | Selected markets, latest scanner run, real signal setups, scanner coverage and market-condition summary |
| Signals | `/pulse/signals` | Active/history filters, selected evidence, risk snapshot, engagement and dismiss/review/execution actions |
| Strategies | `/pulse/strategies` | Momentum, Breakout and Reversal activity, contribution, risk settings and evaluation health |
| Trade Execution | `/pulse/execution` | Selected signal, ticket defaults, order calculations, account balances, recent orders and server-side safeguards |

The shared sidebar includes Dashboard, Market Scanner, Signals, Strategies, Trade Execution, Open Positions, Trade History, Risk Controls, Alerts & Watchlists, Reports & P&L, Binance Connection and Settings. It can be collapsed or expanded; the preference is remembered and collapsed labels remain accessible through tooltips. Links are capability-aware so a user is not sent to a route excluded by their plan.

## Mobile API parity

The five premium pages use one shared page-data service with the mobile API, preventing calculation drift between the web and native clients.

| Mobile view/action | API contract |
|---|---|
| Premium dashboard | `GET /api/v1/pulse/dashboard?period=24h|7d|30d|all` |
| Scanner overview | `GET /api/v1/pulse/scanner/overview` |
| Run scanner | `POST /api/v1/pulse/scanner/run` |
| Signal queue/detail | `GET /api/v1/pulse/signals/overview` |
| Dismiss signal | `PATCH /api/v1/pulse/signals/{signal}/dismiss` |
| Strategy overview | `GET /api/v1/pulse/strategies/overview?period=7d|30d|90d|all` |
| Execution ticket | `GET /api/v1/pulse/execution/ticket?signal_id={id}` |
| Execution readiness | `GET /api/v1/pulse/execution/readiness` |
| Submit eligible order | `POST /api/v1/pulse/signals/{signal}/execute` |

The complete V14.7.7 OpenAPI contract contains 92 paths and 105 operations, covering authentication, account/profile, devices, public market/content, Pulse pages and actions, Private Member access and administrator operations.

## Execution safeguards

- Binance API keys and secrets remain encrypted and hidden from serialized web/API responses.
- A successful connection test stores only safe readiness metadata: trade permission, account equity and available balance.
- Submission requires a current connection test, valid signal, allowed environment, manual-trading permission, enabled system execution, manual mode, no emergency stop, position capacity, daily trade/loss capacity, valid entry/stop/target geometry, sufficient balance and configured risk compliance.
- Limit orders carry validated `timeInForce`; an optional Binance-compatible client reference is supported.
- New signal entries cannot set Reduce Only.
- Live and automatic trading stay behind the pre-existing installation, plan, account and user gates.

After upgrading, test the Binance connection once to refresh the safe equity/balance metadata required by the strengthened order guard.

## Link, route and visual QA

`node scripts/static-release-audit.mjs` verifies:

- every literal named web route referenced by the 79 Blade templates;
- linked local assets and cross-page fragments;
- controller classes and methods registered by web/API routes;
- dynamic-route ordering that could shadow static endpoints;
- customer-facing terminology and placeholder link/action rules;
- all 105 `/api/v1` operations against `docs/openapi.yaml`.

`node tests/Visual/verify-premium-contract.mjs` regenerates five self-contained production-style previews and verifies the approved header/sidebar/content geometry, required sections and copy, unchanged logo checksum, no purple premium-page palette, score terminology, decision-support language and external-asset independence. Approved references use a 1676×939 canvas.

Server/runtime acceptance commands:

```bash
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:view-audit
php artisan test --filter=PulsePremiumPagesApiTest
node scripts/static-release-audit.mjs
node tests/Visual/verify-premium-contract.mjs
```

## Upgrade

1. Preserve the production `.env`, `APP_KEY`, database and user uploads.
2. Replace the application files and public assets with V14.7.7.
3. Run `php artisan optimize:clear`.
4. Run the server/runtime acceptance commands above.
5. Test each saved Binance connection once before opening an execution ticket.

No destructive database rebuild or migration is required.
