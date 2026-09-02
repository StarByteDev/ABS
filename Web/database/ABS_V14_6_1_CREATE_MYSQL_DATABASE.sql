-- Alpha Block Solutions V14.6.1 — MySQL/MariaDB database bootstrap
-- Recommended for Laragon local MySQL and compatible MySQL/MariaDB servers.
--
-- After the database exists, run from the project folder:
--   php cleanup-legacy-migrations.php
--   php configure-mysql.php
--   php artisan optimize:clear
--   php artisan abs:repair --seed
--   php artisan abs:doctor
--
-- Never use destructive fresh commands against a database containing data you need to keep.

CREATE DATABASE IF NOT EXISTS `abs`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `abs`;
