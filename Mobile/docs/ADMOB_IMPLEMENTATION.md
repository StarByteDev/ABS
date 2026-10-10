# ABS Pulse AdMob implementation audit

This mobile build is deliberately in `AdEnvironment.test`. Android and iOS
use Google's official sample application IDs and test ad-unit IDs. Unsupported
platforms and incomplete production configuration fail closed with ads off.

## Screen-by-screen placement audit

| Screen or flow | Eligible user | Banner | Interstitial boundary | Rewarded | App Open context | Decision |
|---|---|---|---|---|---|---|
| Splash, maintenance, update-required, backend-compatibility | None | No | No | No | Cold start can remain on the real splash only while a ready App Open ad is shown | Never place an ad over a blocking/system state. |
| Guest Market Pulse | Guest/free | Anchored adaptive at the shell bottom | No | No | Normal lifecycle rules | Passive public information. |
| Guest Explore Markets and public watchlist | Guest/free | Anchored adaptive at the shell bottom | No | No | Normal lifecycle rules | Passive market browsing; banner is separated from navigation. |
| Guest ABS News list/live/calendar tabs | Guest/free | One anchored adaptive banner owned by the guest shell | After every fourth completed News detail return only | No | Normal lifecycle rules | The embedded News screen does not create a duplicate banner. |
| Guest More | None | No | No | No | Normal lifecycle rules | Contains sign-in/register controls, so no nearby banner. Privacy options appear here when UMP requires them. |
| Login, register, forgot password, activation | None | No | No | No | No special placement | Credential and authentication flows stay ad-free. |
| Free Signal | Guest or non-subscribed user for Rewarded only | No | No | Explicit standard Rewarded after affirmative CTA | Suppressed after Rewarded and external share/web flows | Reward callback is the only client authorization for the server claim. Paid access bypasses the ad. |
| Dashboard | None | No | No | No | Normal lifecycle rules for free users only | Financial/member data stays visually uninterrupted. |
| Markets, market detail | None | No | No | No | Normal lifecycle rules for free users only | No ads over charts or market controls. |
| Signals, signal detail | None | No | No | No | Suppressed for the system share sheet | No interruption near signal, execution, or risk information. |
| Positions, trade detail, orders, trade history | None | No | No | No | Normal lifecycle rules for free users only | Trading and execution state is ad-free. |
| Scanner, strategies, alerts, reports, trading setup | None | No | No | No | Normal lifecycle rules for free users only | Focused analysis/configuration remains uninterrupted. |
| Standalone ABS News | Guest/free/non-subscribed | Anchored adaptive below the tab content | After every fourth completed News detail return only | No | Normal lifecycle rules | Natural end-of-reading boundary; ready ads only. |
| Standalone Economic Calendar | Guest/free/non-subscribed | Anchored adaptive below the list | No | No | Normal lifecycle rules | Passive list with clear separation from event rows. |
| Research, learning, services, Explore ABS, global search, content detail | None | No | No | No | External source links suppress immediate App Open | No extra density was justified. |
| Contact, newsletter, legal hub/documents | None | No | No | No | Normal lifecycle rules | Forms and legal/privacy content remain ad-free. |
| More, profile, account notifications/preferences, password, devices, sessions | None | No | No | No | Normal lifecycle rules | Account/security controls remain ad-free; authenticated More exposes UMP privacy choices when required. |
| Plans, quote, USDT/payment request, proof picker | None | No | No | No | Proof picker suppresses immediate App Open | Subscription and payment decisions are always ad-free. |
| Private member/statements | None | No | No | No | Normal lifecycle rules | Premium financial records remain ad-free. |

No Native Ads or Rewarded Interstitial ads are implemented.

## Central safeguards

- UMP consent information is updated at startup. Mobile Ads initializes and
  requests ads only when `canRequestAds()` permits it.
- Ad eligibility waits until the saved session and package access state are
  resolved. Active Pulse access immediately disposes preloaded normal ads.
- A single full-screen mutex prevents Rewarded, Interstitial, and App Open ads
  from overlapping.
- Interstitials require four completed News reads, 90 seconds of session age,
  three minutes between impressions, and a maximum of two per session. A late
  load is skipped rather than covering an already-interactive list.
- App Open starts on launch three, is limited to one per session and thirty
  minutes between impressions, and is ready-only at cold start or resume.
- Foreground return must follow at least one minute in the background. App Open
  is suppressed for ten minutes after Rewarded ads and external/system flows,
  including share sheets, file pickers, and external browser links.
- Banners are anchored adaptive, own/dispose their ad instance, respect safe
  insets, and collapse on load failure.
- All ad IDs live in `lib/ads/ad_config.dart`; widgets do not contain IDs.

## Monetization-state validation matrix

| State | Enforced result |
|---|---|
| Guest/free | Approved passive banners; conservative News boundary and App Open eligibility; opt-in Rewarded Free Signal. |
| Active paid member | No banner, normal interstitial, or App Open. Member Free Signal uses package access without Rewarded. |
| Reward earned | One pending claim is saved, then the backend claim runs. Returned Signal/Entry Watch and server cooldown remain authoritative. |
| Reward closed early or failed | No claim and no fabricated signal; retry UX remains available. |
| Claim network/server failure after reward | Pending reward/claim token remains encrypted locally and retries without another ad. |
| Consent blocks requests | Ads stay disabled; the ABS app and content continue normally. |
| Membership activates | `/pulse/access` refresh notifies the central service and loaded normal ads are disposed. |
| Quick resume or return from Rewarded/payment/share/picker/browser | No immediate App Open ad. |
| Rapid taps | Free Signal in-flight guard plus the full-screen mutex prevents duplicate ads/claims and overlap. |

## Production-switch checklist

Do not perform these steps until real ABS AdMob values and account approvals
exist.

- Create or verify the Android and iOS ABS apps in AdMob.
- Replace the native Android application ID with Gradle property
  `ABS_ADMOB_APP_ID` and the iOS application ID in the release xcconfig
  `ABS_ADMOB_APP_ID` value.
- Build Dart with `--dart-define=ABS_ADMOB_ENV=production` and all eight unit
  variables: `ABS_ADMOB_BANNER_ANDROID`, `ABS_ADMOB_BANNER_IOS`,
  `ABS_ADMOB_INTERSTITIAL_ANDROID`, `ABS_ADMOB_INTERSTITIAL_IOS`,
  `ABS_ADMOB_REWARDED_ANDROID`, `ABS_ADMOB_REWARDED_IOS`,
  `ABS_ADMOB_APP_OPEN_ANDROID`, and `ABS_ADMOB_APP_OPEN_IOS`.
- Configure UMP Privacy & Messaging in AdMob and approve the privacy policy and
  Play/App Store data disclosures.
- Review Google's then-current iOS SKAdNetwork list. ATT is intentionally not
  requested now because the app does not explicitly use IDFA/tracking APIs.
- Decide child-directed/under-age/content-rating settings from the actual ABS
  audience policy; the app does not invent these classifications.
- Configure and verify `app-ads.txt` where appropriate.
- Re-test paid-user suppression, consent regions, placements, and frequency.
- Use registered test devices while validating production IDs and never click
  live ads during testing.
