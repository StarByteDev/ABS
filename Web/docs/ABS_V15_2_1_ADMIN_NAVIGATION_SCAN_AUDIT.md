# ABS V15.2.1 — Categorized Admin Navigation & Scan Audit

V15.2.1 is an additive Admin usability and strategy-governance upgrade on V15.2.0. It does not change the 15-strategy scoring engine, Free Signal logic, Ads CMS, market-data cadence, validation rules, or exchange execution logic.

## Categorized Admin navigation

The left Admin rail is now grouped by operational purpose instead of presenting one long mixed list:

- **Overview & Members** — Users Dashboard, Members, Pulse Packages, Package Payments.
- **Strategy Engine & Evaluation** — Strategy Dashboard, Scan Audit, Pulse Operations, Strategy Intelligence, Signals & Outcomes, Trades & Execution, Market Feed & Cron, Strategy Controls, Market Universe, Safety & Automation, Audit Trail.
- **Revenue & Advertising** — Rewarded Signal Ads and Ads CMS.
- **Content & Communications** — Alerts & Emails and Content CMS.
- **Reporting & System** — Portfolio Reports and Platform Settings.

Each category can be expanded/collapsed. The full sidebar can also be collapsed into an icon rail or hidden completely. A persistent **Show menu** tab restores a hidden sidebar. Desktop sidebar state and category state are stored locally in the browser; mobile uses the normal slide-out menu.

## Scan Audit & Traceability

Admin → Strategy Engine & Evaluation → **Scan Audit** gives a dedicated scan-by-scan operational register. It answers:

- how many scans were performed;
- exact scan date/time, completion time and duration;
- whether the run was background research, a system scan, or a member scan;
- how many markets/pairs and timeframe evaluations were processed;
- how many evaluations were unavailable;
- how many candidates crossed the qualification threshold;
- whether a Best Signal was published;
- the signal's market, direction, timeframe and confidence;
- whether entry was observed;
- whether the research outcome became TP, SL, ambiguous, expired before entry, expired after entry, or remains open;
- whether a real backend `PulseTrade` record was created;
- actual exchange trade status, close reason, realized P&L and fees.

The register separates **research market outcome** from **actual exchange execution** so a paper TP/SL can never be mistaken for a Binance trade.

## Individual scan trace

Opening a scan displays one immutable lifecycle:

`SCAN → MARKETS → QUALIFIED → SIGNAL → ENTRY → RESULT → EXECUTION`

For system/research scans, the stored scanner summary is rendered market-by-market with timeframe, direction, score, qualification state, levels, top contributing strategies and data errors. The page also exposes the scanner completion audit context for investigation without making raw JSON the primary UI.

## Database impact

No new database migration is required. V15.2.1 reads existing `pulse_scanner_runs`, `pulse_audit_logs`, `pulse_signals`, `pulse_signal_validations` and `pulse_trades` evidence.
