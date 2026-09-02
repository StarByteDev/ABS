# ABS V14.5 Final Pulse Dashboard

## Scope

V14.5 implements the approved Pulse main page shown after an authorized user signs in. It keeps Pulse inside Alpha Block Solutions, preserves the public ABS navigation at the top, and keeps the complete Pulse workspace navigation in the left sidebar.

## Final navigation rules

- The global header contains one ABS logo/wordmark and one Home link.
- The Pulse sidebar starts with Dashboard and does not repeat the company logo or company name.
- Sidebar modules remain subject to the user's active Pulse plan and per-user restrictions.
- Strategies, Trade Execution, Open Positions and Risk Controls link to the existing operational scanner, signal-review, order and settings flows.
- Testnet and Live remain visually distinct and continue to use the existing safety and plan gates.

## Dashboard data

The page reads existing application records rather than static example values:

- cached market prices and 24-hour changes;
- signed-in user's selected pairs and risk settings;
- active and high-conviction Pulse signals;
- scanner run status and enabled plan strategies;
- open and closed trade records;
- realized/unrealized P&amp;L, daily activity and win rate;
- unread alerts;
- selected-environment Binance connection status.

When a provider or account record is unavailable, the page shows an unavailable, connecting or not-connected state. It does not synthesize market prices, P&amp;L, win rate or exchange status.

## Visual authority

The final dashboard authority is:

```text
docs/reference/ABS_V14_5_FINAL_PULSE_DASHBOARD_REFERENCE.png
```

The proposed Signals page is kept separately for review and is not yet a locked implementation authority:

```text
docs/review/ABS_V14_5_PULSE_SIGNALS_CONCEPT.png
```

## Preserved functionality

V14.5 does not alter the established authentication, email activation, recovery, Pulse access, plan configuration, scanner engine, signal generation, Binance integration, execution safety gates, reports, Admin, membership/payment or Private Member logic.
