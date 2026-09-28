# ABS Pulse Mobile V1.6.2+162

Backend target: **ABS V15.7.4**

This release addresses the runtime issues identified from the V1.6.1 emulator screenshots while preserving the true supplied-template UI foundation.

## Economic Calendar correctness
- Historical and upcoming calendar ranges are now requested separately so the backend can return the appropriate release fields for each period.
- Calendar mapping now recognizes `previous_value`, `forecast_value`, `actual_value` and their common API aliases.
- Event date/time parsing now supports `event_at`, `scheduled_at`, `release_at`, date + time pairs and other V15 response variants.
- Past events are never labelled **Pending** merely because an Actual value is absent. Missing historical Actual values display as unavailable (`—`); only future events may display **Pending**.
- Currency/country aliases such as `US -> USD` are normalized consistently.

## Market Overview data quality
- The market-overview mapper now accepts the current and legacy V15 containers (`global`, `market`, `metrics`, root fields) and additional metric aliases.
- Fear & Greed can be read from a scalar or nested `{value/score/index}` object.
- Duplicate core assets are removed when the API provides both `core` and `prices` collections.
- The UI no longer fills the Market Overview grid with `— / Not supplied` cards. It renders only real server values and uses other real ABS metrics (Open Interest, Funding, Pulse score, sentiment) when optional macro fields are not supplied.

## Registration mobile number
- Create Account now includes **country calling code + mobile number** fields.
- Registration sends `country_code` and `phone` with `/auth/register`.
- For backend revisions where register validates only name/email/password, the app immediately performs a best-effort `/profile` update after receiving the registration token, using the existing V15 profile phone fields.
- Phone persistence never blocks successful account creation if a limited/unverified token cannot update Profile yet.

## Pulse Membership UX
- Membership no longer calls the protected membership API for guests or unverified accounts.
- Guest state shows a full premium sign-in explanation instead of a narrow `Unauthenticated` error card.
- Unverified accounts see an activation-specific screen with **Resend activation** and **Check status** actions.
- Expired/invalid authenticated sessions show a proper full-width sign-in-again state.
- Empty membership responses have a useful refresh state rather than a blank page.
- API errors render full-width and retain retry support.

## Validation
- Added mapping tests covering calendar `*_value` fields, missing Actual semantics and alternate market-overview payload containers.
- Offline source validation and archive-integrity checks are included with the release.
- Native `flutter analyze`, `flutter test` and Android/iOS compilation still need the local Flutter SDK/runtime.
