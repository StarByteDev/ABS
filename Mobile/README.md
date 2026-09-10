# ABS Pulse — ABS Flutter Mobile V1.3.5

**Release:** ABS Flutter Mobile V1.3.5+135 — Premium Free Signal & ABS Intelligence for V15.1.6

**Backend:** ABS V15.1.6

**Platforms:** Android + iOS

**Type:** Native Flutter client — not a WebView

## Premium experience

The ABS mobile experience supports two levels of information density without changing account permissions or safety rules:

- **Simple mode** for new traders: guided next steps, plain-language explanations, focused metrics and clear actions.
- **Pro mode** for experienced traders: denser performance data, faster signal review and full professional controls.

The user can switch between Simple and Pro from the Pulse dashboard, Trading Setup and More. The preference is stored securely on the device and does **not** change server permissions, package limits or trading safety checks.

The visual system has also been rebuilt around the real ABS identity: deep premium surfaces, cyan intelligence accents, gold premium markers, restrained gradients, clearer hierarchy, consistent cards, safer LIVE/Testnet status treatment and a floating professional bottom navigation.

## Core mobile journey

```text
Create / Sign in
      ↓
Choose / confirm Pulse plan
      ↓
Trading Setup
  Binance → Risk → Markets → Execution
      ↓
Pulse Scanner
      ↓
Signals
      ↓
Trade Review
      ↓
ABS server readiness checks
      ↓
Binance execution through ABS
      ↓
Entry confirmation + TP/SL protection
      ↓
Open Positions
      ↓
Guarded close + exchange reconciliation
      ↓
Trade History / Reports / Learning
```

## Server architecture

The app does not create its own market-data or trading backend.

```text
Binance Futures
      ↓
ABS server-side market collection — target 1 minute
      ↓
ABS MySQL / Pulse engine
      ↓
/api/v1
      ↓
ABS Website + ABS Flutter Mobile
```

Market prices are read from ABS. Binance credentials are submitted to ABS over HTTPS and remain server-side. The Flutter app never calls Binance market endpoints directly.

## Included workflows

### Guest / public

- Premium ABS landing screen
- Public market overview and movers
- Market detail/chart
- Pulse package preview
- News and live news
- Research
- Learning
- Economic calendar
- ABS services
- Global search
- Contact and newsletter
- Privacy, terms, risk disclosure and market disclaimer

### Account

- Registration
- Email activation / resend
- Login
- Forgot password
- Secure Sanctum token storage
- Profile editing
- Password change
- Sessions and session revocation
- Registered devices
- Account notifications
- Notification preferences

### Pulse

- Premium Pulse dashboard
- Simple / Pro trader experience switch
- Guided new-trader setup flow
- Central ABS Futures prices
- Watchlist
- 15M / 4H scanner
- Package usage and limits
- Market-feed health
- Signals queue
- Signal evidence / validation state
- Premium Trade Review screen
- Execution ticket and readiness checks
- Binance Testnet / LIVE separation
- Binance connection management
- Risk controls
- Market/pair selection
- Execution controls
- Emergency stop
- Open positions
- Local / exchange reconciliation
- Guarded close workflow
- Orders
- Trade history and detail
- Strategies
- Reports / validation / learning
- Pulse alerts

### Private Member Portal

When permitted by the backend:

- account summary
- portfolio/contribution/P&L information
- statements
- transaction history

## Production API

Default:

```text
https://alphablocksolutions.com/api/v1
```

The root `/api/v1` page itself does not need to be a browser page. The app uses concrete endpoints such as `/api/v1/bootstrap`, `/api/v1/auth/login`, `/api/v1/pulse/dashboard`, etc.

For a local backend:

### Android emulator

```bash
flutter run --dart-define=ABS_API_BASE_URL=http://10.0.2.2:8000/api/v1
```

### iOS Simulator

```bash
flutter run --dart-define=ABS_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

## Android compatibility fixed in this build

The included native repair step permanently enforces:

```text
minSdk      = 23
compileSdk  = 36
NDK         = 27.0.12077973
applicationId = com.alphablocksolutions.abs
```

It also keeps `flutter_plugin_android_lifecycle` on the Flutter-3.24-compatible `2.0.26` line used by this source package.

This specifically addresses the previous emulator failures involving:

- compileSdk 35 vs 36
- NDK 26.x vs NDK 27
- `flutter_secure_storage` requiring minSdk 23
- lifecycle plugin versions resolving to releases that require newer Flutter
- stale Windows `android/local.properties` pointing to another Flutter installation

## Fastest Windows emulator run

Create a **new empty folder** and extract the ZIP directly into it. The ZIP is flat/root-level, so you should immediately see:

```text
pubspec.yaml
RUN_ANDROID.bat
lib\
assets\
tool\
test\
docs\
```

Start your Android emulator, then run:

```bat
RUN_ANDROID.bat
```

The runner:

1. detects the Flutter SDK currently on PATH;
2. creates the Android host if missing;
3. removes a stale `android/local.properties`;
4. applies ABS application identity;
5. forces minSdk 23 / compileSdk 36 / NDK 27;
6. clears stale Dart and Gradle state;
7. runs `flutter pub get`;
8. verifies the lifecycle compatibility pin;
9. lists connected devices;
10. runs the app.

Do **not** run:

```bat
flutter pub upgrade --major-versions
```

Use:

```bat
flutter pub get
```

## Full Windows validation / release build

First-time setup:

```bat
tool\bootstrap_windows.bat
```

Android release build:

```bat
tool\build_android_windows.bat
```

Expected outputs:

```text
build\app\outputs\flutter-apk\app-release.apk
build\app\outputs\bundle\release\app-release.aab
```

## macOS

```bash
chmod +x tool/*.sh
./tool/bootstrap_macos.sh
./tool/build_android_macos.sh
flutter build ios --release --no-codesign
```

For App Store signing, open `ios/Runner.xcworkspace` in Xcode and configure the Apple Team / signing profile.

## App identity

```text
Android applicationId: com.alphablocksolutions.abs
iOS bundle identifier target: com.alphablocksolutions.abs
Display name: ABS Pulse
```

## Security model

This client preserves the ABS server-authoritative model:

- no Binance API secret in Flutter source;
- no direct Binance market-price calls;
- package capabilities enforced server-side;
- scanner uses ABS market data;
- execution readiness calculated by ABS;
- LIVE is visually separated from Testnet;
- the client cannot bypass server validation;
- submitted order is not automatically treated as an open position;
- TP/SL protection state remains server/exchange authoritative;
- close requests wait for exchange reconciliation;
- login token is stored through `flutter_secure_storage`.

Never put `.env`, Laravel keys, database passwords, Binance secrets, signing keys or private certificates into the Flutter source.

## Testing before LIVE use

Use Binance Testnet first and verify the complete workflow:

1. register and activate account;
2. sign in;
3. request/confirm Pulse access;
4. connect Binance Testnet;
5. test and activate the connection;
6. set risk, markets and execution options;
7. verify Market Feed Healthy;
8. run scanner;
9. review signal;
10. open Trade Review;
11. confirm all readiness checks;
12. execute a Testnet trade;
13. confirm entry fill state;
14. confirm TP/SL protection state;
15. confirm the position appears correctly;
16. request close;
17. confirm exchange reconciliation;
18. confirm trade history and reporting.

Only after this sequence is verified should LIVE trading be enabled for a user.

## Validation note

This package was parsed and formatted with Dart 3.13.3 and passed its complete offline source validation. Package-network restrictions prevented `flutter pub get`, so the included validation report only claims checks that were actually completed here.

On your Flutter machine you should still run:

```bash
python tool/validate_source.py
flutter pub get
flutter analyze
flutter test
flutter build apk --debug
```

Then test the UI and full backend workflow in your emulator before producing a store release.

## Release files

- `README.md` — this guide
- `CHANGELOG.md` — release history
- `BUILD_VERSION.txt` — release identity
- `VALIDATION_REPORT.md` — performed checks and limitations
- `LOCAL_TEST_GUIDE.md` — local emulator setup and feature checklist
- `docs/ARCHITECTURE.md` — client/backend architecture
- `docs/API_COVERAGE.md` — API coverage
- `docs/STORE_RELEASE_CHECKLIST.md` — production publishing checklist
- `tool/validate_source.py` — offline source audit
- `tool/configure_native.py` — native Android/iOS repair/configuration
- `RUN_ANDROID.bat` — fastest Windows emulator launcher

---

**ABS Flutter Mobile V1.3.5 is the premium Free Signal and ABS Intelligence UX release for ABS V15.1.6.**

## V1.3.5 premium Free Signal & ABS Intelligence

This release connects to the deployed ABS V15.1.6 API and adds rewarded Free Signal, qualified-signal/Entry-Watch disclosure, native social sharing, optional AI explanations, direct USDT plan requests, detailed signal validation, strategy profitability, learned reliability and research-only what-if simulation. Google sample ad identifiers are enabled for local testing only; replace them with the ABS AdMob application and rewarded-unit IDs before publishing.


## V1.2.2 signal-discovery refinement
V1.2.2 keeps the approved Concept A design and makes signal discovery obvious: the Trade Signals page itself contains the primary **Scan Markets Now** action, market-selection readiness, central-data readiness and a clear **Scan → Review → Act** flow. Advanced users can open **Scan Options** for timeframe control and result detail.

### Before login
The app opens directly to Public Market Pulse with market cap, volume, BTC dominance, Fear & Greed, ABS Pulse score, breadth, Futures Insights, liquidations and core assets. Public tabs are Pulse, Markets, News, Watchlist and More.

### After login
The main tabs are Pulse, Markets, Signals, Portfolio and More. The Pulse dashboard continues to show market context in addition to the member's personal trading information, so users do not lose the public intelligence layer after authentication.

### Important test rule
Extract V1.3.5 to a new empty folder and run `RUN_ANDROID.bat`. Do not extract over an older ABS Mobile folder because generated Android and Gradle state can be cached.


## V1.2.2 — how users find signals
The main member flow is intentionally short:

```text
Trade Signals
   ↓
Scan Markets Now
   ↓
ABS evaluates selected, package-approved markets
   ↓
15M + 4H centralized candle data + enabled strategies + signal threshold
   ↓
Qualified setups become immutable ABS signals
   ↓
Review Signal
   ↓
Trade Review / risk / protection checks
   ↓
Execute only after user confirmation and server readiness
```

### One-tap scan
On **Trade Signals**, `Scan Markets Now` calls:

```text
POST /api/v1/pulse/scanner/run
{ "timeframe": "all" }
```

The server uses the authenticated user’s saved `selected_pairs`. The package determines which markets and strategies are allowed; the user’s signal threshold determines the minimum qualifying score. `all` means the 15M and 4H scanner horizons in one scan allowance.

Scanning does **not** place an order. It can only create qualified signal records. Execution is a later, separate server-authorized workflow.

### Advanced scan
`Scan Options` opens **Market Scan**, where the user may choose 15M, 4H, or 15M + 4H and review selected-market count, data readiness, scan allowance and results.


## V1.2.3 highlights
- App display name updated to **ABS Pulse**.
- Premium sign-in screen redesigned for a cleaner, more professional first impression.
- Added animated splash experience with startup sound hook.
- Splash uses a built-in motion animation and plays `assets/audio/abs_pulse_intro.wav` on launch.


## V1.2.4 updates
- Simplified the login page by removing the extra top information box.
- Strengthened Android launcher naming: `configure_native.py` now writes `android/app/src/main/res/values/strings.xml` with `ABS Pulse` and points the manifest label to `@string/app_name`.
- If an older launcher label still appears on the emulator, uninstall the previous app once, then run `RUN_ANDROID.bat` again.


## V1.2.5 updates
- Removed the duplicate centered logo from the login page.
- Strengthened Android launcher-name repair by patching **main/debug/profile** manifests to `@string/app_name`.
- `RUN_ANDROID.bat` now uninstalls old `com.alphablocksolutions.abs`, `com.alphablocksolutions.abs_mobile`, and `com.alphablocksolutions.absmobile` debug installs before launch so the launcher refreshes to **ABS Pulse**.


# V1.2.6 hard-fixed Android host
This package includes the Android host directly. You no longer need `flutter create` to generate the Android launcher identity.

The launcher name is fixed in:
- `android/app/src/main/AndroidManifest.xml` → `android:label="ABS Pulse"`
- `android/app/src/main/res/values/strings.xml` → `ABS Pulse`

Android application ID: `com.alphablocksolutions.abs`

Run on Windows:
```bat
RUN_ANDROID.bat
```

The runner removes older ABS package IDs from the emulator, writes your local Flutter/Android SDK paths, cleans the build, restores packages and launches the app.

### Gradle wrapper note
Binary Gradle wrapper artifacts are environment-generated. If `android\gradlew.bat` is not present, `RUN_ANDROID.bat` asks your installed Flutter SDK to complete the Android wrapper once, then immediately reapplies the hard-fixed **ABS Pulse** manifest, strings, application ID, SDK and NDK settings before building.


## V1.2.7 Android resource fix
The Android launch background resources were corrected to use a valid shape drawable. This resolves the AAPT error:

`#06080D is incompatible with attribute drawable`

The fix is already applied to both `drawable/launch_background.xml` and `drawable-v21/launch_background.xml`.

## V1.3.5 additions
- Calendar table-style UI for mobile with Yesterday / Today / Tomorrow / This Week navigation.
- Wider economic-event payload compatibility.
- Retains Free Signal member fallback from V1.3.3.
