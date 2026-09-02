# ABS Mobile V1.1 Design System

## Product principle

The same application must be easy to understand for a first-time trader without becoming slow or simplistic for an experienced trader.

## Experience modes

**Simple** emphasizes sequence, explanation and confidence: setup → scan → review → execute → monitor.

**Pro** emphasizes density, metrics and fast navigation. Both modes call the same ABS APIs and obey the same server rules.

## Visual identity

- Deep near-black base for focus and premium feel.
- Cyan = intelligence, market-data and primary action.
- Gold = premium membership, LIVE caution and high-attention states.
- Green = confirmed/healthy/positive state.
- Red = blocked/risk/negative state.
- Rounded 16–24px geometry with restrained borders and shadows.
- Gradients are reserved for hero surfaces, not every card.

## Information hierarchy

1. Environment / readiness / risk state.
2. What the user should do next.
3. Trade-defining numbers: Entry, TP, SL, P&L.
4. Professional diagnostics and secondary metrics.

## Safety UX

LIVE/Testnet is never hidden by Simple/Pro mode. Execution readiness and protection status remain visible. The mobile presentation does not infer fills or closure independently of ABS/Binance reconciliation.


## V1.2.2 interaction principles
- Primary trading actions must be visible without opening More.
- Signal discovery starts with **Scan Markets Now** on Trade Signals.
- Every empty state must provide the next useful action.
- Use professional terminology: Market Scan, Trade Signals, Trade Review, Portfolio, Protection.
- Avoid informal coaching labels in production UI.
- Critical market-data and protection states must be explicit before a user can act.
