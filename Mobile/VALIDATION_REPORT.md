# ABS Flutter Mobile V1.3.5 Validation Report

**Target backend:** ABS V15.1.6

**Offline source result:** 403/403 static integrity and architecture checks passed.

## V1.3.5 checks completed

- Free Signal retains the V15.1.6 rewarded-ad status / session / claim / visitor / server cooldown flow.
- The gateway is now one simple premium rewarded-access card with ABS gold treatment, risk acknowledgement and a single CTA.
- Native motion uses rotating orbit rings, a pulsing play control and animated signal bars without adding a new dependency.
- Claim response parsing now accepts `signal`, `free_signal`, `setup`, nested result/payload responses and `entry_watch` shapes.
- If `/claim` does not include the reveal directly, the app performs one visitor-bound `/status` recovery request before treating the reveal as missing.
- The mobile client no longer silently shows an empty gateway after a completed rewarded ad when the server returns no setup; a clear server-flow notice is shown.
- Qualified Signal and `ENTRY WATCH / WATCH ONLY` remain visually and semantically distinct.
- ABS Intelligence / News / Live / Economic Calendar changes from V1.3.1 are preserved.
- No direct Binance endpoint is present in Flutter source; market and execution traffic remains routed through ABS.
- Declared assets, critical screens, API references and relative imports passed the included offline validator.


### V1.3.5 Free Signal presentation checks

- Tiny crypto prices use adaptive precision instead of being rounded to misleading `$0.00` values.
- Current market price falls back to the latest candle close when a separate price field is absent.
- Missing Entry / Stop Loss / Take Profit values render as unavailable, never as fake zero levels.
- Authenticated package fallback attempts to hydrate `/pulse/signals/{id}` for complete signal levels.
- Market Context now renders candlesticks plus a close-price line and Entry/SL/TP overlays when valid levels exist.
- Entry Watch uses a concise `WATCH ONLY` action and a clear Setup Snapshot instead of a trade-looking zero-value card.
- Release ZIP is flattened so project files are at archive root.

## Runtime validation still required locally

Flutter/Dart is not installed in this build environment, so compiler, emulator and device execution must be run locally before release:

```bash
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run
```

Use Google rewarded test ads for local validation. If a completed reward still returns the new “public Free Signal service did not return a market setup” notice, the remaining fault is in the V15.1.6 Laravel Free Signal backend, not the Flutter reveal UI.

Additional static review: verified Free Signal member-fallback code path, shorter gateway copy, and updated error-state navigation.
