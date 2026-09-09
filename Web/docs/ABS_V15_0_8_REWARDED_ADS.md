# ABS V15.0.8 Rewarded Ads

## What is included

### Website / member side

- Pulse Sparks now contains an optional **Watch & Earn** section.
- The browser requests a short-lived signed reward session from ABS.
- Google Publisher Tag creates a rewarded out-of-page slot.
- ABS displays the ad only after the member explicitly chooses to view it.
- Sparks are requested only after Google fires the rewarded-grant event.
- The backend enforces daily limits, cooldown, user binding and idempotent reward references.

### Admin side

Open **Admin → Pulse Sparks → Rewarded Ads Control Center**.

Admin can configure:

- Enable / pause rewarded ads.
- Sparks and XP per verified reward.
- Daily reward limit per user.
- Cooldown between rewards.
- Google Ad Manager web rewarded ad unit path.
- Web test mode.
- Mobile test mode.
- Android and iOS AdMob rewarded ad unit IDs.
- AdMob SSV verification.
- Maximum accepted SSV callback age.
- Recent protected reward receipts.

### Mobile backend

Authenticated mobile clients use:

`GET /api/v1/pulse/sparks/rewarded-ad/config?platform=android`

or:

`GET /api/v1/pulse/sparks/rewarded-ad/config?platform=ios`

The response provides:

- the test or production rewarded ad unit ID,
- `use_test_ads`,
- signed `ssv_custom_data`,
- `ssv_user_id`,
- reward value,
- remaining daily allowance,
- cooldown status.

The AdMob SSV callback endpoint is:

`GET /api/v1/pulse/rewarded-ad/admob/ssv`

Configure the full production URL shown in Admin in your AdMob rewarded-ad server-side verification settings.

## Mobile app integration contract

The Laravel ZIP does not contain the separate Flutter application source. The backend contract is complete and the mobile client should:

1. Request the rewarded-ad config endpoint with the current platform.
2. If `enabled=false`, do not show a rewarded-ad CTA.
3. Load `ad_unit_id` returned by ABS.
4. Set the AdMob server-side verification custom data to `ssv_custom_data` and user identifier to `ssv_user_id` before showing the ad.
5. Show the ad only after explicit user action.
6. Do **not** credit Sparks in Flutter. ABS credits Sparks only after the verified AdMob SSV callback reaches the server.
7. Refresh `/api/v1/pulse/sparks` after the reward callback has had time to arrive.

Google Android test rewarded ad unit used by V15.0.8:

`ca-app-pub-3940256099942544/5224354917`

Google iOS test rewarded ad unit used by V15.0.8:

`ca-app-pub-3940256099942544/1712485313`

## Production checklist

1. Keep Web Test Mode and Mobile Test Mode enabled while testing.
2. Enable Rewarded Ads in Admin.
3. Confirm the member Watch & Earn card can open test inventory on a supported device.
4. Confirm only completed/granted rewards increase the Spark wallet.
5. Confirm duplicate reward references do not create duplicate Sparks.
6. Create production Google Ad Manager / AdMob rewarded units.
7. Add the production web ad unit path and Android/iOS AdMob IDs in Admin.
8. Configure the Admin-displayed AdMob SSV callback URL in AdMob.
9. Confirm your consent/privacy setup covers the ad products used on your production property.
10. Turn the relevant test mode off only after production ad units are configured.

## Shared-hosting database upgrade

Preferred: run `php artisan migrate --force`.

No Terminal: use the existing protected ABS schema repair, or import `ABS_V15_0_8_APPLY_REWARDED_ADS.sql` once in phpMyAdmin.
