# ABS V14.6.2 — Homepage Live Data + 3D Market Board

The homepage was compared row-by-row with the approved design reference. V14.6.2 focuses on display fidelity and missing live-data wiring without changing the approved Pulse workspace, Admin CMS, authentication or MySQL architecture.

## Corrected rows

1. **Header / page width** — reduced excess desktop side margins while keeping responsive safe gutters.
2. **Hero** — preserved approved wording and CTAs; completed 24h BTC volume refresh.
3. **Final market board** — added subtle 3D depth/glass highlights; completed six-asset strip with ADA.
4. **Market Pulse / Sentiment** — aligned gauge and legend; added Bullish, Neutral and Bearish live percentages.
5. **BTC Dominance** — added live DOM value binding plus derived 24h change.
6. **Verified headlines** — retained live/CMS verified headline pipeline and approved five-card desktop layout.
7. **Advantages** — retained six approved product-capability cards.
8. **Clarity / Market Overview** — preserved total cap and volume; added stablecoin market cap, stablecoin 24h change, public Binance liquidation notional, and market-cap KPI label.
9. **Pulse plans** — retained Admin-managed plan destinations and approved three-column layout.
10. **CTA / footer** — added subtle dotted depth to CTA and retained full legal/newsletter footer.

## Data accuracy

- Spot prices, high/low/volume and chart: Binance public market data.
- Global market cap, total volume and BTC dominance: CoinGecko global data.
- Stablecoin market cap: aggregate of CoinGecko `stablecoins` category market caps.
- Stablecoin 24h change: reconstructed from current per-asset market caps and their 24h market-cap changes.
- BTC dominance 24h change: derived from current dominance plus BTC and total-market 24h changes.
- Liquidations: Binance USD-M public liquidation-order snapshot. If the 1,000-row endpoint limit is reached, the UI/source metadata treats the result as an endpoint-limited sample rather than claiming full-market coverage.

No synthetic market price is generated when a provider is unavailable.
