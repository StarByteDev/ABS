# ABS V15.8.2 Release Notes

## Purpose
V15.8.2 is a production-safe stability and reporting release on top of V15.8.1. It fixes the live Admin Scan Audit MySQL sort-memory failure and makes paper strategy performance financially intuitive through a standardized $100 virtual-position model.

## 1. Scan Audit shared-hosting fix
The live error was:

`SQLSTATE[HY001]: Memory allocation error: 1038 Out of sort memory`

The failing query sorted complete `pulse_scanner_runs` rows by `started_at`. Those rows include a large JSON `summary`, so MySQL can allocate a large filesort buffer even when only 30 rows are ultimately displayed.

V15.8.2 changes Audit & History pagination to:
1. Apply the existing date/status/source/outcome filters.
2. Paginate only `pulse_scanner_runs.id`, newest ID first.
3. Hydrate the 30 matching full models and relationships in a second query.
4. Preserve the same LengthAwarePaginator, filters, total count and UI.
5. Process date-range JSON evidence summaries in bounded 100-run chunks instead of hydrating the full range in memory.

An additive `started_at,id` index is also attempted by the normal migration and Schema Repair paths. If the shared host prevents index creation, the ID-first paginator still avoids the original JSON-heavy filesort.

## 2. Standard $100 virtual paper model
For reporting only, every decisive paper validation is replayed as an unleveraged $100 position:

- LONG P/L = `$100 × (exit - entry) / entry`
- SHORT P/L = `$100 × (entry - exit) / entry`
- Final TP uses the configured final take-profit level.
- SL uses the configured stop-loss price.
- Open, expired and ambiguous outcomes are not counted as realized virtual P/L.
- No leverage, trading fees, funding, slippage or compounding is included.

The model does not change signal generation, confidence scoring, learning, exchange execution, investor accounting or any customer balance.

## 3. Strategy Intelligence reporting
Admin Strategy Intelligence now shows:
- Signals / Entries
- TP1 / TP2 / TP3+ milestones
- Final TP / SL
- Final success rate
- Gross virtual profit
- Gross virtual loss
- Net virtual P/L
- Virtual ROI
- Net R
- Learning Score
- Evidence state
- Direct link to the underlying trades for that strategy

The overall header shows unique-trade totals so the operator can see actual system-level virtual performance without double counting.

### Attribution warning
A signal can contain more than one contributing strategy. Per-strategy P/L is therefore diagnostic attribution and rows can overlap. The overall Net Virtual P/L is calculated once per unique validation and is the correct system total.

## 4. Trade Results reporting
Trade Results now supports a Strategy filter in addition to outcome filters. Every displayed resolved trade shows its own $100 virtual P/L alongside TP progress, outcome, MFE and duration.

## 5. Production safety
- Existing users/customers are preserved.
- Memberships/payments are preserved.
- Private Investor accounting is unchanged.
- Signals, validations, trades and strategy-learning history are preserved.
- Existing mobile/API routes and fields are preserved.
- Database change is additive only: performance index creation.
- Migration rollback intentionally does not remove the performance index.

## Deployment note
Deploy through the normal ABS backup/patch path, then run the normal migrations and clear application/view/config caches. No MySQL server-level sort-buffer change is required for this fix.
