ABS PULSE — LIVE RECOVERY REFERENCE

PRIMARY RECOVERY CONTROLS

1. Admin -> Reporting & System -> Updates & Recovery
   - Back Up Current Build
   - Upload New Patch
   - Install Patch
   - Restore Previous Build

2. Emergency application rollback
   - /api/recovery/build
   - Uses the same ABS_RECOVERY_KEY as Database Fix.
   - Restores application files only.
   - Does NOT restore, replace, truncate or roll back the production database.

3. Database structure recovery
   - /api/recovery
   - Use Fix Missing Tables & Columns for the existing live database.
   - This creates missing required schema while preserving existing rows.

LIVE PRODUCTION RULES

- Keep the production .env and APP_KEY unchanged during normal upgrades.
- Do not delete storage/ or vendor/ during a patch deployment.
- ABS patch packages cannot overwrite .env, storage, vendor, node_modules or .git.
- Only one previous application build is retained for rollback.
- The Admin patch installer blocks destructive pending migrations.
- Patch rollback leaves all current users, memberships, payments, trades, signals, CMS content and other live database records in place.

MANUAL DATABASE BACKUPS

Admin -> Database Backups remains a separate disaster-recovery feature. A manual database restore is a different action from application rollback and should only be used when you explicitly intend to replace database state.
