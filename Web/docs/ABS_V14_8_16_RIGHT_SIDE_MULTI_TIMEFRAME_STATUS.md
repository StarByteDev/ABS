# ABS V14.8.16 UX and Scanner Contract

## Signal execution drawer
Desktop execution opens from the right. Mobile remains a bottom sheet. The footer renders only stage-valid controls so hidden actions cannot consume space or remain visible.

## Scanner status
Scanner Results and Pulse Signals expose a separate Status column. The status is the live price-driven stage: Entry Ready, Move in Progress, Entry Watch, Trade Open, or Signal Closed. The Action column remains separate.

## Multi-timeframe scanner
The previous Run Market Scan form submitted 1h regardless of the visible 1H · 4H filter. V14.8.16 resolves this. `timeframe=all` evaluates 1H and 4H within a single scanner run and consumes one scan run quota. Each summary row stores its timeframe, and active signal identity includes symbol + direction + timeframe so 1H and 4H signals do not overwrite one another.
