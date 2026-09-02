# ABS V14.6.14 — Advantage Grid + Side Card Clearance Fix

This build is a visual refinement of ABS V14.6.13 based on browser review.

## Homepage corrections

- `.final-side-card.final-pulse-card` is taller so Market Pulse score/graph content remains fully visible.
- `.final-side-card.final-liquidation-card` is taller so both Longs and Shorts values, 24H share text and mini bars remain fully visible.
- The complete hero market board is increased proportionally to keep the side stack aligned with the lower coin ticker rather than allowing content to overflow.
- `.final-advantage-grid` now follows the same visual organization as `.final-trust-grid`: bordered icon at left, title and description grouped at right, consistent vertical centering and readable spacing.
- Responsive behavior remains intact for desktop, tablet and mobile.

## Preserved functionality

No live-data providers or calculations were changed. Market prices, news, long/short liquidations, Market Pulse, sentiment, OI, funding, long/short ratio, perp basis, Fear & Greed, industries, market snapshot, movers and package pricing remain based on the V14.6.13 implementation.

Laragon/MySQL, Admin CMS, authentication, membership, Private Member Portal and Pulse workspace functionality are preserved.
