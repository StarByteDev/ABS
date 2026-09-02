# Mobile API Notes — ABS V14.8.8

V14.8.8 does not add or remove routed Mobile API operations. Existing Pulse execution APIs remain the mobile contract for opening the pre-trade ticket and submitting a signal order.

The execution endpoint now returns a controlled HTTP 422 payload when Binance/risk/execution-mode validation blocks submission. Testnet/Live environment selection and all existing manual-trading safeguards remain enforced by the shared `PulseTradeService`.
