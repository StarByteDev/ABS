# ABS V14.4 Final Homepage

**Build:** ABS V14.4 Final Homepage Build  
**Date:** 10 August 2026  
**Baseline:** ABS V14.3 Complete Authentication UI Build

## Locked visual authority

The final public homepage is governed by `docs/reference/ABS_V14_4_FINAL_HOMEPAGE_REFERENCE.png`. Its deep-navy background, cyan data accents, restrained gold actions, panel borders, spacing, content hierarchy and complete footer are the development baseline.

The public header must use the exact transparent ABS emblem and the login-matched single-line wordmark **ALPHA BLOCK SOLUTIONS**, with **BLOCK** in gold. The wordmark must not be redrawn as a different font, stacked, or returned to title case.

## Developed page structure

1. Final public header with Home, Pulse Intelligence, Market News, Membership, About and Contact navigation.
2. Market-intelligence hero with Explore Pulse and View Market News actions.
3. Live BTC/USDT price, chart, Market Pulse, Market Sentiment and BTC Dominance.
4. Live BTC, ETH, SOL, BNB and XRP market row.
5. Verified Intelligence, Real-Time Market Context, Professional Research and Secure Member Access strip.
6. Five latest verified headline cards sourced from CMS and verified live providers.
7. Six platform advantages.
8. Clarity Before Action with a unique Market Overview.
9. Trial, Pulse Intelligence and Pulse Professional access cards.
10. Final Pulse conversion banner.
11. Four-column footer, legal links, newsletter subscription and risk notice.

## Data rules

- Market Sentiment and BTC Dominance appear only once each.
- Total Market Cap and 24h Volume use verified provider data already exposed by `MarketDataService`.
- Stablecoin Market Cap and 24h Liquidations remain clearly unavailable until a verified provider is configured; the UI must not invent live values.
- Headline cards retain the existing Admin CMS and verified live-source behavior.
- Pulse plan selection retains existing account, checkout and entitlement controls.

## Responsive and functional rules

- Desktop preserves the approved wide full-page composition.
- Tablet stacks the hero and market dashboard while retaining all content.
- Mobile uses a keyboard-operable collapsible navigation, single-column content, touch-sized controls and readable live-data cards.
- All actions remain real Laravel routes and forms, including sign-in, registration, Pulse access, membership, newsletter and legal pages.
