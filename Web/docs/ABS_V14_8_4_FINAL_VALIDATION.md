# ABS V14.8.4 — Final Validation

Release: **ABS V14.8.4 — Premium Upgrade Path, Self-Healing Database Fix & Package-Aware UX Build**  
Validation date: **27 August 2026**

## Release checks

| Validation | Result |
| --- | --- |
| PHP syntax | PASS — 165 PHP files |
| Blade/static route audit | PASS — 85 Blade views |
| Named routes discovered | 157 |
| Named route references checked | 150 |
| Mobile/OpenAPI operations matched | PASS — 107 operations |
| Approved premium visual contract | PASS — 262 checks |
| V14.8.4 release contract | PASS — 78 checks |
| JavaScript syntax | PASS — 6 files |
| Release SHA manifest | regenerated and verified before packaging |

## V14.8.4 acceptance scope

- Current active Pulse package is visibly marked as active.
- The next higher administrator-defined package is promoted in the signed-in sidebar and plan screens.
- Downgrade-only plans are hidden from the active user's upgrade path.
- Web and Mobile API plan responses expose matching current/next-tier state.
- Admin **Database Fix — Repair Everything** performs safety backup, schema reconciliation, legacy migration baselining, pending migrations, reviewed baseline synchronization, cache clearing and final required-schema verification.
- The V14.8.3 pair-selection timestamps are part of required schema and can be repaired even when a prior migration was incorrectly marked as applied.
- Installation-readiness caching is keyed by the current required schema and Recovery clears the same current key after repair.
- Future Admin package upgrades fail hard if migrations, baseline synchronization or cache clearing fail and use the pre-upgrade restore point for automatic rollback.
- Routine production repair does not silently create a default administrator, reset an existing administrator password or force-reactivate an existing administrator account.
- V14.8.3 async scanner, safe logout, usage quotas, valid Long/Short ratio handling and 50-hour pair cooldown remain preserved.
- The 15-strategy catalog, Binance USD-M Futures controls, plan market access, Mobile API and production backup/rollback baseline remain preserved.

## Deployment note

The distribution ZIP intentionally excludes `.env`, `storage`, `vendor`, `node_modules`, `.git`, runtime cache files and production credentials/state. Existing production `.env`, `APP_KEY`, database and user uploads must remain in place during upgrade.

After upload/install, use **Admin → Database Maintenance → Database Fix — Repair Everything** if the server reports schema drift. The Admin release installer also performs the same required-schema reconciliation automatically for future managed upgrades.
