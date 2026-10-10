# ABS V15.8.3 Release Notes

**Release:** Mobile API Compatibility, Adaptive Strategy Optimizer & Premium Dark Theme  
**Base:** ABS V15.8.2  
**Release date:** 2026-10-08

V15.8.3 is a production-safe follow-up to V15.8.2. It keeps the existing customer, investor, signal, trade and mobile API contract intact while addressing three live observations: a mobile connectivity failure caused by an invalid TLS certificate for `alphablocksolutions.com`, broad negative strategy attribution, and an overly black/high-contrast Dark theme.

## Mobile/API compatibility and TLS diagnosis

A source comparison of the V15.7.4 and V15.8.2 API route/controller contract found no removal or renaming of the existing Pulse mobile endpoints. The supplied browser screenshot shows `NET::ERR_CERT_COMMON_NAME_INVALID` at `https://alphablocksolutions.com/api/v1`. That error occurs during HTTPS certificate validation before Laravel receives the request; application PHP cannot repair it.

V15.8.3 therefore preserves every existing mobile route/field and adds only additive diagnostics:

- `GET /api/v1`
- `GET /api/v1/health`

The probe returns the API/build identity and `mobile_api_compatible=true`. It is intentionally unauthenticated so a mobile/network check can distinguish a reachable Laravel API from authentication or account issues. The existing Bootstrap, authentication, scanner, signals, trades, reports, private-investor and notification endpoints remain unchanged.

The hosting certificate must still be corrected separately. See `docs/MOBILE_API_TLS_RECOVERY_V15_8_3.md`.

## Adaptive strategy loss-control engine

V15.8.3 does **not** rewrite historical losses or claim that a strategy can be guaranteed profitable. Instead, it changes how live/member candidates use accumulated evidence while preserving raw research collection.

- Raw/system research continues to evaluate enabled strategies so losing strategies keep producing evidence rather than disappearing from the dataset.
- Member-facing scans use an adaptive strategy profile derived from strategy/version/timeframe/direction and, when sufficiently sampled, market regime.
- Strategy weighting is automatically reduced when evidence shows negative expectancy and can be increased modestly when evidence is consistently positive.
- Mature strategies with clearly negative evidence can be marked `research_only`, preventing them from contributing to member Best Signal selection while their research evaluation continues.
- New member candidates require at least two directional contributors and at least one Core Strategy or Entry Trigger; a collection of confirmations alone cannot create a trade.
- Market-regime and risk filters can block a candidate but no longer invent a LONG/SHORT direction.
- A fundamental/macro risk gate uses the existing ABS economic-calendar database to pause new member candidates inside a configurable window around high-impact crypto-relevant/USD events. It is a risk filter only and never guesses trade direction from a headline.

### Corrected strategy roles

Three components that previously could create misleading directional attribution are corrected:

- **ATR Volatility Filter** → neutral market-regime filter.
- **Range Compression** → neutral market-context filter.
- **Risk/Reward Quality** → neutral risk gate.

The migration also fills missing strategy categories/settings without overwriting Admin-customized values and ensures the previously introduced Trend + Volume Confluence, MACD + RSI Confluence and Breakout Momentum rows exist if an earlier strategy migration was skipped.

## Evidence-driven R:R tuning

Learning now evaluates a conservative R:R candidate grid using the actual recorded Maximum Favorable Excursion (MFE) and Maximum Adverse Excursion (MAE) in R units from resolved paper validations.

Candidate targets are tested at 1.00R, 1.25R, 1.50R, 1.75R, 2.00R, 2.25R, 2.50R and 3.00R. Each candidate records sample count, synthetic target hits, losses, unresolved cases, win rate, expectancy and profit factor. To avoid overstating results when TP and SL may both have been crossed inside a historical observation window, ambiguous ordering is not credited as a clean win.

For member-facing signals, target adjustment is deliberately bounded to a maximum of 0.50R above or below the configured strategy target. This prevents sudden target changes from a small or temporary sample.

The existing Pulse confidence architecture remains intact: **75% technical evidence + 25% learned reliability**. V15.8.3 improves the evidence feeding that model rather than replacing it.

## Strategy Performance UI

Admin Strategy Performance now shows the configured role together with:

- configured R:R versus Adaptive R:R;
- Adaptive Weight multiplier;
- observed/estimated expectancy where sufficient evidence exists;
- status: Collecting, Adaptive, Preferred, Throttled or Research Only;
- member eligibility versus research-only evaluation;
- the existing $100 virtual P/L, ROI, TP milestones, Final TP/SL, Win Rate and Net R evidence.

This makes it clear that strategy-row P/L is diagnostic attribution and that the overall unique-trade P/L remains the correct total-system figure.

## Premium Dark theme refresh

A V15.8.3 theme layer replaces the near-black appearance with a navy/graphite institutional palette, clearer card hierarchy, softer borders, stronger text contrast, cyan interaction states, restrained gold accents, and clearer green/red outcome colors. The Light theme is retained and receives matching component polish. Theme preference remains persistent and no separate duplicated UI is introduced.

## Mobile behavior

The adaptive scanner runs on the backend, so the currently published mobile application automatically receives the improved signal selection once the HTTPS certificate is valid. The strategy performance API keeps all legacy fields and adds an optional `adaptive` object. Signal `strategy_evidence` also keeps its legacy keys and adds optional role/adaptive R:R/weight/eligibility fields. Older mobile builds can ignore the new keys safely.

No Flutter source/binary is included in this web/backend package; V15.8.3 therefore does not claim to rebuild the mobile client itself.

## Production/data safety

- No users, memberships, payments, investor transactions, statements, signals, trades, learning history or scanner history are deleted or reset.
- The V15.8.3 migration is idempotent and data-preserving. It fills missing strategy settings/rows only; existing Admin settings win.
- Existing mobile API routes and fields are preserved.
- Existing V15.8.1 investor effective-date/reinvestment accounting and V15.8.2 Scan Audit/$100 virtual reporting remain in place.
- Rollback intentionally does not delete strategy rows/settings that may already have accumulated production evidence.

## Recommended deployment

1. Take the normal application/database backup.
2. Deploy V15.8.3 over V15.8.2 without deleting `.env`, storage or customer data.
3. Run `php artisan migrate --force`.
4. Run `php artisan optimize:clear`.
5. Run `php artisan abs:pulse-analytics-backfill --days=30` once to refresh daily evidence and the adaptive learning state from the retained validation history.
6. Correct/reissue the production TLS certificate and verify `https://alphablocksolutions.com/api/v1/health` returns JSON without a browser certificate warning.
7. Test login, Bootstrap, scanner, signals and reports from the published mobile app before considering the deployment complete.

Full Laravel runtime/integration tests require the deployment/vendor dependencies and production-like database. The release package itself is validated statically and with the V15.8.3 release contract.
