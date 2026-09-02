# ABS V14.8.2 — Production Upgrade, Binance Futures Market Catalog & Mobile API Parity

## Release scope

ABS V14.8.2 is a production-upgrade release for the finalized premium Pulse authenticated experience. It preserves the approved Dashboard, Market Scanner, Signals, Strategies, Trade Execution and remaining Pulse user-page family while adding production release management, package-controlled market/strategy access and complete web/mobile contract parity.

## Binance Futures markets

- Pulse synchronizes active Binance USD-M `PERPETUAL` markets from exchange information.
- Supported quote assets default to `USDT,USDC` and are configurable with `PULSE_BINANCE_QUOTE_ASSETS`.
- Markets no longer reported as active perpetual contracts are disabled after synchronization.
- Admin can synchronize the market catalog from **Admin → Pulse → Trading Pairs**.
- `abs:pulse-pairs --environment=live` is scheduled every six hours when the Laravel scheduler is running.
- Plans can use `all` market access or a selected subset.
- Users can only save, scan and execute markets permitted by their active plan.
- The user market selector is searchable, quote-filterable and respects each plan's maximum selected-pair limit.

## 15-strategy catalog

The canonical catalog contains exactly the reviewed 15 strategy engines. The production migration inserts any missing canonical strategies without overwriting existing administrator settings. A legacy plan with no strategy assignments is initialized with the complete catalog once; administrators can then reduce plan assignments.

Web and Mobile APIs expose the complete catalog with `included` / `locked_by_plan` state. Scanner and signal filters use the strategy engines assigned to the user's package.

## Binance Testnet and Live Futures

Users can save separate Testnet and Live USD-M Futures API connections and explicitly activate either verified connection. Live trading requires all of the following:

1. Admin global **Live Trading** switch enabled.
2. Current Pulse plan includes Live Trading.
3. User has selected the Live environment.
4. Binance Live API connection has passed the trading-permission test.
5. Normal risk, position, emergency-stop and execution safeguards pass.

`PULSE_DISABLE_LIVE_TRADING=true` is an optional server-level emergency kill switch. The default is `false`, allowing Admin settings to remain the primary operational control. Withdrawals are not part of Pulse execution.

## Mobile API parity

All user-facing Pulse functionality is exposed under `/api/v1` and protected by Sanctum, active-account, active-Pulse-access and package `mobile_api` capability checks. The API includes:

- authentication, sessions, profile, notifications, devices and watchlist;
- Pulse access, plans, membership quotes and membership requests;
- Dashboard;
- plan-aware Binance Futures pair catalog and selections;
- complete 15-strategy catalog plus strategy overview;
- Market Scanner runs and screen overview;
- Signals, evidence, dismissal and execution;
- execution readiness and ticket calculation;
- Open Positions, orders, Trade History, close and sync;
- Risk Controls and Settings;
- Reports & P&L;
- Binance Testnet/Live connection save, test, activate and delete;
- Alerts and notification state.

Admin Pulse APIs also support plan strategy assignments and `all`/`selected` pair access with pair IDs. `docs/openapi.yaml` is the release contract.

## Production System Updates & Rollback

After the one-time deployment of V14.8.2, future ABS release ZIPs can be installed from **Admin → System Updates & Rollback**.

### Upgrade flow

1. Upload a complete ABS release ZIP.
2. ABS validates the archive and blocks protected paths such as `.env`, `storage`, `vendor`, `node_modules` and `.git`.
3. Before installation, ABS automatically creates:
   - a MySQL database backup;
   - an uploads backup;
   - an application-code snapshot;
   - a combined full restore point with SHA-256.
4. Managed application files are synchronized to the staged release; obsolete managed files are removed while server/runtime state stays protected.
5. Database migrations run with `--force` and caches are cleared.
6. If deployment or migrations fail, ABS automatically restores the pre-upgrade application, database and uploads.
7. If a release installs but later proves unsuitable, Admin can select a restore point and type `ROLLBACK` to return to the previous working code + DB + uploads. A safety restore point of the current state is created before manual rollback.

The live `.env`, APP_KEY, server database credentials and encrypted secret key remain on the production server and are not replaced by an update package.

## Release-package rule

Future Admin-uploadable ABS release ZIPs must contain the application root (`artisan`, `composer.json`, `BUILD_VERSION.txt`, `app/`, `routes/`, `resources/`, `database/migrations/`) and must not contain `.env`, `storage/`, `vendor/`, `node_modules/` or `.git/`.
