# Pulse Mobile V1.6.4 — Calendar Data Completeness & Pulse Branding

- Reworked Economic Calendar response parsing to recursively discover provider event rows across nested historical/upcoming response wrappers.
- Duplicate event payloads are deep-merged so completed Actual/Forecast/Previous figures are not lost when the same release appears in more than one collection.
- Added broad structured aliases, nested `{value, unit}` parsing, labelled value-array parsing and safe labelled-summary fallback for Previous / Forecast / Actual.
- Removed unsafe generic `value` fallback for Actual; Pulse never invents or mislabels a provider value.
- Historical missing Actual is shown as `Not reported`; missing Previous/Forecast as `Not provided`; future Actual remains `Pending`.
- Rebranded the mobile product and launcher from `ABS Pulse` to `Pulse`, while retaining Alpha Block Solutions for parent-company/legal context and retaining the existing application ID for upgrade compatibility.
- Updated release version to V1.6.4+164; backend target remains ABS V15.7.4.

# ABS Flutter Mobile V1.6.3 — Web Payment & Find Best Signal Parity

- Pulse Membership now opens directly into a web-aligned Direct USDT transfer workflow; server quote retrieval is automatic/internal.
- Added exact amount, network, wallet copy, TXID, payment proof, notes, acknowledgement and Submit Payment for Verification.
- Added interactive Find Best Signal to the template Pulse tab with animated scanning stages and 15M + 4H server scan.
- Pulse merges persisted qualified signals from active signal and scanner-result server shapes, including singular best-signal responses.
- Pulse refreshes active signals whenever the primary Pulse tab is opened.
- Updated release version to V1.6.3+163; backend target remains ABS V15.7.4.

# ABS Flutter Mobile V1.6.2 — Calendar, Market Data, Registration & Membership Reliability

- Corrected historical Economic Calendar field mapping (`previous_value`, `forecast_value`, `actual_value`) and past-event status semantics.
- Split historical/upcoming calendar retrieval to preserve release data returned by the V15.7.4 backend.
- Expanded Market Overview response mapping and removed meaningless `— / Not supplied` cards when optional server metrics are absent.
- Added required country-code/mobile-number collection during account creation with best-effort Profile persistence for older registration contracts.
- Rebuilt Pulse Membership guest, activation-pending, expired-session and empty-data states so unauthenticated responses never render as a broken narrow card.
- Updated release version to V1.6.2+162; backend target remains ABS V15.7.4.

# ABS Flutter Mobile V1.6.1 — AppState Compile Collision Hotfix

- Fixed the Flutter compile failure where `AppState` was exported by both the ABS template-rebase state and `google_mobile_ads`.
- Narrowed the ads import in `lib/main.dart` to `show MobileAds`, so ABS `AppState` is unambiguous.
- Added a validator guard to prevent broad `google_mobile_ads` imports in files that also use ABS `AppState`.
- No UI, API, navigation, Free Signal, activation, News/Calendar, trading or Private Investor behavior changed from V1.6.0.
- Updated release version to V1.6.1+161; backend target remains ABS V15.7.4.

# ABS Flutter Mobile V1.5.1 — Flutter Compile Hotfix

- Fixed invalid `FontWeight.w650` in `lib/template_ui/common.dart`; replaced with supported `FontWeight.w700`.
- Added a validator guard so unsupported intermediate FontWeight constants fail packaging before release.
- No API, backend, navigation, trading, investor, activation, news or template behavior changed.
- Updated release version to V1.5.1+151; backend target remains ABS V15.7.4.

# ABS Flutter Mobile V1.5.0 — Attached Template UI + Production V15.7.4 Integration

- Rebuilt the app shell around the user-provided `abs_mobile.zip` UI template while retaining V1.4.1 production API/session/trading logic.
- New primary navigation: Home / Pulse / Free Signal / News / Account.
- Replaced template mock content with live ABS market, scanner, signal, intelligence, membership and account data.
- Applied real ABS Pulse branding and logo assets throughout the new template shell.
- Added live template-style Home with BTC chart, market pulse, sentiment, futures, majors, movers and headlines.
- Added live template-style Pulse screen with scanner, signals, filters, watchlist and trading shortcuts.
- Preserved rewarded Free Signal, Calendar-first Intelligence, pending-account basic access and V15.7.4 Private Investor workflows.
- Consolidated the complete feature set in the Account hub including Orders, Global Search, Newsletter, Services and all trading/account/security tools.
- Updated release version to V1.5.0+150; backend target remains ABS V15.7.4.

# ABS Pulse Mobile Changelog

## V1.4.1+141 — Intelligence, Activation & Responsive UX Patch

- ABS Intelligence now opens directly on **Calendar**.
- Calendar views align with the live web experience: **Today / Upcoming / Previous / All**, with Upcoming selected by default.
- News, live-headline and economic-calendar response parsing now accepts nested/paginated V15.7.4 payload shapes instead of assuming one `data` list.
- Registration now keeps the server-issued token and enters a **limited basic-access session immediately** while email activation is pending.
- Limited accounts retain public market pulse, markets, Free Signal, ABS Intelligence, research, learning, services and profile access across app restarts.
- Profile clearly shows **Account not activated**, with Resend Link and Check Status actions.
- Full Pulse membership/trading controls remain locked until activation.
- Login requests a limited-access token for pending accounts when the backend supports that contract and can consume such a token returned in a 403 activation payload.
- Trade Signals market-scan header was rebuilt responsively so status chips can never squeeze the title into a one-character column.
- Pulse readiness UI was condensed into a clearer next-step card with one primary action.

# ABS Flutter Mobile V1.3.2 — Rewarded Access Motion & Free Signal Recovery

- Rebuilt the Free Signal gateway into a single, simple premium rewarded-access card inspired by the approved ABS web treatment.
- Added lightweight native animation: rotating market-orbit rings, pulsing play control and moving signal bars, with no new package dependency.
- Switched the Free Signal action to the ABS gold visual treatment and kept the risk acknowledgement directly above the CTA.
- Added robust Free Signal response normalization for `signal`, `free_signal`, `setup`, nested result/payload responses and `entry_watch` fallbacks.
- Added a post-claim status recovery pass so a reveal persisted by the V15.1.6 server can still be shown if `/claim` does not return it directly.
- Prevents the mobile client from silently returning to an empty gateway after a completed reward; it now explains when the public Free Signal service returned no setup.
- Existing rewarded-ad verification, visitor identity, 30-minute server cooldown, qualified Signal vs Entry Watch disclosure, and ABS Intelligence calendar/news features remain unchanged.
- Backend compatibility remains ABS V15.1.6.

# ABS Flutter Mobile V1.3.1 — Premium Free Signal & ABS Intelligence

- Rebuilt the guest Free Signal gateway into a compact, premium institutional-style flow aligned with the live ABS web visual language.
- Replaced oversized Cost/Availability/How-it-works blocks with a concise market-intelligence hero, status strip, three-step unlock flow, consent row and premium CTA.
- Added extra embedded-page bottom clearance so Free Signal disclosure content no longer sits behind the guest navigation bar.
- Upgraded **News** into **ABS Intelligence** with three clean sections: ABS News, Live Market Wire and Economic Calendar.
- Moved the full Economic Calendar experience under the News/Intelligence area while retaining the standalone calendar entry for compatibility.
- Economic Calendar now requests **30 days of past events and 30 days ahead**, with Past / Today / Upcoming views, impact filter, currency filter, grouped dates, and clear Actual / Forecast / Previous values.
- Added today/high-impact summary metrics and a next-event preview while preserving the existing V15.1.6 `/economic-calendar` API contract.
- Refined ABS News and Live News cards for denser, cleaner mobile reading.
- Backend compatibility remains ABS V15.1.6.

# ABS Flutter Mobile V1.3.0 — V15.1.6 Complete Mobile Parity

- Added native rewarded Free Signal for guests and members with secure visitor continuity, server cooldown, qualified-signal-first behavior and clearly separated Entry Watch fallback.
- Added native share sheets for free and member signals plus server-generated share payload tracking.
- Added optional Admin-controlled AI signal explanations.
- Added detailed Signal, Strategy Profitability, Learned Reliability and research-only What-if Simulation intelligence with 7/30/90-day filters.
- Corrected direct USDT package quote, transaction-reference, proof upload and request status mapping for the live V15.1.6 API.
- Updated all runtime compatibility labels and release metadata to ABS V15.1.6 / mobile 1.3.0+130.
- Added Google Mobile Ads test configuration for safe local validation. Production AdMob IDs must be supplied before store release.

# ABS Flutter Mobile V1.2.7 — Android Resource Linking Fix

- Fixed Android AAPT resource-linking failure in both launch background XML files.
- Replaced invalid `android:drawable="#06080D"` usage with a valid rectangle shape and solid `#06080D` color.
- Retains ABS Pulse launcher naming, premium login cleanup, splash animation/sound, and all V1.2.6 features.
- Backend compatibility unchanged: ABS V14.9.2+

## V1.3.3 — Free Signal Fallback & Clean Premium UI
- Free Signal claim recovery now attempts active member-signal fallback for authenticated users with Pulse access when the public rewarded flow returns no dedicated setup.
- Free Signal claim errors now try recovery first and show a shorter user-facing message when no public setup is available.
- Free Signal gateway copy was shortened for a cleaner, more premium mobile presentation.
- Error-state action now opens in-app Trade Signals for eligible package users instead of only linking to the web Free Signal page.

## V1.3.4 — Calendar Table & Data Visibility
- Economic Calendar now uses compact table-style rows inspired by professional market calendars: Time, Currency, Impact and Event, with Actual / Forecast / Previous inline.
- Added Yesterday / Today / Tomorrow / This Week navigation and retained 30-day past / 30-day future retrieval.
- Expanded event payload compatibility for common provider field names including event_name, release_at, scheduled_for, country_code, consensus and previous aliases.
- Preserves V1.3.3 member-signal fallback when the public rewarded flow returns no setup for an authenticated Pulse package user.
- Important: guest/public Free Signal and missing macro events still depend on the ABS V15.1.6 Laravel backend returning the data; the Flutter client cannot manufacture a server-side signal or calendar event that the API does not provide.

## V1.3.5 — Clear Signal Levels & Web-style Market Context
- Fixed misleading `$0.00` rendering for tiny crypto prices in Free Signal by using adaptive market-price precision.
- Current price now falls back to the latest valid candle close when the public payload omits a separate live-price field.
- Added broader Entry / Stop Loss / Take Profit alias mapping and target normalization.
- Authenticated package fallback now hydrates the selected signal from `/pulse/signals/{id}` when possible, so the reveal can use the full signal payload instead of only the overview row.
- Entry Watch no longer presents missing trade levels as zero; it shows a clear `Watch only` action and explicitly says when trade levels have not been issued.
- Replaced the simple spark line with a web-style market-context chart: candlesticks + close-price line + Entry/SL/TP level lines when valid levels exist.
- Simplified reveal copy and converted the detail block into a clear Trade Plan / Setup Snapshot.
- Distribution ZIP is intentionally created with project files at ZIP root (no enclosing version folder).

## V1.6.0+160 — True Supplied-Template Rebase
- Rebased production runtime onto the user-supplied `abs_mobile(1).zip` UI/navigation foundation.
- Removed legacy `MainShell` and prior template adapter entry screens.
- Integrated live ABS V15.7.4 market, Pulse, Free Signal, intelligence, membership, account and Private Investor functionality into the template structure.
- Made Calendar the default ABS Intelligence tab with Upcoming selected.
- Preserved pre-activation basic-access behavior and activation controls.
- Rebuilt Free Signal in the supplied template visual language while retaining rewarded-ad/server-cooldown behavior and non-fabricated BTC 4H context fallback.
- Removed unsupported `FontWeight.w650` and replaced `Color.withValues` usage for wider Flutter 3 compatibility.
