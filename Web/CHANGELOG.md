# ABS V14.9.2 — Mobile/Web Backend Parity & Admin Event Notifications

- Prepared the ABS backend as the canonical shared backend for both the production website and the native Flutter mobile application.
- Updated `/api/v1/bootstrap` release metadata to V14.9.2 and added explicit mobile-readiness, central-market-data and supported-module metadata.
- Preserved full mobile API coverage for authentication, account/dashboard, market data, watchlist, CMS/news/research/learning/calendar, Pulse packages, scanner, signals, strategies, Binance connection management, execution readiness, positions, orders, trades, reports, alerts, notifications, devices and Private Member reporting.
- Added administrator email alerts for every self-service user registration from web or mobile API.
- Added administrator email alerts for every Pulse package subscription request from web or mobile API, including automatically approved voucher requests.
- Default administrator event recipient is `i@armansabir.com`.
- Added Admin → Email Communications controls to change the notification recipient and independently enable/disable new-registration and new-subscription alerts.
- Added idempotent migration defaults for the administrator event-notification settings without overwriting existing administrator choices.
- Added `database/ABS_V14_9_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql` for phpMyAdmin. It creates only missing tables/columns and does not drop, truncate or delete existing business data.
- Preserved V14.9.1 safe user lifecycle/schema repair and V14.9.0 central 1-minute ABS market-price architecture.
- No Binance API credentials are exposed to the mobile bootstrap or public CMS endpoints.

# ABS V14.9.1 — User Lifecycle Schema Repair Hotfix

- Fixed upgrade login failure on existing databases where `users.deleted_at` had not yet been added.
- Added `users.deleted_at` to protected schema diagnosis and non-destructive `abs:repair`.
- Added `deleted_at` to fresh users-table creation and the complete MySQL create/repair SQL.
- Existing V14.9.0 migration remains the canonical Laravel migration path.
- No Binance execution, TP/SL, signal, subscription, or trade-history logic changed.

# ABS V14.8.22 — Protected Database Self-Repair & Shared-Hosting Recovery

## Added / changed

- Added protected browser-based schema repair for users without hosting Terminal access.
- Added `POST /api/recovery/repair`, protected by `ABS_RECOVERY_KEY` and throttled to five attempts per minute.
- Replaced Terminal-command instructions on the Setup Required page with one **Fix Missing Tables & Columns** action.
- Repair is non-destructive and invokes normal `abs:repair` without `--seed`, preserving existing application data and business configuration.
- Added direct private `.env` recovery-key fallback for shared-hosting deployments with stale Laravel config cache.
- Added before/after schema verification and user-visible repair result.
- Added repair option to the Advanced Recovery page.
- Added a phpMyAdmin-compatible complete schema create/repair SQL fallback in the package.
- Preserved V14.8.21 HostGator scheduler QA, V14.8.20 guided trading UX and V14.8.17 central price/validation/learning architecture.

**Database:** no new migration; this release improves how existing required schema is reconciled.

---

# ABS V14.8.21 — Full Application QA & HostGator Shared Scheduler

## Fixed / hardened

- Corrected `abs:about` release identity and aligned API/OpenAPI build metadata.
- Added HostGator Shared/Baby scheduler support using a 15-minute Laravel cron cadence.
- Added profile-aware central price freshness tolerance for shared hosting.
- Prioritized central scanner candle ingestion around user-selected and recent-signal markets to reduce shared-hosting HTTP load.
- Added `abs:scheduler-check` and expanded `abs:production-check` to exercise scheduler, public market, central ingestion and signal-validation paths.
- Preserved V14.8.20 guided execution, V14.8.19 single Open Trade UX, V14.8.18 runtime hardening and V14.8.17 price/validation/learning architecture.
- Added HostGator cron setup and V14.8.21 mobile/API notes.

**Database:** no new V14.8.21 migration.

---

# ABS V14.8.20 — Guided Self-Configured Trading UX

## Changed

- Removed the requirement to manually switch Pulse Settings to Manual before an eligible user can execute a signal.
- Explicit Confirm & Open Trade now activates managed manual signal execution for signal-only accounts when the plan permits it.
- Automatic execution mode is preserved if already enabled.
- Open Trade readiness now uses real execution prerequisites rather than the Settings execution-mode selector.
- Removed the Open Trade `Enable Trading` redirect to Settings.
- Added directed setup states for Binance connection, plan access, Emergency Stop, environment availability and platform-level execution pause.
- Added Managed Setup messaging in the Open Trade drawer.
- Added automatic refresh of Binance permission/balance snapshot before order submission when stale or incomplete.
- Simplified first-time Binance onboarding: Save & Verify runs the connection test immediately and automatically selects the first successful connection.
- Successful signal execution returns to Pulse Signals with monitoring guidance instead of navigating to a separate trade-detail page.
- Simplified Pulse Settings into Pulse Preferences with managed-default guidance and collapsed advanced execution controls.
- Mobile execution-readiness response now includes managed setup and next-step guidance.

## Preserved

- V14.8.17 central market-price, 15M/4H candle, 1M validation, immutable signal, learning/reporting and mobile API architecture.
- V14.8.18 runtime hardening and V14.8.19 single Open Trade execution surface.
- Binance risk, margin, plan quota, environment, TP/SL and Emergency Stop safeguards.

**Database:** no new V14.8.20 migration.

---

# ABS V14.8.19 — Simplified Premium Pulse UX & Single Open-Trade Flow

## Changed

- Fixed Pulse Strategies overlap by removing fixed-height strategy catalog/bottom layouts.
- Simplified strategy catalog cards and combined Controls + Health into one compact operational card.
- Removed the standalone Trade Execution item from the member sidebar.
- `/pulse/execution` now redirects to the Signals Open Trade drawer for backward compatibility.
- Removed the Advanced execution link and second execution-page workflow.
- Simplified the Open Trade drawer to essential trade levels, sizing, environment and max-loss information.
- Added one final execution action: `Confirm & Open Trade`.
- Execution setup gaps now route to Plans, Binance Connection or Settings from inside the same drawer.
- Execution failures reopen the same signal/drawer with a readable error message.
- Reduced Signals page density by removing R:R from the queue, reducing summary metrics and removing duplicate activity detail.
- Preserved central price architecture, immutable signal validation, learning/reporting, mobile API and Binance safety checks.

**Database:** no new V14.8.19 migration.

---

# ABS V14.8.18 — Full-Site Stability, Dashboard Runtime & API Compatibility Build

## Fixed

- Fixed `/pulse/dashboard` runtime crash caused by uninitialized `$scannerCapabilities` in `PulsePageDataService::dashboard()`.
- Fixed the companion uninitialized `$scannerConnectionReady` dashboard variable on the same payload path.
- Added controlled schema-readiness behavior for central market-data reads/health so missing V14.8.17 tables return recovery guidance rather than raw SQL errors.
- Added graceful no-schema empty states to user Pulse Reports, Admin Signal Intelligence and mobile signal-intelligence endpoints.
- Restored `GET /api/v1/pulse/reports` backward compatibility by retaining the legacy `data.summary` trading object alongside `data.trading`.
- Restored cumulative revision history in the main README.
- Expanded the packaged static release audit to verify controller `view(...)` targets and literal Blade `@extends`/`@include` targets across the site.
- Added a V14.8.18 stability/architecture-preservation contract and final validation report for repeatable release checks.

## Preserved

- V14.8.17 central price/signal-intelligence architecture, 15M/4H scanner candle model, 1M validation, reporting and mobile APIs.
- Strategy engine identity remains `engine-14.8.17-*`; this stability build does not create a false learning/version split.
- Existing Binance execution, risk, permission and TP/SL protection logic.

**Database:** no new V14.8.18 migration. Existing installations still need the V14.8.17 schema via `php artisan abs:repair --seed`.

---

# ABS V14.8.17 — Central Price, Signal Intelligence, Reporting & Mobile API Build

## Added

- Central scheduled Binance Futures ticker storage for enabled Pulse markets.
- Central rolling 15M/4H candle buffers plus short-retention 1M validation candles.
- Market-data run health/audit table and mobile health endpoint.
- Immutable signal strategy/version, TP levels, technical/reliability/confidence snapshots and fingerprint.
- Future-candle signal validator with entry-before-outcome enforcement and same-minute ambiguity protection.
- MFE, MAE, R-multiples, duration and highest TP-level outcome fields.
- Permanent daily user signal metrics, strategy/version/timeframe/direction aggregates and evidence-protected strategy learning state.
- Pulse Reports signal-intelligence, strategy-performance, learning-state and validation sections.
- Admin Pulse Signal Intelligence reporting for platform-level deduplicated quality, learning evidence and central ingestion health.
- Mobile API endpoints for central prices, feed health, signal validation, signal reporting, strategy reporting and learning insights.
- Console/scheduler jobs: `abs:pulse-market-data`, `abs:pulse-validate-signals`, `abs:pulse-learning`.
- Direct schema-repair support for all new V14.8.17 tables/columns for existing ABS deployments.

## Changed

- User scans now read central stored prices/candles and do not trigger Binance public market-data fetches per user.
- Supported scanner execution timeframes are aligned to the approved architecture: 15M, 4H, or both in one `all` scan.
- Existing active signals are immutable across later scans.
- Scanner strategy-bundle identity includes engine build `14.8.17` to keep learning/version evidence separated when engine logic changes.

## Retention defaults

- 1M candles: 2 days; 15M: 21 days; 4H: 180 days.
- Resolved detailed signal validation: 7 days.
- Daily aggregate reporting and learning state: permanent.

## Deployment

This release **does require schema reconciliation**. Run `php artisan abs:repair --seed` after upload and configure `php artisan schedule:run` once per minute.

---

# ABS V14.8.16 — Right-Side Execution, Multi-Timeframe Scanner & Live Status Build

## Changed
- Moved the desktop Signal Execution / Open Trade drawer to the right side. Mobile remains a bottom sheet.
- Fixed the execution footer so hidden actions cannot leak into the layout and all valid action buttons remain fully visible.
- Restored a dedicated Status column to Scanner Results and Pulse Signals. Status now shows the live signal stage (Entry Ready, Move in Progress, Entry Watch, Trade Open or Signal Closed), while Action remains a separate execution control.
- Fixed the scanner timeframe bug: Run Market Scan was hard-coded to 1H even while the UI showed 1H · 4H.
- Selecting All/1H · 4H now performs one quota-safe scan across both 1H and 4H and preserves the actual timeframe on every result and saved signal.
- 1H and 4H signals for the same pair are stored/updated independently instead of overwriting each other.
- The scan run follows the currently selected Timeframe filter even if the user changes it immediately before pressing Run Market Scan.
- Existing live-price signal staging and Binance execution/risk/TP-SL validation remain unchanged.

**Database:** no migration is required for V14.8.16.

# ABS V14.8.15 — Live Price-Driven Signal Action & Open Trade Build

## Changed
- Removed Risk / Reward from Scanner Results to keep the execution table compact.
- Removed the static scan-sequence based “New Signal” action logic.
- Signal action state is now calculated from a fresh Binance Futures ticker price against Entry, Entry Zone, Stop Loss and Take Profit.
- Active signal stages are now Entry Ready, Move in Progress and Entry Watch; an existing Binance/Pulse trade becomes Trade Open; expired/TP/SL-boundary signals become Signal Closed.
- Every saved scanner signal has a clickable action, including expired/closed signals, so the left-side trade panel can always explain the current state.
- Every valid signal keeps an Open Trade action. The left-side panel recommends the appropriate Binance LIMIT execution approach for the current price stage.
- Entry Ready recommends the planned LIMIT entry; Move in Progress recommends waiting for a pullback to the original signal entry instead of chasing; Entry Watch allows the planned LIMIT entry with an explicit review warning.
- Trade Open routes users to manage the existing trade rather than create a duplicate; Signal Closed blocks submission but still opens the review panel.
- Scanner action state refreshes every 20 seconds using the lightweight scanner refresh endpoint. This does not rerun strategies or consume scan quota.
- Existing PulseTradeService risk, balance, permission, leverage, quantity and TP/SL validation remains unchanged.

**Database:** no migration is required for V14.8.15.

# ABS V14.8.14 — Professional Signal Action & Binance Open Trade UX Build

## Changed
- Removed Last Price from Scanner Results.
- Added Risk / Reward directly to Scanner Results.
- Removed the confusing Signal Limit text from the Action column.
- Simplified scanner actions to one professional action per signal.
- Updated lifecycle actions to Review Signal → Open Trade → Monitor Trade → View Result.
- Scanner actions now open the selected signal's left-side trade guidance panel.
- Added Recommended Binance Execution guidance in the Open Trade panel.
- Entry Confirmed recommends a Binance Futures LIMIT order at the signal entry.
- New Signal recommends waiting for entry confirmation; Trade Active recommends monitoring rather than chasing; Signal Closed recommends no new entry.
- Binance execution remains protected by the existing Pulse risk, balance, permission and TP/SL validation.

**Database:** no migration is required for V14.8.14.

# ABS V14.8.13 — Professional Signal Actions & Left-Side Trade Execution Build

## Changed
- Removed Strategy columns from Scanner Results and Pulse Signals.
- Added visible Entry Price, Stop Loss and Take Profit to Scanner Results; Pulse Signals now uses a single Entry Price column.
- Reworded signal lifecycle and action labels to professional signal-provider language.
- Moved the Trade Execution drawer from the right side to the left side on desktop.
- Simplified execution copy to “Trade Execution” and “Place Trade”.
- Kept advanced execution available without changing the safety backend.

## Lifecycle
1. New Signal — Monitor Entry
2. Entry Confirmed — Enter Trade
3. Trade Active — Monitor Trade
4. Signal Closed — View Result

**Database:** no migration is required for V14.8.13.
