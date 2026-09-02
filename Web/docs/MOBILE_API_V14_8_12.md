# Mobile/API Notes — ABS V14.8.12

The existing Pulse signals payload now exposes the same simplified trade-action metadata used by the web UI. No new route is required.

`trade_action` uses four user-facing phases:

- `Opportunity Spotted` → `Monitor Signal`
- `Entry Ready` → `Open Trade`
- `Trade In Progress` → `Monitor Trade`
- `Expired` → `View Signal`

The action object also contains `kind`, `detail`, `direct`, and `target`. When an active local Pulse trade exists, `trade_id` is included so mobile clients can open the trade monitor directly.

Only `Entry Ready` can be `direct: true`, and only when the existing manual-execution prerequisites are satisfied. The protected execution endpoint and all PulseTradeService safety validation remain unchanged.
