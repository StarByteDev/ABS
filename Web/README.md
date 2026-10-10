> **V15.9.1 CANDIDATE — Cross-route premium light/dark theme normalization:** A single final theme stylesheet shared across public, auth, member and admin layouts. Fixes Pulse Open Positions/Trade History KPI, headings, tables, filters and mobile alignment. All 131 Blade templates source-inventoried; 144 fixture-based contrast checks passed across two themes and three widths. **Not authenticated end-to-end verified**; see `docs/ABS_V15_9_1_DEPLOY_AND_AUDIT.md`. No database or trading changes.

> **V15.9.0 CANDIDATE — UI theme and economic-calendar impact filter:** Web style patches for profile services, Free Signal and macro calendar, grid-aligned sidebar and period-preserving High/Medium/Low impact filter. Read `docs/ABS_V15_9_0_THEME_CALENDAR_FILTER_QA.md`. No DB change. Full authenticated route QA still needed.

> **V15.9.0 CANDIDATE — UI theme and economic-calendar impact filter:** Web style patches for profile services, Free Signal and macro calendar, grid-aligned sidebar and period-preserving High/Medium/Low impact filter. Read `docs/ABS_V15_9_0_THEME_CALENDAR_FILTER_QA.md`. No DB change. Full authenticated route QA still needed.

> **V15.8.7 UI theme hotfix candidate:** Fixes broken/mixed Light/Dark themes for member pp-* dashboard/components and improves the shared theme switcher. No DB/business logic changes. See `docs/ABS_V15_8_7_THEME_HOTFIX_DEPLOY.md`. Not production-verified.

> **V15.8.6 testing candidate:** Evidence-based strategy safety, admin reporting CSV + mobile API, generic FCM queued alerts, consistent light theme. Deployment: `docs/ABS_V15_8_6_ADAPTIVE_PUSH_THEME_DEPLOY.md`. NOT PRODUCTION-VERIFIED.

> **V15.8.5 candidate:** Economic Calendar + Pulse Push. See `docs/ABS_V15_8_5_MACRO_PUSH_DEPLOY.md`. Not yet deployed; mobile-side FCM setup required. Original working base: V15.8.4.

## ABS V15.8.4 — Admin Analytics Backfill & Shared-Hosting Maintenance

V15.8.4 is a production-safe follow-up to V15.8.3 for HostGator/shared-hosting deployments. It removes the need to run the one-time strategy analytics backfill from SSH/Terminal. Admin can now queue a 7, 30 or 90-day rebuild directly from **Admin → Strategy Engine & Evaluation → Strategy Performance**. The existing scheduler processes the job automatically in small bounded batches; the existing cPanel cron entry must remain unchanged.

### Shared-hosting backfill workflow

1. Open **Admin → Strategy Engine & Evaluation → Strategy Performance**.
2. In **Shared-Hosting Maintenance**, choose 7, 30 or 90 days.
3. Click **Queue Analytics Backfill**.
4. Leave the existing production cron exactly as it is. ABS `schedule:run` will reach the already-scheduled `abs:pulse-maintenance` task and process up to three retained dates per cycle within an approximately 20-second work budget.
5. The page shows queued/running/completed/failed status, processed dates and progress percentage.
6. At completion, Pulse refreshes learning-state and adaptive R:R evidence once.

This rebuild updates derived analytics only. It does not delete or rewrite customer, investor, signal, validation or trade history. A safe Cancel action stops future chunks while preserving dates already rebuilt.

### Compatibility

- No database schema change.
- No change to the user's existing cPanel cron configuration.
- Existing mobile API routes and fields remain compatible.
- V15.8.3 adaptive strategy, dynamic R:R and premium theme behavior is preserved.

## ABS V15.8.3 — Mobile API Compatibility, Adaptive Strategy Optimizer & Premium Theme

V15.8.3 is a production-safe follow-up to V15.8.2. It preserves the existing mobile API contract, adds a health probe for connectivity diagnostics, introduces evidence-driven strategy throttling and bounded R:R optimization, corrects neutral strategy filters that were creating misleading directional attribution, and refreshes the Dark theme with a premium navy/graphite palette.

### Mobile/API recovery
- V15.7.4 → V15.8.2 source comparison found no removal/renaming of the existing Pulse mobile API routes/controller contract.
- The supplied production error `NET::ERR_CERT_COMMON_NAME_INVALID` is an HTTPS certificate hostname problem that occurs before Laravel; it must be corrected at HostGator/cPanel/SSL level.
- Added `GET /api/v1` and `GET /api/v1/health` as unauthenticated diagnostics. Existing Bootstrap/auth/scanner/signals/trades/reports/private-investor API routes remain intact.
- Strategy-report API fields are preserved and an optional `adaptive` object is added for newer clients; signal strategy-evidence rows also receive optional adaptive/eligibility keys without removing legacy keys.

### Adaptive strategy optimization
- Raw research continues for all enabled strategies so the system keeps learning rather than hiding weak evidence.
- Member-facing scans dynamically reduce the contribution of mature negative-expectancy strategies and can mark persistently weak contributors Research Only.
- Adaptive R:R is selected from recorded MFE/MAE evidence and is bounded to ±0.50R from the configured strategy target to avoid overreaction.
- Member signals require directional confluence and at least one Core Strategy/Entry Trigger.
- ATR Volatility, Range Compression and Risk/Reward Quality are now neutral filters/gates and cannot manufacture LONG/SHORT direction.
- Added a non-directional high-impact macro-event risk gate using the existing economic calendar, so member signals can be paused around configured event windows without pretending that fundamentals predict direction.
- The existing 75% technical + 25% learned-reliability confidence architecture is retained. Historical losses are not rewritten and no profitability guarantee is implied.

### Theme
- Added a V15.8.3 premium theme layer: navy/graphite surfaces, softer hierarchy/borders, cyan interactions, restrained gold highlights and clearer success/loss colors.
- Light/Dark preference remains persistent across Admin, Pulse and public layouts.

### Production/data safety
- No customer, investor, payment, membership, signal, trade, statement or learning history is deleted/reset.
- Strategy migration is additive/idempotent and preserves Admin-customized settings.
- The published mobile app receives the backend strategy improvements automatically once valid HTTPS is restored. No Flutter binary/source is bundled in this web/backend release.

---

## ABS V15.8.2 — Scan Audit Stability & $100 Virtual Strategy Performance

V15.8.2 is a production-safe reporting/stability update on top of V15.8.1. It fixes the live shared-hosting `HY001 / 1038 Out of sort memory` failure on **Admin → Audit & History** and makes strategy performance easier to understand by replaying each resolved paper trade with the same **$100 unleveraged virtual position size**.

### Scan Audit stability
- Replaced full-row `ORDER BY started_at` pagination with a two-stage primary-key-first paginator. Only IDs are paginated/sorted first; the 30 full rows and their large JSON summaries are hydrated afterward.
- Aggregate scan evidence now reads large JSON summaries in bounded 100-run chunks instead of loading the complete date range into PHP memory at once.
- Added a best-effort `started_at + id` scanner-run index for normal migrations and shared-hosting Schema Repair.
- This removes the MySQL filesort pattern that caused the production `Out of sort memory` error without requiring HostGator/MySQL server configuration changes.

### $100 virtual strategy performance
- Every resolved paper TP/SL is replayed as a **$100 unleveraged position** using its actual Entry → final TP/SL price move.
- Reports now show gross virtual profit, gross virtual loss, net virtual P/L, tested notional, virtual ROI and average P/L per resolved trade.
- Strategy Intelligence shows Signals/Entries, TP1/TP2/TP3, final TP/SL, success rate, virtual P/L, virtual ROI, Net R and Learning Score in one register.
- Trade Results now displays the $100 virtual P/L for each individual resolved trade and can be filtered by strategy as well as TP/SL/milestone outcome.
- Strategy P/L is explicitly marked as attribution: one signal can contain several contributing strategies, so strategy rows can overlap. The overall Net Virtual P/L is calculated once per unique trade and is the figure to use for total system performance.

### Production/data safety
- No users, memberships, payments, investors, statements, signals, trades or historical strategy evidence are deleted or rewritten.
- The only schema change is an additive performance index; rollback intentionally leaves it in place.
- Existing web/mobile API routes and response fields remain compatible. The $100 simulation is Admin reporting only and does not change real exchange execution, signal scoring or investor accounting.

---

## ABS V15.8.1 — Qualified Signal Shortlist, Effective-Dated Investor Rates, Reinvestment & Exchange Performance Ledger

V15.8.1 is a production-safe follow-up to V15.8.0. It keeps the live mobile/API contract intact while making the web scanner more transparent and strengthening Private Investor accounting and Admin performance tracking.

### Find Best Signal
- Scans the full enabled Binance USD-M Futures universe synchronized into Pulse, regardless of package pair assignments.
- Keeps one canonical **Best Signal** for existing mobile/API compatibility, but the web page now shows the ranked top qualified shortlist (default up to 12) instead of hiding every qualifier except the winner.
- Metrics distinguish total qualified setups from the number displayed and keep the strongest candidate ranked first.

### Effective-dated investor agreements
- Monthly percentage changes are stored as immutable effective-dated rate periods. A later 2% agreement does not rewrite earlier 5% months.
- Rate changes can start on the first day of a month or mid-month; the target is calculated day-by-day using invested capital and the rate effective on each date.
- Published monthly statements are finalized history and are not automatically rewritten by a later agreement change.

### Profit payout vs reinvestment
- Each effective-dated agreement can settle monthly profit as **Pay to investor** or **Retain & reinvest into capital**.
- Paid profit remains an external distribution and has zero capital effect.
- Reinvested profit remains visible as profit, is separately reported as **Profit Reinvested**, and also increases invested capital from its settlement date so later targets compound from the higher capital base.
- Partial capital withdrawals reduce invested capital from their posted date; future daily/monthly targets automatically use the reduced balance.
- Statements separately show Profit Paid and Profit Reinvested. Transaction labels and schema-repair logic preserve the same distinction.

### Admin Exchange Ledger & Performance
- New **Private Investors → Exchange Ledger** page records opening balance, exchange deposits, exchange withdrawals, trading P/L, fees and auditable adjustments.
- Admin can set a monthly own-performance target and compare realized trading P/L with that target and with the combined investor monthly profit target.
- Shows opening/closing exchange balance, net exchange cash flow, monthly P/L, target progress and a rolling 12-month statement.
- Incorrect ledger rows are voided with a reason rather than deleted, preserving audit history.

### Production/data safety
- Additive migration only. Existing users, investors, payments, memberships, transactions, statements, signals, trades and mobile API fields are retained.
- Existing V15.8.0 Best Signal API behavior is preserved; the multiple-qualified-signals experience is an additive web/reporting enhancement.
- Shared-hosting Schema Repair is V15.8.1-aware and will not convert reinvested profits back into external payouts.

### Release QA
- PHP/Blade syntax validation completed across the application source.
- The source package contains no production dummy financial records.
- Runtime Laravel integration tests require the deployment/vendor dependencies and production-like database environment; use the normal staged migration/backup procedure before production deployment.

---

## ABS V15.7.4 — Automatic Investor Profit Payouts, Statements & USD Consolidation

V15.7.4 is the production Private Investor settlement and reporting release built on V15.7.3. It fixes the gaps visible in the live investor screens: completed months no longer leave Statements blank, monthly profit is treated as an external investor payout rather than capital retained in the portfolio, capital withdrawals remain separate from profit distributions, and Admin can see every investor in their own principal currency while also managing all investor funds in consolidated USD.

### Investor accounting behavior
- Daily performance continues to accrue automatically from the agreed monthly percentage and effective date.
- At the configured payout moment, ABS automatically finalizes the completed performance month, records the month's **Profit Paid** transaction, and publishes/reconciles that month's statement.
- The default payout timing is month end. Admin can alternatively set a payout day from 1–28 of the following month.
- **Profit Paid is not a capital withdrawal.** It is money distributed to the investor and therefore has zero principal/current-capital effect.
- **Capital Withdrawal** is reserved only for return of invested principal. The investor receives principal in the locked original account currency under the V15.7.2/V15.7.3 FX-protection rule.
- Monthly statements show opening capital, added capital, capital withdrawn, profit/loss, profit paid, payment date, and closing capital balance.
- Completed due months are reconciled idempotently: re-running the scheduler or opening the portal cannot create duplicate monthly profit payouts.

### Admin reporting
- Each investor remains visible in their own account/principal currency, including principal, reported value, profit paid, performance and statements.
- Admin Portfolio Overview and Reports also maintain consolidated USD accounting using each transaction's locked USD basis/rate.
- **Total Investor Funds** is the combined USD historical principal basis across active investors.
- Admin-only realized FX gain/loss remains separate from investor profit and does not alter investor principal.
- The Activity ledger distinguishes **Investment**, **Capital Withdrawal**, and **Profit Paid** so the Withdrawals KPI is zero when no principal was actually returned, even if monthly profit has already been paid out.

### Automatic settlement and shared-hosting scheduling
- `InvestorSettlementService` performs monthly settlement and statement reconciliation.
- The normal ABS scheduled maintenance pipeline calls the settlement process every minute; the operation is idempotent and safe for the existing HostGator one-minute cron setup.
- Portal/Admin/API reads also reconcile due months before presenting financial summaries so an already-due statement does not remain blank simply because a scheduled invocation was delayed.
- Historical manual profit entries are preserved and mapped to a performance month instead of being duplicated.

### Mobile/API readiness
- Private Investor mobile APIs now expose the corrected activity summary with separate `capital_withdrawal` and `profit_paid` values.
- Statements returned to mobile include `profit_paid`, `payment_date`, and the reconciled monthly statement fields.
- Private Investor API reads invoke due-settlement reconciliation before returning account, transactions or statements.
- OpenAPI and application build identity are updated to V15.7.4 so the next Flutter release can target this backend contract.

### Production/data safety
- V15.7.4 uses an additive migration only. No investor, user, transaction, statement, request, agreement, trade, payment or audit row is deleted.
- Historical `profit` records are retained. Their old capital-increase effect is corrected to zero because published/paid profit is external distribution, not invested principal.
- Existing V15.7.2 principal-currency and Admin FX invariants remain unchanged.
- Schema Repair includes the V15.7.4 fields for shared-hosting recovery.

### Release QA
The final release checks cover PHP syntax, Blade stability, routes/views/OpenAPI consistency, automatic settlement contract, statement reconciliation, payout/withdrawal separation, Admin local-currency plus consolidated-USD reporting, mobile API fields, schema-repair coverage and release-manifest integrity. The package does not contain production dummy/test financial records.

---

## ABS V15.7.3 — Investor/Admin Principal Currency Controls & Production UX Fix

V15.7.3 completes the end-user multi-currency workflow introduced in V15.7.1/V15.7.2. The accounting engine already supported non-USD investor principal and Admin USD FX accounting, but an existing empty investor account could still look fixed to USD because the main Admin transaction form and investor Requests page did not provide a complete currency-change path.

### What is fixed
- **Investor side:** Private Investor → Requests now has a real Principal Currency selector for an empty portfolio.
- **Investor first/add-investment request:** the investor can select the currency being contributed before financial activity starts.
- **Admin side:** currency can be changed from Investment Setup, Transactions, and Account Controls while the portfolio is financially empty.
- **First Admin transaction:** selecting PKR/AED/EUR/etc. immediately switches the amount label, FX conversion panel, and FX reference lookup to that currency.
- **USD is now a default, not a forced choice.** Supported currencies come from `config/private_investor.php`.

### Principal-protection rule remains locked
- Example: investor contributes **PKR 1,000**.
- ABS stores PKR 1,000 as the investor principal and stores the investment-date USD equivalent as Admin historical cost basis.
- Full principal withdrawal returns **PKR 1,000**, regardless of later PKR/USD movement.
- Any difference between historical USD basis and withdrawal settlement USD cost is an **Admin-only realized FX gain/loss**.
- The investor's principal, monthly target and statement figures are never revalued because FX moved.

### When currency can and cannot change
The principal currency can be changed only while there is no real financial history. `InvestorCurrencyService` enforces this server-side and uses a database row lock.

Currency becomes locked when the account contains transactions, statements, non-zero portfolio values, meaningful non-zero performance history, or an unresolved capital request. A zero-value performance schedule created from a percentage agreement does **not** incorrectly lock an otherwise empty account. An open Add Investment/Capital Withdrawal request must be resolved or cancelled before changing currency so a pending request cannot silently change denomination.

This means an existing live account that currently shows USD 0.00 can be switched to PKR/AED/etc. from either Admin or the investor portal, provided no financial history has already been recorded.

### Admin workflow
1. Open **Private Investors → Investment Setup**, **Transactions**, or **Account Controls**.
2. Select the required Principal Currency while the account is empty.
3. For a non-USD first investment, enter or fetch the locked `1 currency = USD` rate.
4. Post the investment. From that point the investor currency is locked to protect principal.
5. Admin consolidated reporting continues in USD.

### Investor workflow
1. Open **Private Investor → Requests**.
2. On an empty portfolio, choose the Principal Currency and save it, or choose the currency directly while submitting the first Add Investment request.
3. Once financial activity starts, withdrawals are always requested in the locked principal currency.

### Database and production safety
V15.7.3 adds **no database schema changes**. It uses the existing additive V15.7.1/V15.7.2 fields. No users, investors, transactions, statements, requests, agreements, payments, trades or audit records are deleted or recalculated during deployment.

Deploy over V15.7.2 using the normal ABS backup/patch workflow. Run normal Laravel cache clearing after deployment. Existing V15.7.1/V15.7.2 migrations should remain in place and may be run normally on any server that has not applied them yet.

### Release QA
V15.7.3 validation covers PHP syntax, Blade compilation-oriented stability checks, static routes/views/OpenAPI consistency, explicit Admin/investor currency controls, server-side currency locking, first-transaction currency handling, dynamic FX UI behavior, preservation of V15.7.2 principal/FX accounting invariants, and release-manifest integrity.

---

## ABS V15.7.2 — Principal-Currency Protection & Admin FX Accounting

V15.7.2 is a production-safe upgrade of V15.7.1 for the Private Investor module. It keeps each investor's capital obligation permanently denominated in the investor account currency while ABS maintains consolidated internal administration in USD.

### Locked investor-principal rule
- Each investor account has one principal currency selected by Admin from the supported currency list.
- The investor's principal amount never changes because the USD exchange rate changes.
- Example: an investor contributes **PKR 1,000**. If the investment-date rate gives an Admin USD carrying basis of USD 3.57, the investor still owns **PKR 1,000 principal**.
- When that full principal is returned, the investor receives **PKR 1,000**, regardless of the later PKR/USD rate.
- If returning PKR 1,000 costs ABS USD 3.33, Admin records a USD 0.24 realized FX gain. If it costs USD 3.85, Admin records a USD 0.28 realized FX loss.
- Realized FX is Admin-only and never changes investor principal, investor P/L, monthly target, or investor statements.

### USD accounting model
- Investments store the original amount/currency, investment-date locked FX rate, USD settlement equivalent, and historical USD principal basis.
- Capital withdrawals store the original investor-currency amount, withdrawal settlement rate, actual USD settlement cost, historical USD principal basis removed, and realized Admin FX gain/loss.
- Partial capital withdrawals use a weighted-average historical USD principal basis. A full withdrawal consumes all remaining USD basis to avoid rounding residue.
- Admin `net_contributions_usd` is a historical carrying basis, not a floating revaluation of investor principal.
- Published monthly statement P/L may be translated at the statement rate, but principal additions and withdrawals use their transaction-level historical basis.

### Investor experience
- Overview, transactions, performance, statements and requests remain in the assigned investor currency.
- Capital Withdrawal explicitly means return of invested principal in the same investor currency.
- Investor request validation prevents a capital-withdrawal request above remaining principal.
- No Admin USD basis, settlement cost or FX gain/loss is exposed through the investor model serialization.

### Admin experience
- Investor setup clearly selects the principal currency.
- Transaction entry explains investment-date USD basis versus withdrawal settlement rate.
- Withdrawal preview shows investor amount returned, Admin USD basis, USD settlement cost and estimated FX gain/loss.
- Account Snapshot, transaction ledger, Portfolio Overview and Reports show realized FX gain/loss separately from investor P/L.
- CSV reporting includes Admin realized FX gain/loss.

### Existing V15.7.1 live data
- Upgrade migration is additive only.
- No users, investors, transactions, statements, requests, agreements, trades, payments or histories are deleted.
- Existing V15.7.1 transaction financial effects are not silently rewritten during migration.
- New or edited withdrawals use the V15.7.2 principal-basis/settlement model.
- Existing USD accounts continue 1:1 with no FX gain/loss.

### Database / shared-hosting safety
The normal migration and `AbsSchemaRepair`/Database Fix paths both know the new fields:
- `portfolio_accounts.realized_fx_gain_loss_usd`
- `portfolio_transactions.principal_usd_basis`
- `portfolio_transactions.settlement_usd_amount`
- `portfolio_transactions.fx_gain_loss_usd`

The migration `down()` intentionally retains finance/audit fields under the permanent ABS production data-preservation rule.

### Release QA
V15.7.2 release validation covers PHP syntax, Blade expression/directive stability, static route/view/API checks, schema-repair parity, build/version identity, principal-currency invariants, withdrawal basis/settlement/FX contract, Admin-only field protection, and production additive migration requirements.

### Upgrade
Deploy V15.7.2 over the existing V15.7.1 application using the existing ABS production patch/backup workflow. Run normal migrations (or the existing Database Fix if required by the shared-hosting environment), clear application/config/view caches, then verify one non-USD investor with a small test/draft flow before posting real financial activity.
