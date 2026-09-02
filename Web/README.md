# Alpha Block Solutions — ABS V14.9.2

**Mobile/Web Backend Parity & Admin Event Notifications Build**

V14.9.2 is the backend handoff release to use before production Flutter mobile development. The website and Flutter app share the same ABS authentication, entitlements, central Binance-derived market database, Pulse scanner/signals, execution/reconciliation state, content/CMS, notifications and reporting APIs.

### V14.9.2 changes

- Complete `/api/v1` backend contract retained for web/mobile parity.
- Mobile bootstrap now reports V14.9.2, `mobile_api_ready`, central ABS market-data source, 60-second target refresh and supported mobile modules.
- New self-service registration alerts are emailed to the configurable administrator notification recipient.
- New Pulse package subscription alerts are emailed to the same configurable administrator recipient.
- Default recipient: `i@armansabir.com`; change it in **Admin → Email Communications**.
- Includes a phpMyAdmin-safe, non-destructive schema create/repair SQL: `database/ABS_V14_9_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql`.
- V14.9.1 user soft-delete/schema-repair hotfix and V14.9.0 one-minute central market architecture remain included.

---

## Historical baseline — ABS V14.8.22

## Protected Database Self-Repair & Shared-Hosting Recovery

V14.8.22 is a shared-hosting recovery release built directly on V14.8.21. It preserves the full application QA, HostGator scheduler profile, guided Open Trade UX and central price/signal intelligence architecture while removing the need for Terminal access when an upgrade introduces missing database tables or columns.

### V14.8.22 changes

- Replaced the command-heavy Setup Required page with a protected **Fix Missing Tables & Columns** workflow.
- The repair form accepts the private `ABS_RECOVERY_KEY` already configured in `Laravel_ABS/.env`.
- Added `POST /api/recovery/repair` with rate limiting and constant-time recovery-key comparison.
- Web repair runs the existing non-destructive `abs:repair` reconciliation **without seeding**: missing ABS/Pulse tables are created and missing required columns are added while existing rows and business configuration are preserved.
- Added before/after schema verification and a clear repair result showing how many missing tables/columns were reconciled.
- Added a shared-hosting fallback that reads `ABS_RECOVERY_KEY` directly from the private `.env` when Laravel configuration was previously cached and Terminal access is unavailable. The key is never returned to the browser.
- Kept advanced backup initialization/restore at `/api/recovery`, and added database repair there as the recommended first option.
- Setup diagnostics still show the exact missing tables/columns in a collapsed detail section, but no longer instruct ordinary hosting users to run Terminal commands.
- Added the complete SQL create/repair file under `database/ABS_V14_8_22_COMPLETE_DATABASE_SCHEMA_CREATE_REPAIR.sql` as a phpMyAdmin fallback.
- No new database migration is required.

### Shared-hosting upgrade

After uploading this build, simply browse to the website. If ABS detects missing database structure, enter the configured `ABS_RECOVERY_KEY` and press **Fix Missing Database Structure**. When the repair reports success, open the website normally.

HostGator Shared/Baby scheduler behavior remains the V14.8.21 model: one Laravel `schedule:run` cron every 15 minutes with missed 1-minute validation candles backfilled centrally.

---

# Cumulative ABS Revision History

## ABS V14.8.21 — Full Application QA & HostGator Shared Scheduler

- Corrected release identity and aligned web/API/OpenAPI build metadata.
- Added the `hostgator_shared` scheduler profile using one Laravel `schedule:run` cron every 15 minutes on HostGator Shared/Baby hosting.
- Preserved 1-minute validation precision by downloading missed closed 1-minute candles on each central cycle and processing them chronologically.
- Added profile-aware central-price freshness and prioritized central candle ingestion for user-selected markets, recent signal markets and core markets.
- Added `abs:scheduler-check` and expanded `abs:production-check` for deployment/database, Blade/routes, scheduler, public market, Binance Futures public connectivity, central market ingestion and signal validation.
- Added HostGator no-Terminal deployment/cron documentation.
- No new database migration was required.

---

## ABS V14.8.20 — Guided Self-Configured Trading UX

## Guided Self-Configured Trading UX

V14.8.20 is a customer-journey simplification release built directly on V14.8.19. It keeps the V14.8.17 central price/signal intelligence architecture and Binance risk backend intact while removing the unnecessary “Enable Trading in Settings” step for eligible users.

### V14.8.20 changes

- An explicit **Confirm & Open Trade** click now acts as consent for guided manual signal execution when the user's Pulse plan already includes manual trading.
- Eligible signal-only accounts are automatically moved to managed manual execution at the moment of the first confirmed trade; customers no longer need to visit Settings first.
- Automatic trading mode is never disabled or overwritten by a manual signal trade.
- Open Trade readiness no longer depends on the Settings execution-mode selector. It depends on real requirements only: plan access, eligible environment, verified Binance connection, execution-system availability and Emergency Stop state.
- The Open Trade drawer now displays a **Managed setup** message when ABS will apply the safe trading defaults automatically.
- Removed the confusing **Enable Trading** redirect from the Open Trade flow. Required next steps are now explicit: Connect Binance, View Trading Plans, Review Trading Pause, Choose Trading Environment, or Trading Temporarily Unavailable.
- Successful trades now return the customer to Pulse Signals instead of sending them to another trade-detail page. Pulse continues monitoring the Binance order and protection state in the background; **Monitor Trade** remains available from the signal.
- Before submission, Pulse refreshes Binance trading permission, account equity and available balance when the stored connection snapshot is stale or incomplete.
- The first Binance API connection is now **saved and verified in one step**; when it is the first successful connection, Pulse activates it automatically. Additional verified environments remain deliberate user choices.
- Pulse Settings is renamed **Pulse Preferences** and explains that normal signal execution is managed automatically. Advanced execution preferences remain available but are collapsed by default.
- Mobile execution-readiness API now reports the same managed setup logic with `managed_setup` and `next_step`.
- No new database migration is required.

### Upgrade commands

```bash
php artisan abs:repair --seed
php artisan optimize:clear
php artisan abs:doctor
```

Keep Laravel `schedule:run` once per minute for the central market-data and validation architecture.

---

## ABS V14.8.19 — Simplified Premium Pulse UX & Single Open-Trade Flow

## Simplified Premium Pulse UX & Single Open-Trade Flow

V14.8.19 is a UX simplification release built directly on V14.8.18. It keeps the V14.8.17 central price/signal-intelligence architecture and Binance safety backend unchanged while reducing visual density, removing the redundant standalone Trade Execution page from the user journey, and fixing the Strategies-page overlap shown in production testing.

### V14.8.19 changes

- Fixed the Pulse Strategies overlap by removing fixed-height catalog layout and placing all lower sections in normal responsive document flow.
- Simplified Strategy cards to the essentials: strategy name, short description, timeframe, signal count, average score and one Scanner action.
- Replaced the three crowded Strategy Contribution / Global Controls / Strategy Health blocks with a clean performance summary plus one combined Controls & Health card.
- Removed Trade Execution from the signed-in sidebar.
- Kept `/pulse/execution` only as a backward-compatible redirect to the Signals Open Trade drawer, so old bookmarks do not break.
- Removed the Advanced execution link from the Open Trade drawer.
- Simplified the drawer to Current, Entry, Stop Loss, Take Profit, Environment, Leverage, Trade Size and Max Loss, with one confirmation action: **Confirm & Open Trade**.
- Removed Qualified Strategies, Potential Reward and Risk/Reward clutter from the execution drawer.
- Incomplete execution setup now guides directly to Plans, Binance Connection or Settings instead of opening a second execution page.
- Execution validation errors return to the same signal and automatically reopen the Open Trade drawer with the error shown clearly.
- Reduced Pulse Signals summary cards from five to four and removed the R:R table column and duplicate 30-day activity block.
- Preserved V14.8.17 central market-data, validation, reporting, learning, scheduler and mobile API behavior.

### Upgrade commands

```bash
php artisan abs:repair --seed
php artisan optimize:clear
php artisan abs:doctor
```

No new V14.8.19 database migration is required. Keep Laravel `schedule:run` once per minute for central prices and signal validation.

---


## ABS V14.8.18 — Full-Site Stability, Dashboard Runtime & API Compatibility

V14.8.18 is a stability release built directly on the V14.8.17 central price/signal-intelligence architecture. It fixes the Pulse Dashboard runtime error reported on `PulsePageDataService.php`, keeps the approved central market-data architecture intact, strengthens architecture-readiness handling on reporting/API surfaces, and restores the cumulative revision history in this README.

### Changes

- Fixed `Undefined variable $scannerCapabilities` on `/pulse/dashboard` by initializing dashboard capability and Binance connection-readiness state before building the page payload.
- Fixed the companion uninitialized `$scannerConnectionReady` path that would have produced the next dashboard error after the first variable was corrected.
- Added schema-readiness guards to central price health/read APIs so missing V14.8.17 tables return a controlled recovery state instead of a raw SQL/server error.
- Added graceful empty-state handling to user Pulse Reports and Admin → Pulse → Signal Intelligence when the new intelligence tables have not yet been reconciled.
- Added graceful mobile API behavior for signal validation/performance/learning endpoints while schema repair is pending.
- Preserved the legacy `data.summary` object on `GET /api/v1/pulse/reports` while retaining the clearer `data.trading` alias, preventing an avoidable mobile-client breaking change.
- Kept the V14.8.17 strategy-engine identity unchanged so this stability release does not incorrectly split strategy-learning evidence.
- Restored cumulative revision history to the main README rather than replacing it with release-only notes.
- Expanded release-wide static checks for routes/controllers, API/OpenAPI parity, controller view targets and Blade template references.
- No new database migration is introduced by V14.8.18; V14.8.17 schema reconciliation is still required for the central price/signal-intelligence features.

### Upgrade commands

```bash
php artisan abs:repair --seed
php artisan optimize:clear
php artisan abs:doctor
```

For central prices and signal validation, configure hosting cron to execute Laravel `schedule:run` once per minute.

---

## ABS V14.8.17 — Central Price, Signal Intelligence, Reporting & Mobile API

- Added one centrally scheduled Binance Futures market-data pipeline instead of per-user public price/candle requests.
- Added shared latest-price storage, 15M/4H closed-candle scanner buffers and short-retention 1M validation candles.
- Froze generated signal strategy/version, entry, SL, TP levels, technical score, reliability, confidence and fingerprint.
- Added entry-before-outcome validation, entry-minute exclusion and same-minute TP+SL ambiguous handling.
- Added MFE, MAE, R-multiples, duration and TP progress tracking.
- Added permanent daily signal/strategy aggregates and evidence-protected recency-weighted strategy learning.
- Added user Pulse signal-intelligence reporting, Admin Signal Intelligence reporting and mobile-friendly reporting/market-data APIs.
- Added `abs:pulse-market-data`, `abs:pulse-validate-signals` and `abs:pulse-learning` scheduled commands.
- Database/schema reconciliation required.

## ABS V14.8.16 — Right-Side Execution, Multi-Timeframe Scanner & Live Status

- Moved desktop Signal Execution to the right side; mobile retained the bottom-sheet treatment.
- Fixed execution-footer button visibility.
- Restored dedicated live Status columns to Scanner Results and Pulse Signals.
- Fixed the then-current scanner multi-timeframe behavior so displayed timeframe matched evaluated timeframe.
- Kept same-pair signals independent by timeframe.

## ABS V14.8.15 — Live Price-Driven Signal Action & Open Trade

- Removed scanner Risk / Reward clutter.
- Removed static `New Signal` action logic.
- Made action stage follow current Binance Futures price versus Entry/SL/TP.
- Added Entry Ready, Move in Progress, Entry Watch, Trade Open and Signal Closed states.
- Kept valid signals clickable and refreshed action state without consuming scan quota.

## ABS V14.8.14 — Professional Signal Action & Binance Open Trade UX

- Removed Last Price from Scanner Results.
- Removed confusing `Signal Limit` action text.
- Added professional Review/Open/Monitor/View action flow.
- Added state-aware Recommended Binance Execution guidance.
- Preserved protected Binance LIMIT execution and backend risk checks.

## ABS V14.8.13 — Professional Signal Actions & Trade Execution Drawer

- Removed Strategy columns from execution-focused signal tables.
- Surfaced Entry Price, Stop Loss and Take Profit directly.
- Introduced professional lifecycle/action wording.
- Added the compact execution drawer while preserving server-side Binance/risk safeguards.

## ABS V14.8.12 — Simple Four-Phase Signal Workflow

- Introduced Opportunity Spotted → Entry Ready → Trade In Progress → Expired.
- Added Monitor Signal / Open Trade / Monitor Trade / View Signal actions.
- Reduced execution to a compact single-review surface.
- Prevented a second execution form on the standalone analysis page.

## ABS V14.8.11 — Premium Guided Signal Trade Execution

- Replaced native browser confirmation with guided Signal Review → Confirm Trade → Binance execution.
- Added LIVE/Testnet distinction, qualified-strategy display and risk preview.
- Added final acknowledgement and duplicate-submit protection.
- Preserved the advanced execution ticket and authoritative server validation.

## ABS V14.8.10 — Signal Execution Actions, Multi-Strategy & Live Time

- Converted the Signals queue to decision-oriented actions.
- Added protected LIMIT execution from ready/waiting signals.
- Displayed all positively qualifying strategies.
- Added relative scan-time updates without page refresh.

## ABS V14.8.9 — Scanner Action Consistency

- Standardized scanner action wording and prerequisite handling.
- Refreshed existing active signals against the latest scan without consuming another signal slot.
- Preserved original signal generation time.

## ABS V14.8.8 — Scanner Trade Action & Binance Protected Execution

- Restored deliberate Scanner → Trade Execution flow.
- Reused the protected execution ticket with pre-filled entry, quantity, leverage, SL and TP.
- Enforced plan/environment/manual-trading/risk/exchange safeguards.
- Added Binance exchange-side TP/SL protection and controlled API validation errors.

## ABS V14.8.7 — Selected-Pair Scanner & Threshold Consistency

- Normal scans follow the user's saved package-approved selected markets.
- Removed the legacy small scanner-pair ceiling from modern selected-market scanning.
- Unified scanner minimum score with effective user/package signal threshold.
- Added package-configurable default minimum score and repair support.

## ABS V14.8.6 — Full Package Scanner

- Expanded scanner processing and package market handling from the prior scanner baseline.
- Added release checks around package-wide evaluation, concurrent market processing and completed-run quota accounting.
- Retained mobile scanner API parity.

## ABS V14.8.5 — Scanner Fragment Hotfix

- Corrected scanner-page fragment/navigation issues from the preceding release.
- Preserved V14.8.4 upgrade/database behavior while restoring scanner page stability.

## ABS V14.8.4 — Upgrade Path & Database Fix

- Added current-plan/next-tier upgrade UX.
- Added production-safe self-healing database repair behavior.
- Strengthened non-destructive upgrade/seeding paths for existing installations.

## ABS V14.8.3 — Async Scanner, Quotas, Logout & Pair Lock

- Added asynchronous scanner behavior and safer logout handling.
- Added plan quota enforcement and selected-pair change locking.
- Corrected Long/Short ratio handling and scanner usage accounting.

## ABS V14.8.2 — Production Updates, Mobile & Binance

- Established the production V14.8.x Pulse baseline.
- Included the 15-strategy/package market model, Binance Futures connection/execution controls and mobile API parity.
- Included production backup/restore and rollback-oriented release tooling.

## ABS V14.7.9 — Final Logged-In Pulse Navigation

- Finalized authenticated Pulse navigation and page routing consistency.
- Preserved capability-aware links and premium authenticated layout.

## ABS V14.7.8 — Complete Premium Authenticated Experience

- Harmonized remaining logged-in Pulse pages with the approved premium dashboard/scanner/signals visual language.
- Preserved real authenticated account, plan, signal, trade and exchange data rather than sample production values.
- Maintained mobile/OpenAPI parity and execution safeguards.

## ABS V14.7.7 — Premium Pulse User Pages + Mobile API Parity

- Delivered premium Dashboard, Market Scanner, Signals, Strategies and Trade Execution pages.
- Added shared page-data logic for web/mobile consistency.
- Added capability-aware collapsible Pulse navigation.
- Strengthened execution readiness and safe Binance metadata handling.

## ABS V14.7.6 — Subscription Decision Workflow

- Improved administrator subscription-request review, approve/reject decisions and remarks handling.
- Focused the admin membership flow on clear operational decisions.

## ABS V14.7.4 / V14.7.3 / V14.7.2 / V14.7.1

- Continued production validation of Pulse routes/pages and deployment behavior.
- Added premium admin/email activation guidance and live-market/database update guidance.
- Added backup/restore support and production-safe recovery documentation.

## ABS V14.7 — Production Distribution Baseline

- Consolidated enterprise CMS, mobile API, email/alert matrix, deployment and validation documentation.
- Established the production-oriented distribution/acceptance baseline used by later Pulse releases.

## ABS V14.6.14 — Advantage Grid / Side-Card Clearance

- Refined premium homepage layout clearance and side-card spacing.

## ABS V14.6.13 — Integrated Futures Pricing Gauge

- Integrated futures-positioning/pricing presentation into the premium public market experience.

## ABS V14.6.12 — UI Validation

- Added UI validation and consistency checks for the premium homepage iteration.

## ABS V14.6.11 — Futures Positioning Metrics

- Added futures-market positioning metrics to the public market-intelligence experience.

## ABS V14.6.10 — Premium Wide Layout & Readability

- Improved wide-layout readability, spacing and information density.

## ABS V14.6.9 — Confirmed Premium Homepage

- Locked the confirmed premium homepage visual reference.

## ABS V14.6.8 — Premium Readability & Density Refinement

- Refined information density and premium readability across the public homepage.

## ABS V14.6.7 — Premium Public Experience

- Advanced the professional public ABS visual/market-information experience.

## ABS V14.6.2 — Homepage Live Data & 3D Treatment

- Added/refined live-data presentation and the approved richer homepage treatment.

## ABS V14.6.1 — Laragon / MySQL Fixed Build

- Fixed local Laragon/MySQL startup and duplicate legacy user-migration conflicts.
- Added cleanup/repair and MySQL-first local helper scripts.

## ABS V14.6 — Complete Pulse Workspace

- Completed the signed-in Pulse customer area: Dashboard, Scanner, Signals, Strategies, Trade Execution, Positions, History, Risk, Alerts/Watchlists, Reports, Binance Connection and Settings.
- Preserved authenticated Binance terminology, execution safeguards and no-fake-production-data rules.

## ABS V14.5 — Final Pulse Dashboard

- Established the approved professional Pulse dashboard baseline used by the complete workspace.

## ABS V14.4 — Final Homepage

- Established the approved public ABS homepage baseline.

## ABS V14.3 — Authentication UI

- Consolidated premium authentication presentation and flow.

## ABS V14.2 — Create Account Page

- Added/refined the ABS account-registration experience.

## ABS V14.1 — Login Page

- Added/refined the ABS login experience.

## Earlier retained baselines

The package also retains documentation for the earlier membership/premium/error-fix foundations, including V13.7 Membership/Admin, V13.8 Premium Experience and V13.2 error-fix references. These earlier documents remain under `docs/` for traceability instead of being deleted from later builds.

---

# V14.8.17 Architecture Operation Notes

The current runtime still uses the V14.8.17 approved price/signal architecture:

- Binance Futures public market data is fetched centrally by the server scheduler.
- Web/mobile clients consume ABS-stored price/candle/reporting state rather than initiating per-user public Binance market-data fetches.
- Scanner execution architecture uses 15M and 4H strategy candles.
- Detailed validation uses 1M candles with approximately 7-day resolved-detail retention.
- Compact daily strategy metrics and learning state are retained permanently.
- Signal Entry/SL/TP/strategy/version snapshots remain immutable after generation.

See:

- `docs/ABS_V14_8_17_CENTRAL_PRICE_SIGNAL_INTELLIGENCE_ARCHITECTURE.md`
- `docs/MOBILE_API_V14_8_17.md`
- `docs/openapi.yaml`

## Production deployment

Back up the database and `.env`, upload the build, then run:

```bash
php artisan abs:repair --seed
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:production-check
```

Configure one hosting cron job to run Laravel `schedule:run` every minute so central prices, validation, exchange reconciliation and other scheduled ABS tasks remain current.
