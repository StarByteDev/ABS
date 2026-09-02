# ABS V14.8.4 — Upgrade Path & Database Fix

## Package-aware signed-in UX

- Current active Pulse plan is explicitly labelled `CURRENT PLAN · ACTIVE`.
- The next higher administrator-defined tier (by `sort_order`, with price fallback) is labelled `NEXT TIER · RECOMMENDED UPGRADE`.
- Downgrade-only tiers are hidden from the active user's upgrade path.
- The bottom-left Pulse sidebar plan chip promotes the next higher tier directly above the signed-in user identity. Highest-tier users see their current tier as active.
- Mobile plan/membership responses include current/next plan state so Flutter can render the same hierarchy.

## Database Fix — Repair Everything

The Admin Database Fix is the standard recovery action after any release introduces new database requirements. It:

1. Attempts a safety database backup.
2. Non-destructively creates missing ABS/Pulse tables and columns.
3. Baselines legacy migrations that are already satisfied by the live schema.
4. Runs remaining safe Laravel migrations.
5. Synchronizes missing reviewed baseline records without resetting existing administrator passwords or non-zero package prices.
6. Clears Laravel caches.
7. Runs final required-schema diagnostics and only reports success when the database is READY.

## Future Admin package upgrades

Beginning with V14.8.4, the Admin release installer automatically performs schema reconciliation before normal migrations and verifies the final required schema. If the new release cannot reach a READY schema, installation fails and the pre-upgrade restore point is used for rollback.

The installation-readiness cache is keyed by the required schema signature, so a READY result from an older build cannot hide newly required columns after an upgrade.

## Production repair safety

- Baseline synchronization does not reset an existing administrator password or force-reactivate an existing administrator account.
- A new production administrator is created only when `ABS_ADMIN_EMAIL` is explicitly configured; if that account does not exist, `ABS_ADMIN_PASSWORD` must also be configured.
- Admin package installation treats migration, baseline-seed and cache-clear command failures as upgrade failures and automatically rolls back to the pre-upgrade restore point.
- Recovery and installation readiness use the same schema-signature cache key, so successful repairs immediately invalidate the correct readiness state.
