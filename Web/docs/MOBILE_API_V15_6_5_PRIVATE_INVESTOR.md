# ABS Mobile API — V15.6.5 Private Investor

The Flutter client should show the Investor Portfolio module only when account bootstrap returns `private_investor_enabled: true` (or the user role is a supported Private Investor role).

## Portfolio overview
`GET /api/v1/private/account`

Use the returned account for total investment (`net_contributions`), latest portfolio value, total reported P/L, monthly P/L, overall return, monthly return, valuation date, recent transactions/statements and open-request count.

## Statements
- `GET /api/v1/private/statements`
- `GET /api/v1/private/statements/{statement}`

## Transactions
- `GET /api/v1/private/transactions`

## Requests
- `GET /api/v1/private/requests`
- `POST /api/v1/private/requests`
  - `type`: `add_investment`, `withdrawal`, or `portfolio_review`
  - `amount`: required for add-investment/withdrawal; omitted for portfolio review
  - `message`: optional
- `PATCH /api/v1/private/requests/{portfolioRequest}/cancel`

Money movement is not performed by these endpoints. They create a review request. Admin records confirmed portfolio transactions separately.

## Support
Use the existing V15.6.4 shared support endpoints. A Private Investor can continue the same support conversation across web and mobile.
