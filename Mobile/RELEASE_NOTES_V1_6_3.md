# ABS Pulse Mobile V1.6.3+163

**Backend target:** ABS V15.7.4

## Web-aligned Pulse package payment

- Removed the user-facing **Get current quote** step from Pulse Membership.
- Selecting an upgrade now automatically loads the server-authoritative package payment data in the background and presents a mobile version of the live web **Direct USDT transfer** flow.
- Mobile now shows exact package amount, access period, network, wallet address with copy action, payment instructions, TXID/hash field, optional/required proof attachment, notes, acknowledgement, and **Submit Payment for Verification**.
- The existing `/pulse/membership/quote` API remains an internal server validation step only; the user sees a payment workflow, not a quote workflow.
- Submission remains through the existing `/pulse/membership/requests` endpoint and manual ABS verification process.

## Interactive Find Best Signal on mobile

- Rebuilt the primary Pulse Signals experience around the web **Find Best Signal** workflow.
- Pulse now loads scanner overview, usage, market-data health and active qualified signals together.
- Tapping **Find Best Signal** runs the existing server scan across **15M + 4H** using `/pulse/scanner/run`.
- Added an animated radar/orbit, indeterminate scan progress, live stage copy, Universe → 15M → 4H → Rank visual progression, package-universe/qualification metrics, and a result reveal.
- The mobile client does not invent qualification. Scanner rows are treated as Best Signal candidates only when the backend marks them qualified/active or returns a persisted signal identity.
- Existing web-created/other-device signals are refreshed whenever the Pulse tab is opened, reducing stale mobile state.
- Signal payload normalization now accepts list responses plus singular `best_signal`, `bestSignal`, `signal`, or `setup` server shapes.
- When no setup qualifies, mobile clearly says that no signal was created instead of showing a misleading generic filter-empty state.

## Preserved

All V1.6.2 calendar, market overview, registration/mobile-number, activation, membership-state, Private Investor, Free Signal and template-rebase fixes remain unchanged.
