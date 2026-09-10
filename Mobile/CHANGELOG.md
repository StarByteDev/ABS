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
