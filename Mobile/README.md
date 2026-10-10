# Pulse Flutter V1.7.5+175

Full source based on Pulse V1.7.4, targeting ABS Laravel V15.9.1.

## Quick start

```bash
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run
```

Improved Free Signal checkbox tap support, ad-loading resilience, ETF configuration messages and Farside external publisher link. Live ETF values still require the optional ABS backend patch and a licensed CoinGlass key. The app has no embedded ETF provider secrets and does not produce mock financial values.

For details and local acceptance tests, see `docs/V175_FREE_SIGNAL_ETF_QA.md`.
