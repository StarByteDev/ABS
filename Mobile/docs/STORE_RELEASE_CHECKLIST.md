# ABS Mobile — Store Release Checklist

## Backend
- Deploy ABS V14.9.2 or later.
- Confirm `/api/v1/bootstrap` is reachable over HTTPS.
- Confirm Admin -> Market Data & Cron Health is healthy and central price age is within target.
- Confirm mobile maintenance/minimum/recommended version settings are correct.
- Confirm registration and package-subscription administrator emails are working.

## Flutter quality gate
### Windows
Run:
`tool\\bootstrap_windows.bat`

### macOS
Run:
`chmod +x tool/*.sh && ./tool/bootstrap_macos.sh`

Do not continue to a store release unless analyzer and tests pass.

## Android
- Confirm application ID `com.alphablocksolutions.abs`.
- Configure release keystore outside source control.
- Build `flutter build appbundle --release`.
- Install/test a release APK on at least one current Android device.
- Verify Internet connectivity, secure token persistence and logout.
- Verify Testnet trade lifecycle before enabling LIVE use.
- Complete Google Play Data Safety and financial/trading declarations applicable to the product.

## iOS
- Confirm bundle identifier `com.alphablocksolutions.abs`.
- Select the correct Apple Developer Team.
- Configure signing/provisioning in Xcode.
- Run `flutter build ios --release --no-codesign` before archive/signing.
- Validate on a physical iPhone.
- Complete App Privacy and financial/trading declarations applicable to the product.

## Trading acceptance
- Test Testnet connection/save/test/activate/delete.
- Verify LIVE and TESTNET are visually unmistakable.
- Verify server-authoritative execution readiness.
- Verify stale market feed prevents unsafe scan decisions server-side.
- Verify signal execution ticket values match server output.
- Verify entry fill, TP/SL protection state, open position and guarded close.
- Verify exchange/local reconciliation and trade-history final state.
- Verify no Binance API secret is displayed after storage.

## Security
- Never package `.env`, API credentials, signing keys or service-account secrets in the Flutter ZIP.
- Keep production API HTTPS only.
- Store authentication tokens only through secure storage.
- Revoke test sessions/devices before final production acceptance.

## V1.1 adaptive UX checks

- [ ] Verify Simple mode persists after app restart.
- [ ] Verify Pro mode persists after app restart.
- [ ] Verify switching mode changes presentation only, not permissions or backend settings.
- [ ] Verify Dashboard guided flow opens Trading Setup, Scanner and Signals correctly.
- [ ] Verify Trade Review shows Entry / TP / SL clearly in Simple mode.
- [ ] Verify Pro Trade Review shows full margin/exposure context.
- [ ] Verify LIVE status is visually prominent across Dashboard, Trade Review and Positions.
- [ ] Verify Android generated project uses minSdk 23, compileSdk 36 and NDK 27.0.12077973.
