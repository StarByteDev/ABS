# ABS V15.6.1 — Market Intelligence Detail & Economic Calendar Reliability

## Customer-facing market intelligence

Live RSS headlines continue to resolve to the internal `/news/live/{id}` route. The News page now calls `LiveNewsService::latest()` so an empty cache no longer produces an empty headline section. Feed text is normalized and retained in bounded summary/detail fields, then presented with a structured ABS Pulse market brief rather than implementation-oriented copy.

`NewsMarketBriefService` derives a non-execution market context from the headline title and feed summary. It classifies a market theme, risk read, impact level, assets in focus, why-it-matters context and a short watchlist. It does not modify Pulse strategy scores, signal levels or trade execution.

The customer-facing copy avoids development/process terms. The member signal explanation action is labelled `Pulse Insight` while keeping the existing backend explanation route and stored field compatibility.

## Economic calendar source chain

The calendar source order is:

1. Financial Modeling Prep when an administrator has configured an API key.
2. Finance Calendar as the first keyless fallback.
3. Xoomar Macro Calendar as a second keyless fallback focused on US releases.
4. Trading Economics only when explicitly enabled with configured credentials.

The application no longer assumes a default Trading Economics guest credential. Every successful sync is normalized into the existing `economic_events` table and retains source attribution.

The public News page considers the calendar stale when there is no relevant event in the current -30/+45 day window or the last successful sync is more than two hours old. A short cache lock prevents repeated public requests from causing excessive provider calls.

## Compatibility

- No schema migration.
- Existing `EconomicEvent` columns are reused.
- V15.6.0 Find Best Signal progress UX remains unchanged.
- V15.5.0 system multi-signal research and paper-trade lifecycle remain unchanged.
- Core `PulseScannerService.php` remains unchanged.
- Production ABS master logo remains unchanged.
