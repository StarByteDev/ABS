# Mobile API V14.8.16

`POST /api/v1/pulse/scanner/run` accepts `timeframe=all` in addition to the existing single timeframe values. `all` performs one scanner run across 1H and 4H. Result/signal payloads preserve the actual timeframe per setup. Existing execution endpoints and risk validation are unchanged.
