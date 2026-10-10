# ABS V15.8.5 — Economic Calendar Actuals + Pulse FCM Push (candidate)

Base: `ABS_V15_8_4_FINAL_WORKING_BUILD.zip`. This is an additive, not-yet-deployed candidate. It does not rewrite users, investors, trades, payments, or signals. No database rows are erased by its migrations.

## What changed

- Imports all *enabled* economic-calendar data providers, not just the first one that responds successfully. Providers are visited in priority order and a secondary provider fills genuinely missing readings only. An existing forecast/actual survives provider-side nulls and an Admin-curated reading survives imports. Forecast values are never fabricated.
- Supports nullable structured `{value, unit}` fields and filters placeholder strings such as `Pending`, `TBA`, `N/A`.
- Full calendar sync remains at 15 minutes. Focused near-release sync runs every 5 minutes on the existing scheduler. Mobile/web API reads share the same `economic_events` records; the web calendar polls the API every minute while visible.
- Actuals that first appear on previously known **high/medium** events within the last 3 hours enqueue one deduplicated push alert per event. Only opted-in, active authenticated iOS/Android device tokens receive it. Interpretations compare actual against the *published* consensus when present. Later corrections update the calendar, not a repeat alert.
- Firebase HTTP v1 transport (OAuth2 service account; no legacy server key), bounded scheduler delivery, retry counts and per-device deduplication. Invalid tokens are deactivated on explicit Firebase `UNREGISTERED`/token invalid errors.
- App navigation payload: `{type:'macro_release', event_id:'...', screen:'economic_calendar', url:'...', impact:'high|medium'}`. Notification body includes the release value and Pulse crypto interpretation. Interpretation is conditional and educational, not predictive.

## Important external dependencies / limitations

1. **Forecasts may still genuinely be absent.** The selected licensed provider must publish them. Optional Trading Economics access can enrich some missing values when `TRADING_ECONOMICS_FALLBACK_ENABLED=true` with valid `TRADING_ECONOMICS_API_KEY`. Some events have no published consensus at all. Trading Economics is configured to request US, Canada, UK, Euro Area, Japan and China by default; adjust `TRADING_ECONOMICS_COUNTRIES` for your licensed coverage. Do not substitute previous readings as forecasts.
2. **FCM is not enabled until configured.** Create a service account for the **same Firebase project as the deployed Pulse app**. Place the JSON outside your website's public directory; configure `FIREBASE_SERVICE_ACCOUNT_JSON_PATH` as the absolute readable path and `FIREBASE_PROJECT_ID`. Never paste the private key into the admin panel or upload it with a public build. On iOS, set up APNs with the same Firebase project.
3. **The Flutter app must request notification permission, retrieve/refresh an FCM token and register it** at the existing authenticated `POST /api/v1/devices` endpoint (`device_uuid`, `platform`, `push_token`). The sample `docs/flutter/pulse_macro_push_bridge.dart` illustrates handling foreground notifications and navigation. It is a **reference, not an already integrated mobile release**.
4. Delivery is best-effort, not guaranteed real-time. Provider publication delay + up-to-5-minute hot sync + scheduler processing + FCM/device-network time determine receipt. Shared hosting limits per-cycle push messages. Without source API access or usable tokens, no actual/push can be promised.

## Deployment (non-destructive)

1. Back up the complete production files and database and retain V15.8.4 ZIP as immediate rollback source.
2. Use the standard non-destructive ABS deployment procedure to update code and dependencies. Keep the existing production `.env`, uploaded files, database and account history intact.
3. Apply additive migration: `php artisan migrate --force` (or your usual safe one-click migration workflow). Creates only `pulse_macro_release_alerts` and `pulse_macro_device_deliveries`. Never use `migrate:fresh`, `db:wipe`, or destructive schema actions.
4. Configure the optional FMP / Trading Economics sources if licensed and Firebase service account and project ID as above. `php artisan config:clear` or refresh cached config through the existing Admin maintenance controls.
5. Existing one-minute cPanel scheduler command remains unchanged. It invokes `abs:sync-economic-calendar-hot` every five minutes and `abs:send-macro-push` each minute. No new cPanel cron entry required.
6. Test `php artisan abs:sync-economic-calendar`, `php artisan abs:sync-economic-calendar-hot` and `php artisan abs:send-macro-push` with a registered sandbox device; observe app notifications and their associated release page. Ensure market notification preference is enabled for the user.
7. Verify actual-null > actual-set, no false second push on ordinary refresh, API/UI equality, and no notifications for stale historical imports. Test Android + iOS (including APNs) separately.

**Roll back safely:** restore V15.8.4 code without dropping the new outbox tables or reverting user data. The extra tables are unused by V15.8.4; keep them for audit. Do not claim successful live push delivery until Firebase and the mobile app are verified together.
