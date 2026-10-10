## V15.9.0 — Theme UI families, aligned sidebar and calendar impact filters (2026-10-09)
- Scoped theming for profile linked-service tiles, Free Signal, and economic-calendar cells/tabs; alignment for member sidebar using grid. Read-only impact filter (High/Medium/Low/All) preserves calendar window. No DB, strategy, mobile or trading changes.

## V15.8.9 — Theme Contrast Regression Candidate (2026-10-09)
- Correct autofilled sign-in text, investor KPI backgrounds, account dropdown and market category contrast across themes. Theme stylesheet loads last on all four web layout shells. No DB or trading changes.

## V15.8.8 — public/member/admin/login theme and navigation readability (candidate)

- Full paired foreground/background light/dark tokens for public homepage charts, market terminal, trust cards, news and plan panels, auth entrypoint, shared admin/member components.
- Clear high-contrast Pulse **left menu** including locked items (no faded opacity); improved selected/hover states, icons, wrapped labels and readable profile area.
- Larger legible admin left-navigation rows and category labels, in desktop and mobile.
- Shared theme preference across public, member, admin and auth shells; no financial/trading/database changes.
- Browser-checked representative DOM elements at 1440px and 390px, both themes, contrast ratios above 4.5 for tested content; not full live-site E2E verification.
- Native Flutter app is separate and has not been changed.

## V15.8.7 — unified Pulse member Light/Dark UI hotfix (candidate)

- Fixed the disconnected member pp-* design system (mixed light outer shell + dark unreadable cards) across authenticated dashboard, navigation and common Pulse components.
- Improved theme toggle semantics; one browser-wide theme preference across Public, Member and Admin. New versioned CSS and JS eliminate prior asset-cache confusion.
- No database migrations or modifications to trading, signals, Firebase, user or investment business logic.
- See `docs/ABS_V15_8_7_THEME_HOTFIX_DEPLOY.md` for deployment, testing and rollback requirements.

## V15.8.6 — adaptive risk evidence, push queue, strategy P/L API, full light-theme pass (candidate)

- Preserves V15.8.4/V15.8.5 history and all existing database rows.
- See `docs/ABS_V15_8_6_ADAPTIVE_PUSH_THEME_DEPLOY.md` for code-level changes and staging checklist.

# ABS V15.8.5 — Macro Calendar + Pulse Push (candidate, not deployed)

- Non-destructive V15.8.4 extension. Missing forecast/actual enrichment from enabled providers; provider null responses no longer erase valid values.
- 5-minute hot calendar refresh, live web API polling, actual-driven factual Pulse market analysis.
- FCM v1 HTTP notification outbox to opted-in devices, with bounded retry/idempotency and optional credentials.
- Additive two-table migration. Mobile integration still required for FCM token registration/notification navigation.
- Full deployment and security notes: `docs/ABS_V15_8_5_MACRO_PUSH_DEPLOY.md`.

# ABS V15.8.4 — Admin Analytics Backfill & Shared-Hosting Maintenance

- Added Admin → Strategy Intelligence → Strategy analytics backfill with 7/30/90-day choices.
- No SSH/Terminal and no cPanel cron edit is required: queued work is consumed by the existing `abs:pulse-maintenance` scheduler cycle.
- Backfill runs in bounded shared-hosting-safe slices (maximum three retained dates / ~20 seconds per maintenance cycle).
- Added live progress/status polling, completion/failure state, safe cancellation and Admin audit events.
- Historical trade/validation rows are never deleted or rewritten; only derived daily analytics/learning evidence is rebuilt.
- Optimized learning refresh so historical dates are rebuilt first and adaptive strategy state is refreshed once at completion rather than once per day.
- Existing `abs:pulse-analytics-backfill` CLI command remains available and now uses the same one-refresh-at-end optimization.
- Mobile API routes/response compatibility from V15.8.3 remain unchanged; API build identity advances to 15.8.4.
- No database schema migration is required.

# ABS V15.8.3 — Mobile API Compatibility, Adaptive Strategy Optimizer & Premium Theme

- Preserved all existing mobile API routes/response fields and added unauthenticated `/api/v1` + `/api/v1/health` diagnostics.
- Documented the production `NET::ERR_CERT_COMMON_NAME_INVALID` issue as a hosting TLS certificate mismatch; no insecure certificate bypass is introduced.
- Added evidence-driven strategy profiles that dynamically throttle negative-expectancy contributors for member signals while raw research continues collecting evidence.
- Added conservative MFE/MAE-based R:R candidate evaluation and bounded live target tuning.
- Corrected ATR Volatility, Range Compression and Risk/Reward Quality to neutral filter/gate roles instead of directional signal creators.
- Added a high-impact economic-event risk gate for member signals using the existing ABS macro calendar; it is non-directional and raw research continues.
- Added member confluence requirements and research-only gating for persistently weak, sufficiently sampled strategies.
- Added missing-strategy/default-settings migration that preserves all Admin-customized values.
- Added adaptive R:R, adaptive weight, expectancy and eligibility/state reporting to Admin Strategy Performance; mobile strategy reporting receives the same data as an additive object.
- Reworked Dark theme with a premium navy/graphite/cyan/gold visual system while preserving Light theme and saved user preference.
- Kept customer, investor, payment, membership, signal, trade and learning history data intact.

# ABS V15.8.2 — Scan Audit Stability & $100 Virtual Strategy Performance

- Fixed production `SQLSTATE[HY001] / 1038 Out of sort memory` on Admin Scan Audit by paginating scanner-run IDs first and hydrating full JSON-heavy rows separately.
- Bounded aggregate scan-summary processing to 100-run chunks to prevent PHP memory spikes on long/high-frequency audit ranges.
- Added a best-effort `pulse_scanner_runs(started_at, id)` index for migration/schema-repair paths.
- Added a standardized **$100 unleveraged virtual position** model for resolved TP/SL paper validations.
- Added overall virtual gross profit/loss, net P/L, ROI, tested notional and average P/L reporting.
- Added per-strategy virtual P/L and ROI alongside TP1/TP2/TP3, final TP/SL, Win Rate, Net R and Learning Score.
- Added strategy filtering to Trade Results and per-trade $100 virtual P/L.
- Kept all changes additive/reporting-only for live customer/mobile safety.

# ABS V15.8.1 — Qualified Signal Shortlist, Effective-Dated Investor Rates & Exchange Ledger

- Web scanner now shows a ranked shortlist of qualified signals while preserving one canonical Best Signal for mobile/API compatibility.
- Scanner metrics use the full enabled Binance USD-M Futures universe, independent of package pair assignments.
- Investor monthly rates are effective-dated: changing a rate later does not rewrite prior months; mid-month changes are prorated from the effective date.
- Monthly profit can be paid out or retained/reinvested. Reinvested profit increases invested capital and future profit targets; partial capital withdrawals reduce future targets from the withdrawal date.
- Monthly statements separate Profit Paid from Profit Reinvested.
- Added Admin Exchange Ledger & Performance page for exchange deposits/withdrawals, realized P/L, fees, monthly target tracking, investor-target comparison, and month-by-month statement rows.
- All finance/database changes are additive and retain production history.

# ABS V15.8.0 — Strategy Intelligence, TP Milestone Learning, Full-Market Scan & Premium Themes

## Strategy Engine & Evaluation
- Reworked Strategy Performance into Strategy Intelligence with signals, entries, TP1/TP2/TP3 milestones, final TP/SL, win rate, Net R and learned reliability.
- Reworked Trade Results into a filterable evidence register for final TP, TP1, TP2, TP3, SL, open, ambiguous and expiry outcomes.
- Added understandable strategy configuration controls for role, preferred R:R, TP levels, stop ATR multiplier, weight and learning. Advanced JSON remains available but is no longer the primary UI.
- Added three executable strategy evaluators: Trend + Volume Confluence, MACD + RSI Confluence and Breakout Momentum.

## Learning
- Preserved the 75% technical + 25% learned-reliability confidence model.
- Added a capped milestone bonus so TP1/TP2/TP3 improve strategy learning gradually while final TP/SL remain dominant evidence.
- Historical signals and trades are never rewritten when learning scores change.

## TP/SL lifecycle repair
- Separated pre-entry signal expiry from post-entry trade holding time.
- 15M research trades default to a 24-hour maximum holding window after entry; 4H trades default to 7 days.
- Central 1M validation candles continue syncing for unresolved validations instead of stopping after four hours.
- This directly addresses the pattern where entered trades could expire before a reasonable TP/SL observation window.

## Find Best Signal
- Authenticated scans now search every enabled Binance USD-M perpetual Futures pair synchronized into Pulse, independent of package pair assignments.
- Package access still controls scanner capability; packages no longer shrink the market search universe.

## UI
- Added persistent Dark/Light theme toggle to Admin, Pulse member UI and public application layout. Dark remains default.

## Production safety
- Additive/data-preserving migration only; no user, customer, payment, investor, signal, trade, statement or history tables are dropped or recreated.
- Existing API routes and mobile response fields are preserved.
- New strategy rows/settings are inserted/backfilled only when missing.

---

# ABS V15.7.4 — Automatic Investor Profit Payouts, Statements & USD Consolidation

## Fixed
- Fixed blank investor Monthly Statements for completed/due performance months by adding automatic idempotent month settlement and statement reconciliation.
- Corrected profit accounting: a posted monthly `profit` entry is an external **Profit Paid** distribution and no longer increases investor capital/current portfolio value.
- Kept **Capital Withdrawal** exclusively for return of invested principal, so the Withdrawals KPI no longer includes normal monthly profit payouts.
- Added explicit `profit_paid`, `profit_paid_usd`, `payment_date` and automatic-statement metadata.
- Added `performance_month` and `entry_source` to transaction records so monthly payouts reconcile to the correct performance month without duplicate creation.
- Added configurable automatic payout timing to the investment agreement: month end by default, or day 1–28 of the following month.
- Added due-settlement reconciliation to investor portal, Admin reporting and Private Investor API reads.
- Updated investor Transactions and Admin activity/reporting labels to distinguish Investment, Capital Withdrawal and Profit Paid.
- Updated Admin overview/reporting to show every investor in their own currency while maintaining total investor funds and consolidated reporting in USD.
- Added Admin-only realized FX gain/loss separation without changing investor principal.
- Updated mobile/API activity summary with separate `capital_withdrawal` and `profit_paid` fields and reconciled statement data.

## Production/data safety
- Additive migration only; no investor/user/transaction/statement/request/agreement records are deleted.
- Existing profit records are preserved while their legacy capital-increase side effect is corrected to zero.
- Historical missing performance-month references are populated deterministically for reconciliation.
- V15.7.2/V15.7.3 original-currency principal and Admin USD FX accounting rules remain intact.
- Shared-hosting `AbsSchemaRepair` includes V15.7.4 fields.

---

# ABS V15.7.3 — Investor/Admin Principal Currency Controls & Production UX Fix

## Fixed
- Added a real editable Principal Currency selector to the investor Requests screen for financially empty portfolios.
- Added investor-side direct currency update and first Add Investment request currency selection.
- Added Admin Principal Currency controls to Investment Setup, Transactions and Account Controls.
- Added first-transaction currency selection so an empty USD-default account can be changed to PKR/AED/EUR/etc. without recreating the investor.
- Made the Admin FX panel dynamic: amount labels, currency prefix, USD-rate label, rate requirement and FX quote lookup now follow the selected currency.
- Fixed the empty-account lock rule so a zero-value generated performance schedule does not prevent a legitimate currency change.
- Centralized currency lifecycle enforcement in `InvestorCurrencyService` with server-side validation and row locking.
- Prevented funded/history-bearing accounts from being relabelled through crafted requests, preserving original-currency principal protection.
- Added an open-capital-request lock: submitted/under-review/approved Add Investment or Capital Withdrawal requests must be resolved before principal currency can change.

## Production/data safety
- No database schema change in V15.7.3.
- No historical investor, transaction, statement, request, agreement, trade, payment or audit data is rewritten.
- V15.7.2 historical USD principal basis and realized FX accounting remain unchanged.
- Existing V15.7.1/V15.7.2 migrations and shared-hosting schema-repair behavior remain compatible.

---

# ABS V15.7.2 — Principal-Currency Protection & Admin FX Accounting

- Locked investor principal to original account currency; FX movement cannot alter investor capital.
- Added historical USD principal basis, withdrawal settlement USD amount and Admin realized FX gain/loss.
- Capital withdrawals now reduce local principal by the exact local amount and reduce Admin USD principal by historical weighted-average basis.
- Added over-withdrawal protection against remaining investor principal.
- Added Admin withdrawal preview and transparent basis/settlement/FX presentation.
- Added Admin realized FX to account snapshot, consolidated overview, reports and CSV export.
- Updated statement USD logic so principal is not revalued at statement FX rates.
- Updated normal migrations and shared-hosting `AbsSchemaRepair` path.
- Preserved V15.7.1 records without destructive migration or silent historical rewrite.
- Bumped web/API build identity to 15.7.2.

---

# ABS V15.7.1 — Private Investor Multi-Currency, Email Alerts & Stability

## Fixed
- Fixed the Monthly Progress page ParseError caused by an unsafe compact `@php` expression.
- Added Private Investor Blade QA for PHP blocks, echo expressions and balanced control directives.
- Investor transaction pages now clearly show whether outbound email delivery is ready or requires SMTP/sendmail configuration.

## Private Investor multi-currency
- Investor-facing portfolio values remain entirely in the investor's assigned currency.
- Added transaction-level locked FX conversion and USD accounting effects for Admin consolidation.
- Added statement-level locked FX conversion and USD reporting values.
- Added current FX reference lookup for Admin transaction/statement entry while keeping historical rate entry under Admin control.
- Admin Portfolio Overview, Monthly Progress, Investor Accounts, Reports and CSV exports use USD consolidated values.
- Internal USD accounting fields are hidden from investor/mobile serialization.

## Alerts and email
- Premium ABS Pulse branded email layout refreshed for investor account communications.
- Confirmed transactions, corrections, deletions, statements, investment-term changes and investor request lifecycle events produce investor notifications.
- Investment transaction emails include the agreed monthly rate/effective date when configured with the investment.
- Investor request cancellation now sends a confirmation email.
- Admin transaction management includes recent investor email-delivery history.

## Data safety
- Additive-only migration; no destructive database operation.
- Database Fix/Schema Repair knows the V15.7.1 multi-currency fields.
- V15.6.2 single previous-build code-only rollback remains unchanged.

## V15.8.8 — Public/Member/Admin/Auth dual-theme audit candidate
- Public final-* theme coverage and authentication dual-mode support. No trading, Firebase or DB changes.

## V15.9.1 — Cross-Route Theme Surface & Typography Normalization (testing candidate)
- Fix Pulse Open Positions, trade history, orders, reports, alerts, strategies and shared data pages: background/foreground are paired in every theme.
- Unify KPI cards, tables, form controls, empty states, hierarchy and mobile scroll containers.
- Improve member sidebar spacing and screen-readable 11–13px captions.
- Add tokenized last-loaded CSS across public/auth/member/admin shells; preserve app logic.
- All authenticated routes require real staging/browser verification before production.
