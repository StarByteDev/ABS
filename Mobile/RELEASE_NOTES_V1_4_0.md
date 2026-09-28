# ABS Pulse Mobile V1.4.0+140 — Release Notes

> Superseded by V1.4.1+141. Kept for release history.

Target backend: **ABS V15.7.4**

This release is based on the currently deployed V1.3.5 mobile source and keeps the existing
server-authoritative trading architecture intact while aligning the mobile experience with the
latest production web/backend direction.

## Main user-facing changes

- New V15.7.4 Private Investor experience.
- New beginner-focused Help Center.
- Simple / Pro trading experience retained.
- Trading bottom navigation renamed from Portfolio to Positions to avoid investor-account confusion.
- Free Signal can show BTCUSDT 4H context when no signal/setup exists, without inventing trade levels.
- Release metadata and compatibility checks now target backend V15.7.4.

## Private Investor

The mobile view supports:

- original principal currency and amount;
- USD equivalent captured at the investment effective date when supplied;
- agreement effective date and monthly performance rate;
- automatic current-month performance progress;
- Profit Paid as a separate external distribution;
- Capital Withdrawal as principal return only;
- monthly statements and transaction history;
- additional-investment / principal-withdrawal requests;
- secure web fallback if the deployed backend has not exposed a mobile request route.

## Testing before production

Run:

```text
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run --dart-define=ABS_API_BASE_URL=https://alphablocksolutions.com/api/v1
```

Then follow `LOCAL_TEST_GUIDE.md` before creating the store release bundle.
