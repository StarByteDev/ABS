# ABS V14.6.7 — Premium Professional Public Experience

## Approved visual reference

`docs/ABS_V14_6_7_PREMIUM_PROFESSIONAL_HOMEPAGE_REFERENCE.png`

## Implemented homepage direction

- Deep navy page background with restrained cyan ambience and gold emphasis.
- Serif hero headline with gold “Confidence”.
- Premium BTC/USDT live market board with gold edge and compact five-asset ticker strip.
- Four right-side market cards: Market Pulse, Market Sentiment, BTC Dominance, and 24H Liquidations.
- Market Sentiment donut shows the centered numeric score only; there is no percent sign beneath the score.
- Market Pulse is a separate ABS composite indicator and is not a duplicate of Market Sentiment.
- Market-source row reflects the actual application ecosystem: Binance, CoinGecko, verified publishers and ABS editorial.
- Latest Verified Headlines, platform advantages, Clarity Before Action, Market Overview, Pulse Access, CTA and footer follow the approved premium visual hierarchy.

## Market Pulse calculation

The ABS Market Pulse combines four live components when available:

- 35% Market Sentiment score.
- 30% positive breadth across BTC, ETH, SOL, BNB, XRP and ADA.
- 20% total-market-cap 24-hour momentum.
- 15% BTC 24-hour momentum.

The score is bounded to 5–95. Labels are Bullish at 65+, Defensive at 35 or below, and Neutral otherwise.

## Accuracy decisions

- The homepage does not claim Coinbase, CME Group or Kaiko endorsements. The source row names integrations/content sources actually used by the application.
- Static decorative “sector performance” percentages were replaced with live Core Assets Performance values.
- Plan prices are rendered from Admin-managed Pulse Plan records. Fresh installs seed 29 USDT/month for Pulse Intelligence and 79 USDT/month for Pulse Professional; existing plan records are not overwritten.
- Liquidations remain sourced through the existing Binance Futures public-feed logic, including unavailable/endpoint-limited states.

## Preserved systems

V14.6.7 does not remove the V14.6.x MySQL/Laragon setup, Admin CMS, authentication, user management, membership, Private Member Portal, live headlines, Pulse scanner, signals, strategies, execution, positions, risk controls, reports, alerts, Binance connection or settings workflows.
