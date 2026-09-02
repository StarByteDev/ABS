# ABS V14.8.7 — Selected-Pair Scanner & Profile Threshold Consistency Build

**Release date:** 28 August 2026

- Normal **Run Market Scan** now evaluates the user's saved package-approved selected markets. If the plan allows 500 pairs and the user selected 77, the run evaluates those 77 markets (subject only to exchange-data availability), not the whole package catalog.
- Legacy production `.env` values such as `PULSE_MAX_PAIRS_PER_RUN=20` can no longer silently cap the modern scanner; the protected runtime ceiling is at least 1000 and also respects the package selection allowance.
- Scanner coverage/monitored metrics are now calculated against the selected market universe; package availability and plan selection allowance remain visible separately.
- Removed the **No Signal / Review Signal** action column from Market Scanner. Scanner is an evaluation surface; generated signals remain in the dedicated Signals page.
- Minimum Score now defaults to the effective user threshold. A saved Pulse Settings/Profile value wins; otherwise the package default applies; only then does the system fallback apply.
- Fixed backend signal generation previously forcing the old system threshold of 65 even when the user saved a lower value such as 10.
- Scanner Minimum Score is now a 0–100 numeric control so values such as 10 are displayed correctly instead of being limited to 70/75/80.
- Added package-level **Default minimum signal score** to Admin plan configuration and self-healing Database Fix. Existing users with saved personal thresholds are not overwritten.
- Daily scan and signal counters now share the same completed/generated timestamp rules used by scanner enforcement, preventing UI/enforcement drift. Failed scans remain excluded.
- Mobile API uses the same selected-market scan universe and effective user/package threshold rules.
- Preserves the 15-strategy catalog, package pair/strategy controls, 50-hour pair-selection lock, async scanner fragments, Binance Testnet/Live Futures controls, Mobile API parity, Database Fix, package backup/update and rollback.

**Database:** one non-destructive migration adds `pulse_plans.minimum_signal_score`. Admin Database Fix also creates it automatically if missing.
