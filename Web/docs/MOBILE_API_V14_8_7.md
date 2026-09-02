# Mobile API Notes — ABS V14.8.7

V14.8.7 keeps the routed Mobile API operation count unchanged while aligning scanner behavior with the web application.

- `POST /api/v1/pulse/scanner/run` without a `symbols` array scans the authenticated user's saved, package-approved `selected_pairs`. If 77 markets are selected, the normal mobile scan evaluates those 77 markets.
- A supplied `symbols` array remains a targeted scan and every symbol is checked against package access.
- Scanner/signals use the saved user `minimum_signal_score` when present; otherwise the plan `minimum_signal_score` is used, then the server fallback.
- `GET /api/v1/pulse/settings` returns `effective_minimum_score` and `minimum_score_source` so Flutter can show the same effective threshold as web.
- Package pair allowance and selected-pair limits remain enforced server-side.
- Daily scan/signal counters use completed scan timestamps and generated signal timestamps consistently across web and API.
