# ABS V15.0.8 Validation Report

## Release scope

V15.0.8 upgrades the Admin dashboard to the confirmed ABS Pulse executive presentation, adds a working CSV report action, and implements rewarded-ad reward handling for the web member wallet plus an AdMob SSV backend contract for mobile clients.

## Protected behavior retained

- Existing Pulse signal engine and strategy calculations are not replaced.
- Existing signal validation and learned-confidence data remains the source of Admin analytics.
- USDT continues to buy Pulse Sparks only.
- Pulse packages continue to activate with Sparks.
- No daily scanner-run or signal-generation quota is introduced.
- Existing scheduler and HostGator shared-hosting workflows remain in place.

## Reward integrity controls

- Per-user daily reward cap.
- Configurable cooldown.
- Signed short-lived web/mobile reward context.
- Unique provider/reference receipt constraint.
- Idempotent Spark ledger crediting.
- Mobile AdMob SSV ECDSA verification against Google public keys.
- Transaction/user binding before mobile reward credit.
- Recent rewarded-ad receipts visible in Admin.

## Deployment

V15.0.8 requires one non-destructive schema addition for rewarded-ad receipts/settings. Use Laravel migration, protected schema repair, or the included phpMyAdmin SQL fallback.
