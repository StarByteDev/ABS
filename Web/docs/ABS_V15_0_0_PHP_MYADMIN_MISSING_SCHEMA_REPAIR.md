# ABS V15.0.0 — phpMyAdmin Missing Schema Repair

Use `database/ABS_V15_0_0_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql` only as a fallback when normal Laravel migrations or the protected ABS recovery screen cannot be used.

The SQL is intentionally non-destructive: it creates missing V15 PP/gamification tables, adds only missing V15 columns, inserts absent defaults, and initializes plan PP defaults only once. Later repairs do not reset PP prices or switches already changed by Admin.

Before any production schema change, take a database backup. Import the SQL into the existing ABS database in phpMyAdmin, then sign in to Admin and verify Pulse plans, Pulse Points configuration and the purchase verification queue. Do not use an older V15 SQL copy over this release file.
