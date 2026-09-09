# Alpha Block Solutions API Guide — V15.1.0

## Commerce model

Pulse paid access uses **direct USDT payment verification**. A registered member selects an Admin-enabled package, transfers the exact USDT amount to the configured wallet/network, and submits the transaction reference plus optional proof. An administrator reviews the payment and activates the package. There is no account credit wallet and there is no per-signal charge for an active package.

Key authenticated API endpoints:

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/pulse/plans` | List direct-USDT Pulse packages and capabilities |
| GET | `/pulse/membership` | Current access and payment-request history |
| POST | `/pulse/membership/quote` | Quote a package/promotion |
| POST | `/pulse/membership/requests` | Submit USDT transaction reference/proof for Admin verification |
| PATCH | `/pulse/membership/requests/{membershipRequest}` | Cancel an owned open request |
| POST | `/pulse/scanner/run` | Run the Admin-controlled Best Signal scan with active package access |
| GET | `/pulse/signals` | Member signals |
| GET | `/pulse/usage` | Active-package compatibility/usage summary |

## Public rewarded Free Signal

The no-registration Free Signal is intentionally a **web route**, not an authenticated mobile API economy:

- `GET /pulse/free-signal`
- `POST /pulse/free-signal/ad-session`
- `POST /pulse/free-signal/claim`
- `GET /pulse/free-signal/status`

A visitor explicitly opts in to Google rewarded web advertising. After the browser receives Google's rewarded-slot grant event, ABS reveals one random qualified Pulse setup in the current page session and applies the configured browser cooldown (default 30 minutes). The signal remains visible until the visitor refreshes or leaves the page; refreshing does not bypass the cooldown and the prior signal is not re-exposed by the server. The public UI and Google Ad Manager unit are controlled from **Admin → Rewarded Signal Ads**.

See `docs/openapi.yaml` for the authenticated `/api/v1` contract.
