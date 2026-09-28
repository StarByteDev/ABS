# Pulse V1.6.4+164 — priority emulator checks

1. Confirm the launcher, splash/app title and Account/About copy display **Pulse**, not `ABS Pulse`.
2. Open **News → Calendar → Previous** and compare several completed releases against the web calendar. Previous / Forecast / Actual should populate whenever those values exist in the ABS/provider payload.
3. Confirm a past release with no provider Actual says **Not reported**, not `Pending`; missing Previous/Forecast says **Not provided**.
4. Check rate decisions, inflation/employment releases and text-style forecasts such as `Held at ...` / `Economists expect ...`; both numeric and text values should render.
5. Re-test V1.6.3 Direct USDT membership and Find Best Signal flows.

# V1.6.3 priority emulator checks

1. Open **Account → Pulse Membership**, tap **Pay with USDT**, and confirm there is no Get current quote button. Payment amount/network/wallet should load automatically and TXID/proof submission should match the web flow.
2. Open **Pulse → Signals**, confirm **Find Best Signal** is visible. Tap it and verify animated Universe → 15M → 4H → Rank progress while the server scan runs.
3. Confirm a qualifying signal shows direction, score, Entry, Stop and Target through the signal card and opens Signal Detail.
4. If nothing qualifies, confirm the app states that the full scan completed with no qualified setup instead of claiming a filter mismatch.
5. Run a Best Signal scan on web, then open/re-open the mobile Pulse tab and verify the active signal list refreshes.

# ABS Pulse V1.6.2+162 — Local Test

Use a **new empty folder** for this build. Do not overwrite an older V1.5.x folder.

## Windows quick test

1. Extract the V1.6.2 ZIP into a new empty folder.
2. Open the extracted project folder in Command Prompt.
3. Start your Android emulator.
4. Run:

```bat
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run
```

You can also use:

```bat
RUN_ANDROID.bat
```

## What you should see first

The app must open with the **new supplied-template UI**, not the previous Pulse shell. Bottom navigation must be:

`Home | Pulse | Free Signal | News | Account`

## Priority checks

1. Home uses the compact template market dashboard and real ABS market data.
2. Pulse uses the template Signals / Watchlist layout.
3. Free Signal uses the gold template-style rewarded card.
4. News opens **Calendar first**, with **Upcoming** selected.
5. Account uses the template card/menu layout.
6. Create a test account: it should remain usable with basic access while activation is pending when the backend returns a limited token.
7. Account should show **Account not activated**, **Resend link**, and **Check status**.
8. After activation, verify Scanner, Signals, Positions, Membership and account tools.
9. Open Private Investor only with an authorized investor account.

If Flutter reports a compile error, send the **first red compiler error**, not only the final Gradle exit-code line.


## V1.6.2 focused checks
1. Create Account: confirm Full name, Email, country code, Mobile number and Password are visible; register with a valid international number.
2. News > Calendar > Previous: confirm Previous/Forecast/Actual use returned values and historical rows never say Pending when Actual is absent.
3. Home > Market overview: confirm only real populated ABS metrics are shown; no `Not supplied` metric cards.
4. Account > Membership as guest/unverified: confirm premium sign-in/activation states instead of the narrow Unauthenticated error card.
5. Membership as a verified user: confirm plan/access/request data loads and Refresh works.
