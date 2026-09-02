# ABS V14.6.9 — Confirmed Premium Homepage + Live Market Validation Build

**Date:** 22 August 2026  
**Baseline:** ABS V14.6.8 Premium Readability + Density Refinement Build

## Confirmed visual baseline

The homepage is locked to the user-confirmed premium composition in `ABS_V14_6_9_CONFIRMED_PREMIUM_HOMEPAGE_REFERENCE.png`. The release keeps the dark navy/black surface, warm gold borders/actions, cyan market-chart treatment, compact card density, readable serif hero hierarchy, and the confirmed section order.

## Homepage sections preserved

1. Premium public header/navigation.
2. Hero message and CTA area.
3. BTC/USDT live market board with 24H range/volume, chart controls and five-asset bottom strip.
4. Market Pulse and Market Sentiment as separate metrics.
5. 24H Liquidations split into Longs and Shorts.
6. Futures Insights with exactly Open Interest and Funding Rate.
7. Four trust/value cards.
8. Five Latest Verified Headlines.
9. One Intelligence Platform / Multiple Market Advantages.
10. Top Crypto Industries.
11. Daily market intelligence snapshot: Market Cap, 24H Trading Volume, BTC Dominance, Fear & Greed, Market Bias, Stablecoin Flow, Volatility, Key Trend and Daily Insight.
12. Market Movers with Top Gainers and Top Losers.
13. Pulse Trial, Intelligence, Professional and Enterprise plans.
14. Premium CTA and footer.

## Live-data sources

- **Binance public market data:** spot prices, 24H tickers, chart candles and movers.
- **Binance USD-M public market data:** BTC open interest and funding-rate context.
- **CoinGecko:** global market cap, 24H trading volume, BTC dominance, stablecoin market-cap data and category/industry data.
- **Alternative.me:** Fear & Greed Index. The source is shown beside the metric as required by the provider.
- **Xoomar Pulse:** public 24H aggregated liquidation totals with separate long/short values. The integration uses the documented no-key endpoint and no longer relies on a Binance signed user-force-order endpoint.
- **Verified RSS publishers + ABS editorial CMS:** homepage/news-page headlines. If an external feed is unavailable, the application preserves editorial content and does not invent headlines.

## Accuracy/fallback policy

The public UI does not manufacture substitute market numbers. When a provider cannot be reached, the affected field stays in a clear `Syncing…`, `Unavailable`, or equivalent state while the remaining providers continue to render. Liquidation percentages shown beneath Longs/Shorts are their share of the current 24H liquidation total, not a fabricated day-over-day percentage.

## Existing application preserved

V14.6.9 keeps the V14.6.x MySQL/Laragon configuration, Admin CMS, authentication, membership controls, Private Member Portal, Pulse workspace, scanner, signals, strategies, execution, positions, risk, reports, alerts and Binance integration intact.
