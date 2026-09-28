# ABS V15.6.2 — First Live Deployment

V15.6.2 introduces the new code-only rollback model. No database migration is required for this release.

## Safest first upgrade from V15.6.1

The currently deployed V15.6.1 updater predates the code-only rollback rule. For the first V15.6.2 deployment, the lowest-risk path is a normal application-file overlay on the Laravel project root while preserving server runtime state.

1. Confirm a current database backup exists for disaster recovery. Do not restore it as part of the application upgrade.
2. Upload/extract the complete V15.6.2 ZIP into the Laravel application root.
3. Preserve the production `.env`, `storage/`, `vendor/` and HostGator server configuration.
4. Run `php artisan optimize:clear` when Terminal is available.
5. Sign in to Admin -> Reporting & System -> Updates & Recovery.
6. Click **Back Up Current Build** once. From this point onward the retained rollback point is code-only and future patches use the V15.6.2 safe patch workflow.
7. Verify member login, Pulse access, market data, Best Signal, ABS News, economic calendar, Admin strategy workflow and payments.

## Existing Admin updater option

The V15.6.1 Admin updater can install this complete ZIP because V15.6.2 has no database migration or new Composer dependency. However, that older updater still creates its legacy full restore point before installation. After a successful V15.6.2 install, immediately open Updates & Recovery and click **Back Up Current Build** to replace the legacy rollback point with the new code-only checkpoint.

## Future patches after V15.6.2

Use Admin -> Updates & Recovery:

Back Up Current Build -> Upload New Patch -> Validate Patch -> Install Patch -> Restore Previous Build only if needed.

Restore Previous Build uses `ABS_RECOVERY_KEY` and leaves the live database in place.
