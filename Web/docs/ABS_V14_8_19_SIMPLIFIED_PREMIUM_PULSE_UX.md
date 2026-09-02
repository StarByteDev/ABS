# ABS V14.8.19 — Simplified Premium Pulse UX

This release removes the redundant standalone member Trade Execution workflow and makes the Signals Open Trade drawer the single manual execution surface.

## UX changes
- Trade Execution removed from the signed-in sidebar.
- Legacy `/pulse/execution` redirects to the appropriate Signals Open Trade drawer.
- Strategy page uses auto-height cards and normal-flow responsive insight sections; no fixed-height overlap is possible.
- Strategy information is reduced to essential operational fields.
- Open Trade is a single confirmation flow with Entry, SL, TP, Environment, Leverage, Trade Size and Max Loss.
- Incomplete Binance/manual-trading setup routes users to the exact setup page from the drawer.
- Execution errors return to the same drawer.

## Preserved architecture
V14.8.17 central market pricing, 15M/4H scanner buffers, 1M validation, immutable signal snapshots, signal reporting, strategy learning, mobile APIs and scheduler behavior are unchanged.
