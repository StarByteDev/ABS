ABS V14.7.1 RECOVERY PATCH V4 - COMPLETE FOLDER RESTORE

WHY THIS PATCH EXISTS
The V3 recovery ZIP contained only the individual files that needed updating. If you deleted the entire app, config, routes and resources folders before uploading V3, the application lost many required Laravel/ABS files and will return HTTP 500.

THIS V4 ZIP CONTAINS THE COMPLETE V14.7.1 VERSIONS OF THESE FOUR FOLDERS:
- app/
- config/
- routes/
- resources/

The V3 recovery fixes are already merged into them.

HOSTGATOR STEPS
1. In File Manager, go to the Laravel project root (the folder containing artisan, bootstrap, vendor, storage, .env).
2. Delete ONLY these currently broken/incomplete folders:
   app
   config
   routes
   resources
3. Upload this ZIP into the Laravel project root and Extract it there.
4. Confirm the project root now contains complete app/, config/, routes/, resources/ folders.
5. DO NOT overwrite/delete .env, storage/, vendor/, bootstrap/, database/, public/, artisan, or composer files.
6. Keep your working HostGator DB values in .env and make sure ABS_RECOVERY_KEY is set.
7. Run from HostGator Terminal in the Laravel project root:
   php artisan optimize:clear
8. Open:
   https://alphablocksolutions.com/api/recovery
9. Restore your existing ABS backup, or initialize only if you intentionally want a fresh database.

IMPORTANT
- Keep the original production APP_KEY if restoring an existing ABS backup.
- After successful recovery, remove ABS_RECOVERY_KEY from .env and run php artisan optimize:clear again.
