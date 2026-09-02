# ABS V14.8.11 — Premium Guided Signal Trade Execution

## Objective

V14.8.11 makes Signal-to-Binance execution easier to understand without weakening the existing protected execution service. The old native browser confirmation is removed from ready signal actions.

## Guided execution flow

1. **Review Signal**
   - Pair, direction, score and timeframe.
   - Current price, protected LIMIT entry, stop loss and take profit.
   - Every direction-aligned qualifying strategy.
   - Binance environment, leverage and configured sizing.
   - Estimated margin, maximum loss at SL, TP reward, risk/reward and estimated account risk.

2. **Confirm Trade**
   - Final concise order summary.
   - Separate LIVE and Testnet safety messaging.
   - Required acknowledgement of direction, entry, SL and TP.

3. **Binance**
   - The existing `pulse.signals.execute` route submits the request.
   - The final `PulseTradeService` validation remains authoritative.
   - Duplicate clicks are reduced by disabling the final submit button after submission begins.

## Action wording

- **Execute Trade** — current price is inside the signal entry zone.
- **Prepare Limit Order** — entry has not been reached; the LIMIT order can wait at the signal entry price.
- **Connect Binance** — selected environment is not verified/ready.
- **Enable Manual Trading** — Pulse execution mode is not Manual.
- **Enable Live Trading / Enable Testnet Trading** — selected environment is not allowed.
- **Execution Paused** — an emergency-stop safeguard is active.
- **Trading Not Included** — manual trading is not included in current access.
- **Signal Closed** — the signal is no longer open for a new execution.

## Advanced execution

The guided drawer includes an **Advanced Order Settings** link to the existing Trade Execution page. This preserves the detailed ticket for advanced users while keeping the default signal flow simple.

## Safety

No execution validation is moved into the browser. The drawer provides an estimate for decision support only. `PulseTradeService` still validates account equity, available balance, risk-per-trade, leverage, exchange limits, quantity/notional, SL/TP direction, permissions and emergency controls immediately before Binance submission.

**Database:** no migration is required.
