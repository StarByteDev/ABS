# ABS V15.6.5 — Private Investor Portfolio Management

## Purpose
V15.6.5 adds a dedicated Private Investor layer above normal ABS Pulse access. A Private Investor keeps Pulse Professional access and receives a private portfolio workspace for valuations, monthly performance statements, capital activity, service requests and support.

## Member experience
- **Investor Portfolio** — latest total investment, portfolio value, reported profit/loss, overall return, latest monthly result and 12-month valuation trend.
- **Statements** — monthly opening balance, contributions, withdrawals, reported profit/loss, closing balance and monthly return; each statement can be printed/saved as PDF by the browser or exported to CSV.
- **Transactions** — complete account-level contributions, withdrawals, profit/loss entries, fees and adjustments.
- **Requests** — additional investment, withdrawal and portfolio-review requests with status/history. Requests do not move money automatically; Admin confirms the activity and records the corresponding transaction.
- **Pulse Support** — the same support conversation remains available for portfolio questions and Admin contact.

## Admin experience
Admin → **Private Investors** contains:
- **Portfolio Overview** — investor count, combined valuation, net investment, reported P/L, latest monthly result, open requests and performance trend.
- **Investor Accounts** — create/convert Private Investor users, review Pulse access, configure or open portfolios.
- **Requests** — review additional-investment/withdrawal/review requests and update status/notes.
- **Statements** — monthly reporting across investors.

Each individual investor account includes portfolio valuation controls, transaction posting, monthly statement publishing, request context, recent history and a direct support conversation action.

## User provisioning
Admin can create a new login directly or convert an existing registered user to **Private Investor**. Private Investor activation creates a zero-balance portfolio record when missing and ensures Pulse Professional access is assigned when the user has no Pulse plan already. Existing historical `private_member` accounts remain compatible and are presented as Private Investor accounts.

## Request lifecycle
`Submitted → Under Review → Approved → Completed` (or Declined/Cancelled).

Approval is intentionally separate from balance changes. After funds or a payout are confirmed, Admin records the matching portfolio transaction and then marks the request completed. This prevents a request click from changing financial records automatically.

## Mobile/API parity
Authenticated Private Investor endpoints:
- `GET /api/v1/private/account`
- `GET /api/v1/private/transactions`
- `GET /api/v1/private/statements`
- `GET /api/v1/private/statements/{statement}`
- `GET /api/v1/private/requests`
- `POST /api/v1/private/requests`
- `PATCH /api/v1/private/requests/{portfolioRequest}/cancel`

Mobile bootstrap advertises `private_investor_portfolio`. Pulse Support remains shared across web/mobile accounts.

## Production safety
The release is additive and preserves all existing production records. It adds `portfolio_requests` and safely widens the legacy MySQL `users.role` ENUM to a VARCHAR when required. No user, membership, payment, signal, trade, portfolio, statement or strategy record is removed or reset. V15.6.2 one-build code rollback remains intact and continues to preserve the live database during restore.
