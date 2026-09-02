-- Alpha Block Solutions V14.0 — MySQL/MariaDB database bootstrap
--
-- Local XAMPP / VPS use:
--   mysql -u root -p < database/ABS_V14_0_CREATE_MYSQL_DATABASE.sql
--
-- Shared hosting (for example cPanel): create the database and database user in the
-- hosting control panel, grant the user ALL PRIVILEGES on that ABS database, then put
-- the host/database/user/password values in .env. Do not assume the database can be
-- named exactly `abs` on shared hosting because providers may add an account prefix.
--
-- After the database exists:
--   php artisan optimize:clear
--   php artisan abs:repair --seed
--   php artisan abs:doctor
--
-- Never use --fresh or db:wipe against a database containing real ABS data.

CREATE DATABASE IF NOT EXISTS `abs`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `abs`;
