# ABS Pulse Mobile V1.5.0+150 — Template UI Production Integration

**Backend:** ABS V15.7.4

V1.5.0 rebuilds the mobile presentation around the user-provided `abs_mobile.zip` Flutter template while preserving the working ABS Pulse V1.4.1 API/session/trading foundation.

## UI / navigation

- New bottom navigation: **Home / Pulse / Free Signal / News / Account**.
- Attached template's compact premium dark-market-terminal visual language adopted across the new shell.
- All runtime identity uses **ABS Pulse / Alpha Block Solutions** and the real ABS logo assets.
- Removed dependency on the supplied template's mock-data runtime layer.
- Responsive cards/chips avoid the narrow-screen text collapse seen in earlier builds.

## Home

- Live ABS market overview, BTC chart, market pulse, sentiment, futures metrics, liquidation context, majors, movers and latest headlines.
- Full public market overview remains reachable.
- Pending-account activation and membership state are surfaced without blocking public/basic access.

## Pulse

- Production scanner/signals/watchlist data replaces mock template content.
- Scanner overview, quick scan, market-data health, signal filters and trading shortcuts retained.
- Full Pulse tools are gated by verified account + backend entitlement.

## Free Signal

- Existing V1.4.1 rewarded access retained.
- Qualified Signal / Entry Watch distinction retained.
- BTCUSDT 4H context-only fallback retained without inventing trade levels.

## News / Intelligence

- Calendar remains the default intelligence tab.
- Today / Upcoming / Previous / All calendar views retained.
- ABS News and Live headlines use resilient nested/paginated payload parsing.
- Live headline detail opens in-app first.

## Account / tools

The new Account hub exposes the production feature set: Scanner, Signals, Positions, Trade History, Orders, Strategies, Reports, Watchlist, Alerts, calculators, Binance/Risk/Markets/Execution setup, Profile, Membership, Notifications, Devices, Sessions, News, Calendar, Research, Learning, Global Search, Newsletter, Help, Services, Support, Private Investor, About and Legal.

## Activation

- Registration token is retained so a newly registered user can enter basic access immediately while activation is pending.
- Profile includes activation state, resend and status refresh.
- Full membership/trading controls remain locked until activation.

## Private Investor

V15.7.4 original-currency principal, agreed rate/effective date, automatic monthly progress, Profit Paid vs Capital Withdrawal separation, statements and requests remain intact.

## Validation

- Offline release/source validator: see `VALIDATION_REPORT.md`.
- ZIP integrity is checked during packaging.
- Flutter/Dart SDK was not installed in the packaging environment, so native analyze/test/build/emulator verification remains a local pre-deployment step.
