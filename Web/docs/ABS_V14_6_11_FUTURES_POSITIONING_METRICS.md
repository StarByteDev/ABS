# ABS V14.6.11 — Futures Positioning Metrics

## Approved change

V14.6.11 keeps the V14.6.10 premium wide/readable homepage unchanged and fills only the intentional reserved space beneath the right-side Futures Insights card.

### Added metrics

- **Long / Short Ratio** — BTCUSDT Binance Futures global account ratio, 5-minute public snapshot. A value above 1 indicates more long accounts than short accounts; below 1 indicates the reverse.
- **Perp Premium Basis** — current BTC perpetual mark-price premium/discount relative to the Binance Futures index price: `(markPrice - indexPrice) / indexPrice × 100`.

### Layout rule

The new metrics are rendered in one compact two-column strip immediately after `.final-side-card.final-futures-card`. The existing hero-board height, Futures Insights OI/Funding card and bottom coin-price strip are not enlarged or moved.

### Live behavior

Both values render server-side when available and refresh from the existing public market overview endpoint through the homepage JavaScript. If Binance does not return either metric, the affected field remains in a clear syncing state instead of showing a fabricated value.
