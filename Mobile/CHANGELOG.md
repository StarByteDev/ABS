# Pulse V1.7.5+175 — Free Signal consent and ETF data visibility

Target backend: ABS V15.9.1. Derived from preserved Pulse V1.7.4 source.

- Fixed entire rewarded-ad consent checkbox label as one accessible tap target.
- Added general API/SDK exception recovery and 25s rewarded-load timeout, without bypassing backend reward verification or cooldown.
- Improved Home daily BTC ETF status diagnostics and added a publisher-operated external source link when the numeric licensed feed is unavailable.
- No changes to actual investor balances, Android/iOS app ID, Firebase, AdMob build-time IDs, or the API authorization model.

See `docs/V175_FREE_SIGNAL_ETF_QA.md` for deployment and testing.
