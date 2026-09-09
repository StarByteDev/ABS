# ABS V15.1.6 — Live Deployment Intelligence & Mobile API Final Build

This is the final Laravel website/backend release prepared for live deployment before the separate Flutter mobile-app implementation. It preserves the V15.1.5 Free Signal/ENTRY WATCH, direct-USDT commerce, ABS News, premium emails, Binance precision guards and no-Sparks architecture.

## V15.1.6 final-review additions

- Replaced the incorrect favicon with favicon/Apple-touch assets generated from the **existing production ABS cube logo**; the source production logo is unchanged.
- Added Admin **strategy profitability / robot-readiness research intelligence**: modeled trades, net R, expectancy, profit factor, unlevered model-return sum, cumulative what-if path and per-strategy profitability.
- Added a separate **actual Binance execution** view: realized P&L, fees, profitable/losing trades and conservatively identified exchange protective TP/SL exits.
- Kept the live confidence model at **75% technical score + 25% learned reliability** with Bayesian/evidence/recency protection.
- Added Admin market-data **scheduler → validation → execution health** so stale active trade reconciliation is visible.
- Added `abs:pulse-execution-check` and `abs:pulse-analytics-backfill --days=7` operational commands.
- Expanded `/api/v1` for the future mobile app with guest Free Signal status/session/claim, strategy reporting and research simulation endpoints.
- Added `docs/MOBILE_API_V15_1_6.md` and updated OpenAPI to cover all current API routes.
- Added a non-destructive migration plus phpMyAdmin SQL fallback for the new analytics columns.

### Research simulation boundary
The what-if model uses only resolved TP/SL validations after entry was observed. TP uses the frozen final target and SL is -1R. Ambiguous and unresolved results are excluded. The model does **not** assume leverage, fees, funding, slippage or compounding. This is research/diagnostic information for evaluating strategy behavior and possible future automation—not a forecast or guaranteed robot-trading result.

### Deployment boundary
Static/source/release validation cannot execute your real HostGator cron, Google rewarded inventory, Binance Testnet/Live account, SMTP, FMP or USDT environment. Run the production commands documented below using the real credentials after deployment.

## Upgrade from V15.1.5

1. Back up files and database.
2. Preserve production `.env`.
3. Replace application files.
4. Run `php artisan migrate --force` (or the included V15.1.6 phpMyAdmin SQL fallback).
5. Run `php artisan optimize:clear`.
6. Run `php artisan abs:pulse-analytics-backfill --days=7`.
7. Run `php artisan abs:production-check --email=YOUR_EMAIL`.

---

# ABS V15.1.5 — Free Signal Entry Watch Fallback Build

## What changed in V15.1.5

- Public rewarded Free Signal still prioritizes a real qualified public/system Pulse signal.
- When no setup reaches the configured Pulse qualification threshold, ABS now selects the **highest-scoring LONG/SHORT setup from the completed public market scan** and presents it as **ENTRY WATCH**.
- ENTRY WATCH is **not inserted into `pulse_signals`**, is not described as a qualified signal, and uses a distinct gold market-watch presentation and warning copy.
- The market-watch fallback keeps the pair, direction, score, entry/watch level, stop, target, current market context and recent candles so the visitor can monitor the setup after the rewarded ad.
- Social sharing clearly identifies an ENTRY WATCH as unqualified rather than promoting it as a Pulse signal.
- If the scan has neither a qualified signal nor a usable LONG/SHORT market-watch candidate, the existing premium **Pulse Market Watch** / no-opportunity state remains and no rewarded ad/cooldown is consumed.
- Existing 30-minute successful-unlock cooldown and page-session visibility behavior are unchanged.
- No database migration is required from V15.1.4.


---

# Alpha Block Solutions — ABS V15.1.4

**Free Signal Market-Watch Fallback + Premium Consent + Compact Top Layout**

ABS V15.1.4 refines the public Free Signal experience without changing the production ABS logo, global header, direct-USDT commerce, scanner engine, rewarded-ad architecture, ABS News, emails or Binance protections.

## What changed in V15.1.4

- Removed the duplicate ABS Pulse product/hero block above Free Signal; the page now begins directly with the three compact benefit cards under the existing global header, matching the approved screenshot.
- Replaced the classic risk checkbox with a premium custom acknowledgement card and aligned control.
- Rewarded access now reserves a qualified **system/public** signal before showing an ad. User-private signals are never used as public fallback content.
- If the live scan cannot evaluate central candle buffers, the technical exception is logged server-side and never shown to visitors. ABS first falls back to the latest still-active qualified public/system signal.
- If neither a fresh nor already-active qualified public signal exists, no ad is shown and no cooldown is applied. The page presents a premium **Pulse Market Watch** state explaining that no new opportunity is available right now and that ABS is monitoring for the next setup.
- When an already-active fallback signal is used, the revealed card clearly states that no new setup qualified in the latest market check and that the latest active qualified setup is being shown.
- The signal still remains visible until refresh/navigation and the 30-minute successful-unlock cooldown remains unchanged.

## Upgrade from V15.1.3

No database migration is required. Back up the site/database, preserve `.env`, replace the application files and run `php artisan optimize:clear` where available.

---

# Alpha Block Solutions — ABS V15.1.3

**Branded Free Signal Teaser + Page-Session Signal Reveal + Social Sharing**

ABS V15.1.3 was an earlier Laravel website/backend release. It keeps the V15.1.2 direct-USDT, ABS News, rewarded-ad and Binance execution architecture, and upgrades the public Free Signal experience to the approved ABS Pulse design. The production Alpha Block Solutions logo and global branding are preserved.

## What changed in V15.1.3

### Premium ABS Pulse Free Signal experience

- `/pulse/free-signal` now uses the approved dark navy, cyan and gold ABS Pulse visual language.
- The production/global ABS logo is not replaced. The page uses the existing site header and a text-led ABS Pulse product strip.
- The pre-ad signal area is a blurred teaser built from placeholders; actual signal values are not leaked in page HTML before the rewarded claim.
- The rewarded-access panel includes a lightweight animated Pulse waveform/orbit treatment implemented in CSS.

### Signal stays visible until refresh

- The old 30-second auto-hide behavior has been removed.
- After a rewarded grant, the signal remains visible in the current page until the visitor refreshes, navigates away or later unlocks another signal.
- The successful-unlock cooldown remains 30 minutes by default and is enforced server-side by the anonymous browser identity.
- Refreshing during cooldown shows the blurred teaser again plus the remaining next-free-signal timer; the prior signal is not re-exposed by the server.

### Rich real signal presentation

Where central ABS market data is available, the unlocked snapshot includes current price, 24H change/high/low/volume and recent candles. The page renders a branded market card with direction, timeframe, score, entry, up to three take-profit levels, stop loss, Pulse strategies, a market-structure chart and the live cooldown counter. Missing market fields degrade to an em dash rather than fabricated values.

### Social sharing / promotion

After unlock, the user can share the Free Signal page through X, Facebook, WhatsApp, Telegram, LinkedIn and Reddit. `More Apps` uses the browser/native Web Share capability where available, and `Copy Link` provides a universal fallback. The shared message references the unlocked market but the URL points to the public Free Signal landing page, so visitors must unlock their own signal.

### Admin control

Admin → Rewarded Signal Ads still controls enable/pause, Google test/production inventory, ad-unit configuration, cooldown and public gateway copy. The retired reveal-duration field is removed because signal visibility is now fixed to the current page session.

## Upgrade from V15.1.2

No new database migration is required for the V15.1.3 Free Signal UX change. Back up the site/database, preserve the production `.env`, replace the application files, then run `php artisan optimize:clear` where Terminal is available. Existing V15.1.0–V15.1.2 migrations/data remain valid.

## Production smoke-test boundary

Real Google rewarded-ad fill, FMP calendar responses, SMTP, MySQL/HostGator cron, Binance Testnet/Live credentials and production USDT configuration still require smoke testing in your own environment. Source/static/release validation cannot simulate those external accounts.

---

# Alpha Block Solutions — ABS V15.1.2

**Premium Admin Usability + Binance Precision Guard + ABS News Calendar History + Simplified Free Signal**

ABS V15.1.2 was an earlier Laravel website/backend release. It keeps the approved V15.1.1 architecture — direct USDT packages, anonymous rewarded Free Signal, ABS News macro intelligence, premium emails and no active Sparks/points economy — and fixes the usability/runtime issues found during local testing.

## What changed in V15.1.2

### 1) Alerts & Emails simplified

Admin → **Alerts & Emails** is rebuilt as one easy communications center:

- Plan expiry reminders with a simple enable/disable switch and clear day thresholds.
- Upcoming expiry audience counts.
- Administrator alert recipient with separate registration and package-payment alert switches.
- Customer email categories presented as clear cards instead of a dense configuration grid.
- One sticky **Save Email Settings** action.
- Simple branded test-email control.
- Delivery history kept as a separate troubleshooting/audit section.

The existing centered premium transactional email template remains unchanged.

### 2) Economic Calendar CMS ParseError fixed

The Economic Calendar CMS list template was rewritten with explicit Blade blocks instead of compressed inline directives. This removes the `unexpected token "endforelse"` error seen on `/admin/cms/content/events`.

The provider panel is also simplified to:

- connect/replace the Financial Modeling Prep API key
- enable/disable automatic synchronization
- run **Sync Calendar Now**
- preview ABS News

### 3) ABS News calendar navigation + history

The public **ABS News** page now has premium calendar navigation:

- **Today**
- **Upcoming**
- **Previous Releases** — last 30 days
- **All** — recent history plus upcoming events

Each row shows event time, impact, Previous / Forecast / Actual, an easy explanation and simplified crypto-impact context. High/medium crypto-relevant events are prioritized.

If the calendar provider is configured but the event database is empty, ABS performs a throttled bootstrap synchronization. Normal provider synchronization now requests approximately **30 days of history and 45 days ahead**, so past release history can be retained instead of only near-future events.

Actual economic-event values are never fabricated. A configured provider or Admin-maintained CMS records are required for real Previous / Forecast / Actual data.

### 4) Find Best Signal page simplified

Removed the redundant **Automatic Pulse Intelligence** information block and its four descriptive chips. The page now moves directly from the page actions/status to the useful scanner metrics and Best Signal result.

The sidebar **Commerce / Direct USDT / Activation / Admin Verified** strip was also removed. Package status remains available through the existing package/account areas without repeating commerce wording on every Pulse screen.

### 5) Binance `-1111 BAD_PRECISION` execution guard

Before risk math/order construction, Pulse now refreshes the selected pair's current Binance Futures symbol rules where possible. Before signing an order it also normalizes:

- LIMIT quantity against live `LOT_SIZE`
- MARKET quantity against live `MARKET_LOT_SIZE` when provided
- price/trigger values against live `PRICE_FILTER.tickSize`
- quantity/price decimal precision against current exchange rules

If Binance rejects the first request with a precision/filter error such as `-1111`, `-4023`, `-4014`, `-4029` or `-4030`, ABS refreshes `exchangeInfo`, re-normalizes the request and retries once. These errors represent an exchange rejection before order acceptance, so the retry path is designed not to create a duplicate accepted order.

Pair synchronization now stores `LOT_SIZE` as the normal executable quantity rule instead of incorrectly using `MARKET_LOT_SIZE` as the default for LIMIT orders.

### 6) Free Signal rewarded-ad page simplified

The anonymous `/pulse/free-signal` page is redesigned around one clear action:

**Watch Ad → Unlock Signal**

End-user technical labels such as `GOOGLE REWARDED WEB`, `OPTIONAL`, `NO ACCOUNT REQUIRED` and provider-engine implementation explanations were removed. The page now communicates only what a visitor needs to know:

- no account required
- one completed rewarded ad unlocks one qualified signal
- signal visibility: current page session until refresh/navigation (V15.1.3)
- default successful-unlock cooldown: 30 minutes
- legal/risk acknowledgement before the ad

The underlying Google rewarded grant validation, signed one-time claim, duplicate protection and cooldown logic remain in place.

## Commerce model retained

- No customer Sparks/points wallet is used in active application code/UI.
- Paid Pulse access uses **direct USDT transfer → TXID/payment proof → Admin verification → package activation**.
- Best Signal is included with an eligible active package; there is no per-signal points debit.
- USDT wallet/network/payment instructions remain Admin controlled.

## Rewarded Free Signal retained

- No registration is required for `/pulse/free-signal`.
- A qualified signal is reserved/created before an eligible rewarded ad is shown.
- The signal is revealed only after the rewarded-grant event.
- Closing/no-fill/no-grant reveals nothing and does not start the successful-unlock cooldown.
- Admin → **Rewarded Signal Ads** controls enable/pause, Google test/production inventory, ad-unit path/snippet, timings and public gateway wording.

## ABS News data source

Admin → **CMS → Economic Calendar CMS** can maintain records manually or configure an encrypted Financial Modeling Prep API key. Auto-sync uses the existing Laravel scheduler when enabled.

Without a provider key, ABS does not invent market releases or forecast/actual numbers; manual CMS remains available.

## Legal/risk coverage retained

- Global market/risk notice across public and Pulse experiences.
- Registration acknowledgement of Terms, Privacy, Risk Disclosure and Market Disclaimer.
- Direct-USDT package submission risk/legal acknowledgement.
- Free Signal legal/risk acknowledgement.
- Economic data, forecasts/revisions, rewarded advertising, third-party data, blockchain/exchange risk and no professional-adviser relationship covered by legal pages.
- Transactional emails include a concise market/risk disclaimer.

These controls improve disclosure but are not a substitute for jurisdiction-specific legal advice.

## Upgrade from V15.1.1

1. Back up the current database and application files.
2. Keep the production `.env`; do not overwrite it.
3. Upload/replace the V15.1.2 application files.
4. Preferred: run `php artisan migrate --force` and `php artisan optimize:clear`.
5. Shared hosting without Terminal: import `ABS_V15_1_2_APPLY_USABILITY_FIXES.sql` once in phpMyAdmin. It only upgrades the old stock Free Signal copy and preserves Admin-customized wording.
6. Keep the existing Laravel scheduler cron enabled.
7. In Admin → CMS → Economic Calendar CMS, enter the FMP key if not already configured and run **Sync Calendar Now** once. This is required for real economic-event data.
8. In Admin → Rewarded Signal Ads, keep Google test inventory enabled until the free-signal flow is confirmed in your target browsers.
9. Re-test Binance Testnet execution on several symbols. V15.1.2 refreshes current symbol filters immediately before execution.
10. Send a test email from Admin → Alerts & Emails.

## Production smoke-test boundary

Source/static validation is included in this release, but the following require your own external credentials/environment and must still be tested after deployment:

- real Google rewarded-ad fill and browser eligibility
- real FMP economic-calendar API responses
- SMTP delivery/rendering
- production MySQL migration/import
- HostGator cron execution
- real Binance Testnet/Live API credentials and exchange acceptance
- production USDT wallet/network/payment-verification workflow
