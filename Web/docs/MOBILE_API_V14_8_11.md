# Mobile API Notes — ABS V14.8.11

V14.8.11 does not add or remove API routes.

Signal action metadata now uses clearer user-facing wording from the shared Pulse page-data layer, including `Execute Trade`, `Prepare Limit Order`, `Connect Binance`, `Enable Manual Trading`, `Enable Live Trading`, `Execution Paused`, `Trading Not Included`, and `Signal Closed` where applicable.

Mobile clients should continue to submit execution through the existing protected trade endpoint and must not treat client-side preview calculations as authoritative. Server-side PulseTradeService validation remains final.
