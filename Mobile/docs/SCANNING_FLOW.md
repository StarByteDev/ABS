# ABS Mobile Market Scan Flow

## User flow
1. Open **Signals**.
2. Tap **Scan Markets Now**.
3. If markets are not selected, ABS opens **Trading Setup → Markets**.
4. ABS checks central market-data health.
5. ABS scans the user’s saved package-approved markets.
6. ABS evaluates 15M + 4H in the quick scan.
7. Enabled plan strategies are scored against central candle buffers.
8. The user’s minimum signal score is applied.
9. Only qualifying LONG/SHORT setups can become signals.
10. Existing active signal values are not rewritten.
11. The user opens **Review Signal** before any execution decision.
12. Trade execution remains protected by ABS readiness, Binance connection, risk and protection checks.

## Data path
```text
Binance Futures public market data
        ↓
ABS one-minute central collection
        ↓
ABS MySQL prices + 15M / 4H candle buffers
        ↓
PulseScannerService
        ↓
Qualified immutable PulseSignal
        ↓
ABS Mobile Trade Signals
```

The Flutter app does not call Binance market endpoints directly.
