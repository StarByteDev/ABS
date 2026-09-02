# ABS V14.8.20 — Guided Self-Configured Trading UX

## Customer-flow objective

The customer should not need to understand internal Pulse execution-mode configuration before opening an eligible signal trade. V14.8.20 keeps advanced controls available, but the normal path is now:

**Signal → Open Trade → Review Entry / SL / TP → Confirm & Open Trade → Pulse monitors the Binance order**

## Managed defaults

For an eligible signal-only account, the first explicit confirmed trade activates guided manual execution automatically. Pulse retains saved leverage, sizing and risk preferences, uses the signal LIMIT entry, and revalidates Binance permission, balance, margin, plan quota, Emergency Stop and TP/SL direction immediately before submission.

ABS does not self-configure items that require genuine customer or administrator action: Binance API credentials, plan entitlement, Live-environment eligibility, Emergency Stop release or global execution enablement. The drawer directs the customer to the single required next step instead.
