# ABS V15.8.0 Validation Report

## Root cause found for "SL or expired after entry, no TP"
The prior lifecycle reused the signal's 90-minute `expires_at` as the post-entry trade expiry. A setup could therefore enter late in the signal window and be resolved as `expired_after_entry` almost immediately. In addition, central 1-minute validation candles were synchronized only for signals generated in the previous four hours, which could stop TP/SL observation for longer-running research trades.

V15.8.0 separates these concepts:
- before entry: the configured signal validity window still applies;
- after entry: 15M trades default to 24 hours and 4H trades default to 7 days;
- unresolved validations keep their 1M validation-candle feed for up to 14 days;
- TP/SL order inside the entry candle remains intentionally unscored because OHLC cannot prove intraminute event order.

## Reporting
Admin Trade Results now exposes TP1, TP2, TP3+, final TP, SL, open, expired-no-entry, expired-after-entry and ambiguous evidence with filtering. Strategy Intelligence exposes milestone counts beside final TP/SL, Net R and Learning Score.

## Learning
The established 75% technical + 25% learned reliability signal-confidence formula remains. TP1/TP2/TP3 contribute a capped incremental bonus to learned strategy reliability. Full TP/SL outcomes remain dominant and historical records are never rewritten.

## Scanner universe
Authenticated Find Best Signal runs now use all enabled Binance USD-M perpetual markets synchronized in `pulse_pairs`, regardless of package pair assignments. Scanner capability itself still requires valid member access.

## Static validation performed
All modified PHP files pass `php -l`. New database work is additive/non-destructive and the down migration intentionally does not delete strategy history.
