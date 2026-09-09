# ABS V15.1.6 — Live Deployment Intelligence & Mobile API Final Build

- Replaced the incorrect favicon with assets generated from the existing production ABS logo without modifying the source brand logo.
- Added strategy profitability and research-only all-signals what-if analytics (R multiple, expectancy, profit factor and simple unlevered model return).
- Added actual Binance execution reconciliation metrics and conservative TP/SL protective-exit attribution.
- Added central-feed → signal-validation → trade-sync health reporting to Admin Market Feed.
- Preserved the 75% technical + 25% learned-reliability confidence engine and existing PulseScannerService byte-for-byte.
- Added mobile API routes for guest Free Signal status/session/claim and authenticated strategy/simulation reports.
- Updated mobile/OpenAPI documentation and API bootstrap capability metadata.
- Added recent analytics backfill and live execution health Artisan commands.
- Added non-destructive schema/migration/phpMyAdmin support for profitability fields.
- Preserved direct USDT package activation, no active Sparks wallet, ABS News, rewarded Free Signal/ENTRY WATCH, emails and Binance precision guards.

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

# ABS V15.1.4 — Free Signal Market-Watch Fallback + Premium Consent + Compact Top

- Removed the duplicate Free Signal product strip/hero so the page starts directly with the approved three benefit cards.
- Rebuilt the risk acknowledgement into a premium aligned custom control.
- Added protected signal reservation before rewarded-ad playback.
- Suppressed technical central-candle/scanner exceptions from the public UI and logged them server-side.
- Added latest active public/system signal fallback when no new setup can be created.
- Added a premium Market Watch state when no qualified signal exists; no ad is shown and no cooldown is applied in that state.
- Added a clear notice on fallback signal cards.
- Prevented public Free Signal fallback from ever selecting user-specific/private signals.
- Preserved page-session reveal, 30-minute cooldown, social sharing, direct USDT, ABS News, emails and Binance guards.

# ABS V15.1.3 — Branded Free Signal Teaser + Persistent Page-Session Reveal + Social Sharing

- Rebuilt `/pulse/free-signal` inside the existing Alpha Block Solutions / ABS Pulse brand system without changing the production logo or global header branding.
- Replaced the 30-second reveal model: a successfully unlocked signal now remains visible in the current browser page until the visitor refreshes, navigates away, or unlocks another signal later.
- The 30-minute successful-unlock cooldown remains server-enforced and is shown as a live next-free-signal counter above the revealed card.
- Refreshing the page intentionally hides the previously revealed signal while preserving the remaining cooldown.
- Added a blurred, non-sensitive teaser card before ad completion; real signal values are never sent to the initial page before the rewarded claim succeeds.
- Added a premium animated ABS Pulse rewarded-access panel using CSS motion/wave/orbit effects around the existing Google rewarded-ad flow.
- Expanded the rewarded signal snapshot with current market price, 24H change/high/low/volume and recent central ABS candle data where available.
- Added a branded candlestick/volume chart with real Pulse entry, TP and SL overlays after unlock.
- Added easy signal sharing to X, Facebook, WhatsApp, Telegram, LinkedIn and Reddit, plus native device sharing and Copy Link. Shared links promote the public ABS Free Signal page rather than exposing the private unlocked payload.
- Removed the Admin reveal-seconds setting; Admin now clearly shows signal visibility as “Until page refresh / navigation.”
- Preserved direct USDT package purchase → Admin verification → package activation, ABS News, Binance precision protections, legal/risk controls, premium email templates, and the no-Sparks active architecture.

# ABS V15.1.2 — Premium Usability + Binance Precision + Economic Calendar History

- Rebuilt Admin → Alerts & Emails into a simpler premium communications center with clear expiry reminders, Admin alert switches, readable customer-email categories, one save action, test email and delivery history.
- Rewrote the Economic Calendar CMS list Blade template to fix the reported `unexpected token "endforelse"` ParseError.
- Simplified the Economic Calendar provider controls and sync workflow.
- Added ABS News calendar navigation for Today, Upcoming, Previous Releases and All.
- Expanded economic-calendar synchronization window to retain approximately 30 days of historical releases and 45 days of upcoming events.
- Added a throttled public-calendar bootstrap sync when the provider is configured but no economic events are stored.
- Reworked the public economic-event rows with time, impact, Previous, Forecast, Actual, simple explanation and crypto-impact context.
- Removed the redundant **Automatic Pulse Intelligence** block and chips from Find Best Signal.
- Removed the repeated Pulse sidebar **Commerce / Direct USDT / Activation / Admin Verified** strip.
- Added live Binance symbol-rule refresh before execution and live order normalization against `PRICE_FILTER`, `LOT_SIZE` and `MARKET_LOT_SIZE`.
- Corrected pair synchronization to store `LOT_SIZE` as the default quantity step for regular/LIMIT orders.
- Added one safe exchange-rule refresh/retry for Binance precision/filter rejections including `-1111 BAD_PRECISION`.
- Applied the same live trigger-price normalization to conditional protection orders.
- Redesigned the anonymous Free Signal page with simpler premium wording and removed end-user implementation/provider labels.
- Added a non-destructive migration/phpMyAdmin fallback that upgrades only the old stock rewarded-signal copy while preserving Admin-customized wording.
- Preserved direct USDT package payment, Admin verification, no active points/Sparks economy, rewarded Free Signal security/cooldown logic, legal/risk disclosures and premium transactional emails.

# ABS V15.1.1 — Direct USDT + Rewarded Free Signal + ABS News Macro Intelligence

- Finalized the no-points/no-Sparks active commerce model.
- Preserved direct USDT package payment with TXID/proof submission, Admin verification and package activation.
- Preserved anonymous rewarded Free Signal: no registration, 30-second reveal and 30-minute successful-unlock cooldown by default.
- Added ABS News Macro Intelligence with economic-event time, previous, forecast and actual/current values plus easy explanation and simplified crypto context.
- Added encrypted FMP economic-calendar integration, Admin manual sync, optional scheduler auto-sync and manual CMS fallback.
- Added global market/risk notices and centered premium transactional email presentation.
