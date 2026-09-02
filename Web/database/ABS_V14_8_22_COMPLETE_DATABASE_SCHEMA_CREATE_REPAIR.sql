-- Alpha Block Solutions ABS V14.8.22
-- COMPLETE NON-DESTRUCTIVE MYSQL/MARIADB SCHEMA CREATE + REPAIR
-- Generated from app/Support/AbsSchemaRepair.php and PulseSchemaRepair.php
--
-- IMPORTANT:
-- 1) In phpMyAdmin SELECT the existing ABS database first, then import this file.
-- 2) This script does NOT DROP tables or delete rows.
-- 3) It creates missing tables and adds missing columns used by ABS V14.8.22.
-- 4) It does not overwrite existing column values or force-change legacy column types.
-- 5) Take a database backup before importing on a live site.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

-- No stored routines are required. The repair section uses INFORMATION_SCHEMA + PREPARE
-- so it is suitable for phpMyAdmin on normal shared-hosting database privileges.

CREATE TABLE IF NOT EXISTS `binance_connections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `environment` VARCHAR(20) NOT NULL DEFAULT 'testnet',
  `label` VARCHAR(255) NOT NULL DEFAULT 'Binance USD-M Futures',
  `api_key` TEXT NOT NULL,
  `api_secret` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `permissions` JSON NULL,
  `last_tested_at` TIMESTAMP NULL DEFAULT NULL,
  `last_error` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_binance_connections_user_id_environment` (`user_id`, `environment`),
  KEY `idx_binance_connections_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `community_post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'published',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_community_comments_community_post_id` (`community_post_id`),
  KEY `idx_community_comments_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `category` VARCHAR(255) NOT NULL DEFAULT 'general',
  `sentiment` VARCHAR(30) NOT NULL DEFAULT 'neutral',
  `status` VARCHAR(30) NOT NULL DEFAULT 'published',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_community_posts_user_id` (`user_id`),
  KEY `idx_community_posts_category` (`category`),
  KEY `idx_community_posts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(180) NOT NULL,
  `category` VARCHAR(60) NOT NULL DEFAULT 'general',
  `message` LONGTEXT NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'new',
  `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
  `admin_notes` TEXT NULL,
  `replied_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_user_id` (`user_id`),
  KEY `idx_contact_messages_category` (`category`),
  KEY `idx_contact_messages_status` (`status`),
  KEY `idx_contact_messages_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `economic_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `country` VARCHAR(80) NULL DEFAULT NULL,
  `currency` VARCHAR(10) NULL DEFAULT NULL,
  `impact` VARCHAR(30) NOT NULL DEFAULT 'medium',
  `event_at` TIMESTAMP NOT NULL,
  `previous_value` VARCHAR(255) NULL DEFAULT NULL,
  `forecast_value` VARCHAR(255) NULL DEFAULT NULL,
  `actual_value` VARCHAR(255) NULL DEFAULT NULL,
  `source` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_economic_events_currency` (`currency`),
  KEY `idx_economic_events_impact` (`impact`),
  KEY `idx_economic_events_event_at` (`event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_delivery_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `event` VARCHAR(80) NOT NULL DEFAULT 'general',
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'queued',
  `error_message` TEXT NULL,
  `metadata` JSON NULL,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_delivery_logs_user_id` (`user_id`),
  KEY `idx_email_delivery_logs_event` (`event`),
  KEY `idx_email_delivery_logs_status` (`status`),
  KEY `idx_email_delivery_logs_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_failed_jobs_uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jobs_queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `learning_articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `excerpt` TEXT NOT NULL,
  `body` LONGTEXT NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `level` VARCHAR(30) NOT NULL DEFAULT 'beginner',
  `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_learning_articles_slug` (`slug`),
  KEY `idx_learning_articles_category` (`category`),
  KEY `idx_learning_articles_status` (`status`),
  KEY `idx_learning_articles_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mobile_devices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `device_uuid` VARCHAR(190) NOT NULL,
  `platform` VARCHAR(30) NOT NULL DEFAULT 'unknown',
  `device_name` VARCHAR(255) NULL DEFAULT NULL,
  `app_version` VARCHAR(50) NULL DEFAULT NULL,
  `os_version` VARCHAR(80) NULL DEFAULT NULL,
  `push_token` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_devices_user_id_device_uuid` (`user_id`, `device_uuid`),
  KEY `idx_mobile_devices_user_id` (`user_id`),
  KEY `idx_mobile_devices_platform` (`platform`),
  KEY `idx_mobile_devices_is_active` (`is_active`),
  KEY `idx_mobile_devices_last_seen_at` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monthly_statements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_account_id` BIGINT UNSIGNED NOT NULL,
  `statement_month` DATE NOT NULL,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `contributions` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `withdrawals` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `profit_loss` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `closing_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `pdf_path` VARCHAR(255) NULL DEFAULT NULL,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monthly_statements_portfolio_account_id_statement_month` (`portfolio_account_id`, `statement_month`),
  KEY `idx_monthly_statements_portfolio_account_id` (`portfolio_account_id`),
  KEY `idx_monthly_statements_statement_month` (`statement_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news_articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `excerpt` TEXT NOT NULL,
  `body` LONGTEXT NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `image_url` VARCHAR(255) NULL DEFAULT NULL,
  `source_name` VARCHAR(255) NULL DEFAULT NULL,
  `source_url` VARCHAR(255) NULL DEFAULT NULL,
  `author_name` VARCHAR(255) NOT NULL DEFAULT 'ABS Editorial',
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_news_articles_slug` (`slug`),
  KEY `idx_news_articles_category` (`category`),
  KEY `idx_news_articles_status` (`status`),
  KEY `idx_news_articles_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `preferences` JSON NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'active',
  `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
  `unsubscribed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_subscribers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` CHAR(36) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `notifiable_type` VARCHAR(255) NOT NULL,
  `notifiable_id` BIGINT UNSIGNED NOT NULL,
  `data` TEXT NOT NULL,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_notifiable_type_notifiable_id` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personal_access_tokens_token` (`token`),
  KEY `idx_personal_access_tokens_expires_at` (`expires_at`),
  KEY `idx_personal_access_tokens_tokenable_type_tokenable_id` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `account_name` VARCHAR(255) NOT NULL DEFAULT 'Private Member Account',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `opening_value` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `current_value` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `net_contributions` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_profit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `monthly_profit` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `valuation_date` DATE NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portfolio_accounts_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_account_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `reference` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_transactions_portfolio_account_id` (`portfolio_account_id`),
  KEY `idx_portfolio_transactions_transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `tagline` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `icon` VARCHAR(20) NOT NULL DEFAULT '◈',
  `accent` VARCHAR(30) NOT NULL DEFAULT 'violet',
  `features` JSON NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'live',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category`),
  KEY `idx_products_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `severity` VARCHAR(20) NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `action_url` VARCHAR(255) NULL DEFAULT NULL,
  `data` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_alerts_user_id` (`user_id`),
  KEY `idx_pulse_alerts_type` (`type`),
  KEY `idx_pulse_alerts_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(80) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `environment` VARCHAR(20) NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `context` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_audit_logs_user_id` (`user_id`),
  KEY `idx_pulse_audit_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_automation_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'running',
  `environment` VARCHAR(20) NOT NULL DEFAULT 'testnet',
  `signals_reviewed` INT UNSIGNED NOT NULL DEFAULT 0,
  `trades_created` INT UNSIGNED NOT NULL DEFAULT 0,
  `summary` JSON NULL,
  `error_message` TEXT NULL,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_automation_runs_user_id` (`user_id`),
  KEY `idx_pulse_automation_runs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_market_candles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol` VARCHAR(30) NOT NULL,
  `timeframe` VARCHAR(8) NOT NULL,
  `open_time_ms` BIGINT UNSIGNED NOT NULL,
  `close_time_ms` BIGINT UNSIGNED NULL DEFAULT NULL,
  `open` DECIMAL(24,12) NOT NULL,
  `high` DECIMAL(24,12) NOT NULL,
  `low` DECIMAL(24,12) NOT NULL,
  `close` DECIMAL(24,12) NOT NULL,
  `volume` DECIMAL(32,8) NOT NULL DEFAULT 0,
  `is_closed` TINYINT(1) NOT NULL DEFAULT 1,
  `source` VARCHAR(30) NOT NULL DEFAULT 'binance_futures',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_candle_symbol_tf_open_unique` (`symbol`, `timeframe`, `open_time_ms`),
  KEY `idx_pulse_market_candles_symbol` (`symbol`),
  KEY `idx_pulse_market_candles_timeframe` (`timeframe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_market_data_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status` VARCHAR(20) NOT NULL DEFAULT 'running',
  `prices_updated` INT UNSIGNED NOT NULL DEFAULT 0,
  `candle_symbols_updated` INT UNSIGNED NOT NULL DEFAULT 0,
  `validation_symbols_updated` INT UNSIGNED NOT NULL DEFAULT 0,
  `summary` JSON NULL,
  `error_message` TEXT NULL,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_market_data_runs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_market_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol` VARCHAR(30) NOT NULL,
  `price` DECIMAL(24,12) NOT NULL,
  `change_percent_24h` DECIMAL(12,6) NULL DEFAULT NULL,
  `high_24h` DECIMAL(24,12) NULL DEFAULT NULL,
  `low_24h` DECIMAL(24,12) NULL DEFAULT NULL,
  `volume_24h` DECIMAL(32,8) NULL DEFAULT NULL,
  `source` VARCHAR(30) NOT NULL DEFAULT 'binance_futures',
  `observed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_market_prices_symbol` (`symbol`),
  KEY `idx_pulse_market_prices_observed_at` (`observed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_membership_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `pulse_plan_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'submitted',
  `base_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `final_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(12) NOT NULL DEFAULT 'USDT',
  `network` VARCHAR(80) NULL DEFAULT NULL,
  `wallet_address_snapshot` TEXT NULL,
  `payment_reference` VARCHAR(190) NULL DEFAULT NULL,
  `payment_proof_path` VARCHAR(255) NULL DEFAULT NULL,
  `promotion_code_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `promotion_code_snapshot` VARCHAR(80) NULL DEFAULT NULL,
  `activation_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `user_notes` TEXT NULL,
  `admin_notes` TEXT NULL,
  `reviewed_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `activated_access_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_membership_requests_payment_reference` (`payment_reference`),
  KEY `idx_pulse_membership_requests_user_id` (`user_id`),
  KEY `idx_pulse_membership_requests_pulse_plan_id` (`pulse_plan_id`),
  KEY `idx_pulse_membership_requests_status` (`status`),
  KEY `idx_pulse_membership_requests_promotion_code_id` (`promotion_code_id`),
  KEY `idx_pulse_membership_requests_reviewed_by` (`reviewed_by`),
  KEY `idx_pulse_membership_requests_activated_access_id` (`activated_access_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_pairs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbol` VARCHAR(30) NOT NULL,
  `base_asset` VARCHAR(20) NOT NULL,
  `quote_asset` VARCHAR(20) NOT NULL DEFAULT 'USDT',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `price_precision` INT UNSIGNED NOT NULL DEFAULT 2,
  `quantity_precision` INT UNSIGNED NOT NULL DEFAULT 3,
  `tick_size` DECIMAL(20,12) NULL DEFAULT NULL,
  `step_size` DECIMAL(20,12) NULL DEFAULT NULL,
  `minimum_quantity` DECIMAL(20,12) NULL DEFAULT NULL,
  `minimum_notional` DECIMAL(20,8) NULL DEFAULT NULL,
  `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_pairs_symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_plan_pairs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pulse_plan_id` BIGINT UNSIGNED NOT NULL,
  `pulse_pair_id` BIGINT UNSIGNED NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_plan_pair_unique` (`pulse_plan_id`, `pulse_pair_id`),
  KEY `idx_pulse_plan_pairs_pulse_plan_id` (`pulse_plan_id`),
  KEY `idx_pulse_plan_pairs_pulse_pair_id` (`pulse_pair_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_plan_strategies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pulse_plan_id` BIGINT UNSIGNED NOT NULL,
  `pulse_strategy_id` BIGINT UNSIGNED NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `weight_override` DECIMAL(6,2) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_plan_strategy_unique` (`pulse_plan_id`, `pulse_strategy_id`),
  KEY `idx_pulse_plan_strategies_pulse_plan_id` (`pulse_plan_id`),
  KEY `idx_pulse_plan_strategies_pulse_strategy_id` (`pulse_strategy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `monthly_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `scanner_runs_per_day` INT UNSIGNED NOT NULL DEFAULT 5,
  `signals_per_day` INT UNSIGNED NOT NULL DEFAULT 10,
  `minimum_signal_score` DECIMAL(6,2) NOT NULL DEFAULT 70,
  `auto_trades_per_day` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_open_trades` INT UNSIGNED NOT NULL DEFAULT 2,
  `max_selected_pairs` INT UNSIGNED NOT NULL DEFAULT 5,
  `allow_live_trading` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_auto_trading` TINYINT(1) NOT NULL DEFAULT 0,
  `capabilities` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `manual_trades_per_day` INT UNSIGNED NOT NULL DEFAULT 10,
  `pair_access_mode` VARCHAR(16) NOT NULL DEFAULT 'all',
  `allow_testnet_trading` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_manual_trading` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_mobile_api` TINYINT(1) NOT NULL DEFAULT 1,
  `is_trial` TINYINT(1) NOT NULL DEFAULT 0,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `request_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_payment` TINYINT(1) NOT NULL DEFAULT 1,
  `access_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `badge` VARCHAR(50) NULL DEFAULT NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_plans_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_promotion_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `label` VARCHAR(120) NULL DEFAULT NULL,
  `type` VARCHAR(30) NOT NULL DEFAULT 'coupon',
  `discount_type` VARCHAR(30) NOT NULL DEFAULT 'percent',
  `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `applicable_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `assigned_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `access_days` INT UNSIGNED NULL DEFAULT NULL,
  `max_uses` INT UNSIGNED NOT NULL DEFAULT 0,
  `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 1,
  `valid_from` TIMESTAMP NULL DEFAULT NULL,
  `valid_until` TIMESTAMP NULL DEFAULT NULL,
  `auto_activate` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_promotion_codes_code` (`code`),
  KEY `idx_pulse_promotion_codes_applicable_plan_id` (`applicable_plan_id`),
  KEY `idx_pulse_promotion_codes_assigned_user_id` (`assigned_user_id`),
  KEY `idx_pulse_promotion_codes_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_promotion_redemptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promotion_code_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `pulse_plan_id` BIGINT UNSIGNED NOT NULL,
  `membership_request_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `redeemed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_promo_request_unique` (`promotion_code_id`, `membership_request_id`),
  KEY `idx_pulse_promotion_redemptions_promotion_code_id` (`promotion_code_id`),
  KEY `idx_pulse_promotion_redemptions_user_id` (`user_id`),
  KEY `idx_pulse_promotion_redemptions_pulse_plan_id` (`pulse_plan_id`),
  KEY `idx_pulse_promotion_redemptions_membership_request_id` (`membership_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_scanner_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'running',
  `timeframe` VARCHAR(12) NOT NULL DEFAULT '15m',
  `pairs_scanned` INT UNSIGNED NOT NULL DEFAULT 0,
  `signals_created` INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `summary` JSON NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_scanner_runs_user_id` (`user_id`),
  KEY `idx_pulse_scanner_runs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_signal_daily_metrics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric_date` DATE NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `timeframe` VARCHAR(8) NOT NULL,
  `direction` VARCHAR(12) NOT NULL,
  `signals` INT UNSIGNED NOT NULL DEFAULT 0,
  `entries` INT UNSIGNED NOT NULL DEFAULT 0,
  `wins` INT UNSIGNED NOT NULL DEFAULT 0,
  `losses` INT UNSIGNED NOT NULL DEFAULT 0,
  `ambiguous` INT UNSIGNED NOT NULL DEFAULT 0,
  `expired_no_entry` INT UNSIGNED NOT NULL DEFAULT 0,
  `expired_after_entry` INT UNSIGNED NOT NULL DEFAULT 0,
  `avg_mfe_r` DECIMAL(12,6) NULL DEFAULT NULL,
  `avg_mae_r` DECIMAL(12,6) NULL DEFAULT NULL,
  `avg_duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_signal_daily_user_tf_dir_unique` (`metric_date`, `user_id`, `timeframe`, `direction`),
  KEY `idx_pulse_signal_daily_metrics_metric_date` (`metric_date`),
  KEY `idx_pulse_signal_daily_metrics_user_id` (`user_id`),
  KEY `idx_pulse_signal_daily_metrics_timeframe` (`timeframe`),
  KEY `idx_pulse_signal_daily_metrics_direction` (`direction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_signal_validations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `signal_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `signal_fingerprint` VARCHAR(64) NOT NULL,
  `symbol` VARCHAR(30) NOT NULL,
  `timeframe` VARCHAR(8) NOT NULL,
  `direction` VARCHAR(12) NOT NULL,
  `strategy_version` VARCHAR(40) NOT NULL DEFAULT '1.0',
  `strategy_snapshot` JSON NULL,
  `entry_price` DECIMAL(24,12) NOT NULL,
  `stop_loss` DECIMAL(24,12) NULL DEFAULT NULL,
  `take_profit_levels` JSON NULL,
  `technical_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `reliability_score` DECIMAL(6,2) NULL DEFAULT NULL,
  `confidence_score` DECIMAL(6,2) NULL DEFAULT NULL,
  `state` VARCHAR(30) NOT NULL DEFAULT 'waiting_entry',
  `outcome` VARCHAR(30) NULL DEFAULT NULL,
  `generated_at` TIMESTAMP NULL DEFAULT NULL,
  `entry_hit_at` TIMESTAMP NULL DEFAULT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `last_checked_at` TIMESTAMP NULL DEFAULT NULL,
  `entry_observed_price` DECIMAL(24,12) NULL DEFAULT NULL,
  `mfe_price` DECIMAL(24,12) NULL DEFAULT NULL,
  `mae_price` DECIMAL(24,12) NULL DEFAULT NULL,
  `mfe_r` DECIMAL(12,6) NULL DEFAULT NULL,
  `mae_r` DECIMAL(12,6) NULL DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `highest_tp_level_hit` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `market_regime` VARCHAR(30) NULL DEFAULT NULL,
  `context` JSON NULL,
  `meta` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_signal_validations_signal_id` (`signal_id`),
  KEY `idx_pulse_signal_validations_user_id` (`user_id`),
  KEY `idx_pulse_signal_validations_signal_fingerprint` (`signal_fingerprint`),
  KEY `idx_pulse_signal_validations_symbol` (`symbol`),
  KEY `idx_pulse_signal_validations_timeframe` (`timeframe`),
  KEY `idx_pulse_signal_validations_direction` (`direction`),
  KEY `idx_pulse_signal_validations_strategy_version` (`strategy_version`),
  KEY `idx_pulse_signal_validations_state` (`state`),
  KEY `idx_pulse_signal_validations_outcome` (`outcome`),
  KEY `idx_pulse_signal_validations_market_regime` (`market_regime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scanner_run_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `symbol` VARCHAR(30) NOT NULL,
  `timeframe` VARCHAR(12) NOT NULL DEFAULT '15m',
  `direction` VARCHAR(12) NOT NULL,
  `entry_price` DECIMAL(24,12) NOT NULL,
  `stop_loss` DECIMAL(24,12) NULL DEFAULT NULL,
  `take_profit` DECIMAL(24,12) NULL DEFAULT NULL,
  `score` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `confidence_label` VARCHAR(30) NOT NULL DEFAULT 'Review',
  `status` VARCHAR(30) NOT NULL DEFAULT 'active',
  `strategy_breakdown` JSON NULL,
  `generated_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `signal_fingerprint` VARCHAR(64) NULL DEFAULT NULL,
  `strategy_version` VARCHAR(40) NOT NULL DEFAULT '1.0',
  `strategy_snapshot` JSON NULL,
  `take_profit_levels` JSON NULL,
  `technical_score` DECIMAL(6,2) NULL DEFAULT NULL,
  `reliability_score` DECIMAL(6,2) NULL DEFAULT NULL,
  `confidence_score` DECIMAL(6,2) NULL DEFAULT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_signals_scanner_run_id` (`scanner_run_id`),
  KEY `idx_pulse_signals_symbol` (`symbol`),
  KEY `idx_pulse_signals_direction` (`direction`),
  KEY `idx_pulse_signals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_strategies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `timeframe` VARCHAR(12) NOT NULL DEFAULT '15m',
  `weight` DECIMAL(6,2) NOT NULL DEFAULT 1,
  `minimum_score` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `settings` JSON NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `version` VARCHAR(40) NOT NULL DEFAULT '1.0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_strategies_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_strategy_daily_metrics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric_date` DATE NOT NULL,
  `strategy_slug` VARCHAR(100) NOT NULL,
  `strategy_version` VARCHAR(40) NOT NULL DEFAULT '1.0',
  `timeframe` VARCHAR(8) NOT NULL,
  `direction` VARCHAR(12) NOT NULL,
  `market_regime` VARCHAR(30) NOT NULL DEFAULT 'ALL',
  `sample_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `entries` INT UNSIGNED NOT NULL DEFAULT 0,
  `wins` INT UNSIGNED NOT NULL DEFAULT 0,
  `losses` INT UNSIGNED NOT NULL DEFAULT 0,
  `ambiguous` INT UNSIGNED NOT NULL DEFAULT 0,
  `expired_no_entry` INT UNSIGNED NOT NULL DEFAULT 0,
  `avg_mfe_r` DECIMAL(12,6) NULL DEFAULT NULL,
  `avg_mae_r` DECIMAL(12,6) NULL DEFAULT NULL,
  `avg_duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_strategy_daily_metric_unique` (`metric_date`, `strategy_slug`, `strategy_version`, `timeframe`, `direction`, `market_regime`),
  KEY `idx_pulse_strategy_daily_metrics_metric_date` (`metric_date`),
  KEY `idx_pulse_strategy_daily_metrics_strategy_slug` (`strategy_slug`),
  KEY `idx_pulse_strategy_daily_metrics_strategy_version` (`strategy_version`),
  KEY `idx_pulse_strategy_daily_metrics_timeframe` (`timeframe`),
  KEY `idx_pulse_strategy_daily_metrics_direction` (`direction`),
  KEY `idx_pulse_strategy_daily_metrics_market_regime` (`market_regime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_strategy_learning_states` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `strategy_slug` VARCHAR(100) NOT NULL,
  `strategy_version` VARCHAR(40) NOT NULL DEFAULT '1.0',
  `timeframe` VARCHAR(8) NOT NULL,
  `direction` VARCHAR(12) NOT NULL,
  `market_regime` VARCHAR(30) NOT NULL DEFAULT 'ALL',
  `sample_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `win_rate` DECIMAL(8,4) NULL DEFAULT NULL,
  `ambiguous_rate` DECIMAL(8,4) NULL DEFAULT NULL,
  `reliability_score` DECIMAL(6,2) NOT NULL DEFAULT 50,
  `recency_weighted_score` DECIMAL(6,2) NOT NULL DEFAULT 50,
  `evidence_level` VARCHAR(20) NOT NULL DEFAULT 'insufficient',
  `meta` JSON NULL,
  `calculated_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_strategy_learning_unique` (`strategy_slug`, `strategy_version`, `timeframe`, `direction`, `market_regime`),
  KEY `idx_pulse_strategy_learning_states_strategy_slug` (`strategy_slug`),
  KEY `idx_pulse_strategy_learning_states_strategy_version` (`strategy_version`),
  KEY `idx_pulse_strategy_learning_states_timeframe` (`timeframe`),
  KEY `idx_pulse_strategy_learning_states_direction` (`direction`),
  KEY `idx_pulse_strategy_learning_states_market_regime` (`market_regime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NULL,
  `type` VARCHAR(20) NOT NULL DEFAULT 'string',
  `group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `description` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_system_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_trades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `signal_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `symbol` VARCHAR(30) NOT NULL,
  `side` VARCHAR(12) NOT NULL,
  `environment` VARCHAR(20) NOT NULL DEFAULT 'testnet',
  `order_type` VARCHAR(20) NOT NULL DEFAULT 'MARKET',
  `leverage` INT UNSIGNED NOT NULL DEFAULT 1,
  `quantity` DECIMAL(24,12) NOT NULL,
  `entry_price` DECIMAL(24,12) NULL DEFAULT NULL,
  `current_price` DECIMAL(24,12) NULL DEFAULT NULL,
  `stop_loss` DECIMAL(24,12) NULL DEFAULT NULL,
  `take_profit` DECIMAL(24,12) NULL DEFAULT NULL,
  `exchange_order_id` VARCHAR(100) NULL DEFAULT NULL,
  `exchange_tp_order_id` VARCHAR(100) NULL DEFAULT NULL,
  `exchange_sl_order_id` VARCHAR(100) NULL DEFAULT NULL,
  `exchange_position_side` VARCHAR(20) NOT NULL DEFAULT 'BOTH',
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `realized_pnl` DECIMAL(20,8) NOT NULL DEFAULT 0,
  `fees` DECIMAL(20,8) NOT NULL DEFAULT 0,
  `opened_at` TIMESTAMP NULL DEFAULT NULL,
  `closed_at` TIMESTAMP NULL DEFAULT NULL,
  `close_reason` VARCHAR(255) NULL DEFAULT NULL,
  `meta` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `exchange_close_order_id` VARCHAR(100) NULL DEFAULT NULL,
  `protection_status` VARCHAR(30) NOT NULL DEFAULT 'not_required',
  `unrealized_pnl` DECIMAL(20,8) NOT NULL DEFAULT 0,
  `commission_asset` VARCHAR(20) NULL DEFAULT NULL,
  `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pulse_trades_user_id` (`user_id`),
  KEY `idx_pulse_trades_signal_id` (`signal_id`),
  KEY `idx_pulse_trades_symbol` (`symbol`),
  KEY `idx_pulse_trades_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_user_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `environment` VARCHAR(20) NOT NULL DEFAULT 'testnet',
  `auto_trade_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `default_leverage` INT UNSIGNED NOT NULL DEFAULT 3,
  `risk_per_trade_percent` DECIMAL(6,2) NOT NULL DEFAULT 1,
  `default_order_type` VARCHAR(20) NOT NULL DEFAULT 'MARKET',
  `take_profit_percent` DECIMAL(6,2) NOT NULL DEFAULT 2,
  `stop_loss_percent` DECIMAL(6,2) NOT NULL DEFAULT 1,
  `daily_loss_limit` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `max_open_positions` INT UNSIGNED NOT NULL DEFAULT 2,
  `selected_pairs` JSON NULL,
  `notification_preferences` JSON NULL,
  `pair_selection_saved_at` TIMESTAMP NULL DEFAULT NULL,
  `pair_selection_locked_until` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `execution_mode` VARCHAR(30) NOT NULL DEFAULT 'signal_only',
  `emergency_stop` TINYINT(1) NOT NULL DEFAULT 0,
  `margin_type` VARCHAR(20) NOT NULL DEFAULT 'ISOLATED',
  `position_mode` VARCHAR(20) NOT NULL DEFAULT 'BOTH',
  `sizing_mode` VARCHAR(30) NOT NULL DEFAULT 'fixed_notional',
  `fixed_notional` DECIMAL(20,8) NOT NULL DEFAULT 25,
  `fixed_quantity` DECIMAL(24,12) NULL DEFAULT NULL,
  `minimum_signal_score` DECIMAL(6,2) NOT NULL DEFAULT 70,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pulse_user_settings_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `summary` TEXT NOT NULL,
  `body` LONGTEXT NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `asset_symbol` VARCHAR(20) NULL DEFAULT NULL,
  `risk_level` VARCHAR(30) NOT NULL DEFAULT 'not_rated',
  `image_url` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_research_reports_slug` (`slug`),
  KEY `idx_research_reports_category` (`category`),
  KEY `idx_research_reports_asset_symbol` (`asset_symbol`),
  KEY `idx_research_reports_status` (`status`),
  KEY `idx_research_reports_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user_id` (`user_id`),
  KEY `idx_sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` TEXT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT 'string',
  `group` VARCHAR(255) NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_settings_key` (`key`),
  KEY `idx_site_settings_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_service_access` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `service` VARCHAR(50) NOT NULL DEFAULT 'pulse',
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `pulse_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `starts_at` TIMESTAMP NULL DEFAULT NULL,
  `ends_at` TIMESTAMP NULL DEFAULT NULL,
  `permissions` JSON NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `trial_used_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_service_access_user_id_service` (`user_id`, `service`),
  KEY `idx_user_service_access_user_id` (`user_id`),
  KEY `idx_user_service_access_service` (`service`),
  KEY `idx_user_service_access_status` (`status`),
  KEY `idx_user_service_access_pulse_plan_id` (`pulse_plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `country_code` VARCHAR(8) NULL DEFAULT NULL,
  `phone` VARCHAR(32) NULL DEFAULT NULL,
  `country` VARCHAR(80) NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(40) NOT NULL DEFAULT 'user',
  `status` VARCHAR(40) NOT NULL DEFAULT 'active',
  `private_member_approved_at` TIMESTAMP NULL DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `watchlists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `symbol` VARCHAR(30) NOT NULL,
  `display_name` VARCHAR(255) NULL DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watchlists_user_id_symbol` (`user_id`, `symbol`),
  KEY `idx_watchlists_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repair existing tables: add every ABS-required column that is missing.
-- Existing columns are preserved as-is.

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='environment'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `environment` VARCHAR(20) NOT NULL DEFAULT ''testnet'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='label'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `label` VARCHAR(255) NOT NULL DEFAULT ''Binance USD-M Futures'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='api_key'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `api_key` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='api_secret'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `api_secret` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='is_active'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='permissions'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `permissions` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='last_tested_at'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `last_tested_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='last_error'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `last_error` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `binance_connections` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache' AND COLUMN_NAME='key'),
  'SELECT 1',
  'ALTER TABLE `cache` ADD COLUMN `key` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache' AND COLUMN_NAME='value'),
  'SELECT 1',
  'ALTER TABLE `cache` ADD COLUMN `value` MEDIUMTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache' AND COLUMN_NAME='expiration'),
  'SELECT 1',
  'ALTER TABLE `cache` ADD COLUMN `expiration` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache_locks' AND COLUMN_NAME='key'),
  'SELECT 1',
  'ALTER TABLE `cache_locks` ADD COLUMN `key` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache_locks' AND COLUMN_NAME='owner'),
  'SELECT 1',
  'ALTER TABLE `cache_locks` ADD COLUMN `owner` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache_locks' AND COLUMN_NAME='expiration'),
  'SELECT 1',
  'ALTER TABLE `cache_locks` ADD COLUMN `expiration` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='community_post_id'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `community_post_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='body'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `body` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''published'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `community_comments` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='title'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='body'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `body` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='category'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `category` VARCHAR(255) NOT NULL DEFAULT ''general'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='sentiment'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `sentiment` VARCHAR(30) NOT NULL DEFAULT ''neutral'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''published'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='is_pinned'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='deleted_at'),
  'SELECT 1',
  'ALTER TABLE `community_posts` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `name` VARCHAR(120) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='email'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='subject'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `subject` VARCHAR(180) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='category'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `category` VARCHAR(60) NOT NULL DEFAULT ''general'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='message'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `message` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''new'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='priority'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `priority` VARCHAR(20) NOT NULL DEFAULT ''normal'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='admin_notes'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `admin_notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='replied_at'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `replied_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `contact_messages` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='title'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='country'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `country` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='currency'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `currency` VARCHAR(10) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='impact'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `impact` VARCHAR(30) NOT NULL DEFAULT ''medium'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='event_at'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `event_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='previous_value'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `previous_value` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='forecast_value'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `forecast_value` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='actual_value'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `actual_value` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='source'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `source` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `economic_events` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='event'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `event` VARCHAR(80) NOT NULL DEFAULT ''general'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='recipient_email'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `recipient_email` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='subject'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `subject` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''queued'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='error_message'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `error_message` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='metadata'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `metadata` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='sent_at'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `sent_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `email_delivery_logs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='uuid'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `uuid` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='connection'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `connection` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='queue'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `queue` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='payload'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `payload` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='exception'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `exception` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='failed_at'),
  'SELECT 1',
  'ALTER TABLE `failed_jobs` ADD COLUMN `failed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `id` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='total_jobs'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `total_jobs` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='pending_jobs'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `pending_jobs` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='failed_jobs'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `failed_jobs` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='failed_job_ids'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `failed_job_ids` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='options'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `options` MEDIUMTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='cancelled_at'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `cancelled_at` INT NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `created_at` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='finished_at'),
  'SELECT 1',
  'ALTER TABLE `job_batches` ADD COLUMN `finished_at` INT NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='queue'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `queue` VARCHAR(255) NOT NULL DEFAULT ''default'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='payload'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `payload` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='attempts'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='reserved_at'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `reserved_at` INT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='available_at'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `available_at` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `jobs` ADD COLUMN `created_at` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='title'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='slug'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='excerpt'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `excerpt` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='body'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `body` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='category'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `category` VARCHAR(255) NOT NULL DEFAULT ''Education'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='level'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `level` VARCHAR(30) NOT NULL DEFAULT ''beginner'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='duration_minutes'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 5'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''draft'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='is_featured'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='published_at'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `published_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='deleted_at'),
  'SELECT 1',
  'ALTER TABLE `learning_articles` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='migrations' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `migrations` ADD COLUMN `id` INT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='migrations' AND COLUMN_NAME='migration'),
  'SELECT 1',
  'ALTER TABLE `migrations` ADD COLUMN `migration` VARCHAR(255) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='migrations' AND COLUMN_NAME='batch'),
  'SELECT 1',
  'ALTER TABLE `migrations` ADD COLUMN `batch` INT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='device_uuid'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `device_uuid` VARCHAR(190) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='platform'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `platform` VARCHAR(30) NOT NULL DEFAULT ''unknown'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='device_name'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `device_name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='app_version'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `app_version` VARCHAR(50) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='os_version'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `os_version` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='push_token'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `push_token` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='is_active'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='last_seen_at'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `last_seen_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `mobile_devices` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='portfolio_account_id'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `portfolio_account_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='statement_month'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `statement_month` DATE NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='opening_balance'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='contributions'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `contributions` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='withdrawals'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `withdrawals` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='profit_loss'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `profit_loss` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='closing_balance'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `closing_balance` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='notes'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='pdf_path'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `pdf_path` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='published_at'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `published_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `monthly_statements` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='title'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='slug'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='excerpt'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `excerpt` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='body'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `body` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='category'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `category` VARCHAR(255) NOT NULL DEFAULT ''Market News'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='image_url'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `image_url` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='source_name'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `source_name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='source_url'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `source_url` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='author_name'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `author_name` VARCHAR(255) NOT NULL DEFAULT ''ABS Editorial'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''draft'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='is_featured'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='published_at'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `published_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='deleted_at'),
  'SELECT 1',
  'ALTER TABLE `news_articles` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='email'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='preferences'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `preferences` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''active'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='confirmed_at'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `confirmed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='unsubscribed_at'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `unsubscribed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `newsletter_subscribers` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `id` CHAR(36) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='type'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `type` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='notifiable_type'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `notifiable_type` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='notifiable_id'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `notifiable_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='data'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `data` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='read_at'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `read_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `notifications` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='email'),
  'SELECT 1',
  'ALTER TABLE `password_reset_tokens` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='token'),
  'SELECT 1',
  'ALTER TABLE `password_reset_tokens` ADD COLUMN `token` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `password_reset_tokens` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='tokenable_type'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `tokenable_type` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='tokenable_id'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `tokenable_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='token'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `token` VARCHAR(64) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='abilities'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `abilities` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='last_used_at'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `last_used_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='expires_at'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `expires_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `personal_access_tokens` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='account_name'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `account_name` VARCHAR(255) NOT NULL DEFAULT ''Private Member Account'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='currency'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `currency` VARCHAR(10) NOT NULL DEFAULT ''USD'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='opening_value'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `opening_value` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='current_value'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `current_value` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='net_contributions'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `net_contributions` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='total_profit'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `total_profit` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='monthly_profit'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `monthly_profit` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='valuation_date'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `valuation_date` DATE NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='is_active'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='notes'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `portfolio_accounts` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='portfolio_account_id'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `portfolio_account_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='type'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `type` VARCHAR(30) NOT NULL DEFAULT ''adjustment'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='amount'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `amount` DECIMAL(18,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='transaction_date'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `transaction_date` DATE NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='reference'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `reference` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='description'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `description` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `portfolio_transactions` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='slug'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='category'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `category` VARCHAR(255) NOT NULL DEFAULT ''General'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='tagline'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `tagline` VARCHAR(255) NOT NULL DEFAULT ''ABS product'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='description'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `description` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='icon'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `icon` VARCHAR(20) NOT NULL DEFAULT ''◈'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='accent'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `accent` VARCHAR(30) NOT NULL DEFAULT ''violet'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='features'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `features` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''live'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='sort_order'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='is_featured'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='deleted_at'),
  'SELECT 1',
  'ALTER TABLE `products` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='type'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `type` VARCHAR(40) NOT NULL DEFAULT ''system'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='title'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='message'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `message` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='severity'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `severity` VARCHAR(20) NOT NULL DEFAULT ''info'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='is_read'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='action_url'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `action_url` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='data'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `data` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_alerts` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='action'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `action` VARCHAR(100) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='entity_type'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `entity_type` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='entity_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='environment'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `environment` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='ip_address'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='context'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `context` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_audit_logs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''running'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='environment'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `environment` VARCHAR(20) NOT NULL DEFAULT ''testnet'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='signals_reviewed'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `signals_reviewed` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='trades_created'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `trades_created` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='summary'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `summary` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='error_message'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `error_message` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='started_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `started_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='completed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_automation_runs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `symbol` VARCHAR(30) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `timeframe` VARCHAR(8) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='open_time_ms'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `open_time_ms` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='close_time_ms'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `close_time_ms` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='open'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `open` DECIMAL(24,12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='high'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `high` DECIMAL(24,12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='low'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `low` DECIMAL(24,12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='close'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `close` DECIMAL(24,12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='volume'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `volume` DECIMAL(32,8) NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='is_closed'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `is_closed` TINYINT(1) NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='source'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `source` VARCHAR(30) NULL DEFAULT ''binance_futures'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_candles` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `status` VARCHAR(20) NULL DEFAULT ''running'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='prices_updated'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `prices_updated` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='candle_symbols_updated'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `candle_symbols_updated` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='validation_symbols_updated'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `validation_symbols_updated` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='summary'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `summary` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='error_message'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `error_message` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='started_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `started_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='completed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_data_runs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `symbol` VARCHAR(30) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='price'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `price` DECIMAL(24,12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='change_percent_24h'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `change_percent_24h` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='high_24h'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `high_24h` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='low_24h'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `low_24h` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='volume_24h'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `volume_24h` DECIMAL(32,8) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='source'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `source` VARCHAR(30) NULL DEFAULT ''binance_futures'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='observed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `observed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_market_prices` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='pulse_plan_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `pulse_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''submitted'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='base_amount'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `base_amount` DECIMAL(12,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='discount_amount'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='final_amount'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `final_amount` DECIMAL(12,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='currency'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `currency` VARCHAR(12) NOT NULL DEFAULT ''USDT'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='network'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `network` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='wallet_address_snapshot'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `wallet_address_snapshot` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='payment_reference'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `payment_reference` VARCHAR(190) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='payment_proof_path'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `payment_proof_path` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='promotion_code_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `promotion_code_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='promotion_code_snapshot'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `promotion_code_snapshot` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='activation_days'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `activation_days` INT UNSIGNED NOT NULL DEFAULT 30'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='user_notes'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `user_notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='admin_notes'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `admin_notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='reviewed_by'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `reviewed_by` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='reviewed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='activated_access_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `activated_access_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_membership_requests` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `symbol` VARCHAR(30) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='base_asset'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `base_asset` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='quote_asset'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `quote_asset` VARCHAR(20) NOT NULL DEFAULT ''USDT'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='is_enabled'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `is_enabled` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='sort_order'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='price_precision'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `price_precision` INT UNSIGNED NOT NULL DEFAULT 2'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='quantity_precision'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `quantity_precision` INT UNSIGNED NOT NULL DEFAULT 3'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='tick_size'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `tick_size` DECIMAL(20,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='step_size'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `step_size` DECIMAL(20,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='minimum_quantity'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `minimum_quantity` DECIMAL(20,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='minimum_notional'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `minimum_notional` DECIMAL(20,8) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='last_synced_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_pairs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_pairs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='pulse_plan_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_pairs` ADD COLUMN `pulse_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='pulse_pair_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_pairs` ADD COLUMN `pulse_pair_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='is_enabled'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_pairs` ADD COLUMN `is_enabled` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_pairs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_pairs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='pulse_plan_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `pulse_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='pulse_strategy_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `pulse_strategy_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='is_enabled'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `is_enabled` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='weight_override'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `weight_override` DECIMAL(6,2) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_plan_strategies` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='slug'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='description'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `description` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='monthly_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `monthly_price` DECIMAL(12,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='currency'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `currency` VARCHAR(8) NOT NULL DEFAULT ''USD'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='scanner_runs_per_day'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `scanner_runs_per_day` INT UNSIGNED NOT NULL DEFAULT 5'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='signals_per_day'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `signals_per_day` INT UNSIGNED NOT NULL DEFAULT 10'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='minimum_signal_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `minimum_signal_score` DECIMAL(6,2) NOT NULL DEFAULT 70'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='auto_trades_per_day'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `auto_trades_per_day` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='max_open_trades'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `max_open_trades` INT UNSIGNED NOT NULL DEFAULT 2'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='max_selected_pairs'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `max_selected_pairs` INT UNSIGNED NOT NULL DEFAULT 5'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_live_trading'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `allow_live_trading` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_auto_trading'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `allow_auto_trading` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='capabilities'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `capabilities` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_active'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='sort_order'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='manual_trades_per_day'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `manual_trades_per_day` INT UNSIGNED NOT NULL DEFAULT 10'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='pair_access_mode'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `pair_access_mode` VARCHAR(16) NOT NULL DEFAULT ''all'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_testnet_trading'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `allow_testnet_trading` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_manual_trading'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `allow_manual_trading` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_mobile_api'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `allow_mobile_api` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_trial'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `is_trial` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_public'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `is_public` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='request_enabled'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `request_enabled` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='requires_payment'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `requires_payment` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='access_days'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `access_days` INT UNSIGNED NOT NULL DEFAULT 30'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='badge'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `badge` VARCHAR(50) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_featured'),
  'SELECT 1',
  'ALTER TABLE `pulse_plans` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='code'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `code` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='label'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `label` VARCHAR(120) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='type'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `type` VARCHAR(30) NOT NULL DEFAULT ''coupon'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='discount_type'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `discount_type` VARCHAR(30) NOT NULL DEFAULT ''percent'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='discount_value'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='applicable_plan_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `applicable_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='assigned_user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `assigned_user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='access_days'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `access_days` INT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='max_uses'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `max_uses` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='per_user_limit'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='valid_from'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `valid_from` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='valid_until'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `valid_until` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='auto_activate'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `auto_activate` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='is_active'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='notes'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='created_by'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `created_by` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_codes` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='promotion_code_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `promotion_code_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='pulse_plan_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `pulse_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='membership_request_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `membership_request_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='discount_amount'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='redeemed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `redeemed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_promotion_redemptions` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''running'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `timeframe` VARCHAR(12) NOT NULL DEFAULT ''15m'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='pairs_scanned'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `pairs_scanned` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='signals_created'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `signals_created` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='started_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `started_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='completed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='summary'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `summary` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='error_message'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `error_message` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='metric_date'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `metric_date` DATE NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `timeframe` VARCHAR(8) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='direction'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `direction` VARCHAR(12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='signals'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `signals` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='entries'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `entries` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='wins'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `wins` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='losses'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `losses` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='ambiguous'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `ambiguous` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='expired_no_entry'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `expired_no_entry` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='expired_after_entry'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `expired_after_entry` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='avg_mfe_r'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `avg_mfe_r` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='avg_mae_r'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `avg_mae_r` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='avg_duration_seconds'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `avg_duration_seconds` INT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='signal_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `signal_id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='signal_fingerprint'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `signal_fingerprint` VARCHAR(64) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `symbol` VARCHAR(30) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `timeframe` VARCHAR(8) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='direction'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `direction` VARCHAR(12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='strategy_version'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `strategy_version` VARCHAR(40) NULL DEFAULT ''1.0'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='strategy_snapshot'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `strategy_snapshot` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='entry_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `entry_price` DECIMAL(24,12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='stop_loss'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `stop_loss` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='take_profit_levels'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `take_profit_levels` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='technical_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `technical_score` DECIMAL(6,2) NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='reliability_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `reliability_score` DECIMAL(6,2) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='confidence_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `confidence_score` DECIMAL(6,2) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='state'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `state` VARCHAR(30) NULL DEFAULT ''waiting_entry'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='outcome'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `outcome` VARCHAR(30) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='generated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `generated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='entry_hit_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `entry_hit_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='resolved_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `resolved_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='last_checked_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `last_checked_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='entry_observed_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `entry_observed_price` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mfe_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `mfe_price` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mae_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `mae_price` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mfe_r'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `mfe_r` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mae_r'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `mae_r` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='duration_seconds'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `duration_seconds` INT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='highest_tp_level_hit'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `highest_tp_level_hit` TINYINT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='market_regime'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `market_regime` VARCHAR(30) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='context'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `context` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='meta'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `meta` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signal_validations` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='scanner_run_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `scanner_run_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `symbol` VARCHAR(30) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `timeframe` VARCHAR(12) NOT NULL DEFAULT ''15m'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='direction'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `direction` VARCHAR(12) NOT NULL DEFAULT ''NEUTRAL'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='entry_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `entry_price` DECIMAL(24,12) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='stop_loss'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `stop_loss` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='take_profit'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `take_profit` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `score` DECIMAL(6,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='confidence_label'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `confidence_label` VARCHAR(30) NOT NULL DEFAULT ''Review'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''active'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='strategy_breakdown'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `strategy_breakdown` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='generated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `generated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='expires_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `expires_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='signal_fingerprint'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `signal_fingerprint` VARCHAR(64) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='strategy_version'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `strategy_version` VARCHAR(40) NOT NULL DEFAULT ''1.0'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='strategy_snapshot'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `strategy_snapshot` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='take_profit_levels'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `take_profit_levels` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='technical_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `technical_score` DECIMAL(6,2) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='reliability_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `reliability_score` DECIMAL(6,2) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='confidence_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `confidence_score` DECIMAL(6,2) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_signals` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='slug'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='description'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `description` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `timeframe` VARCHAR(12) NOT NULL DEFAULT ''15m'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='weight'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `weight` DECIMAL(6,2) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='minimum_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `minimum_score` DECIMAL(6,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='settings'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `settings` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='is_enabled'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `is_enabled` TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='sort_order'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='version'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategies` ADD COLUMN `version` VARCHAR(40) NOT NULL DEFAULT ''1.0'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='metric_date'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `metric_date` DATE NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='strategy_slug'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `strategy_slug` VARCHAR(100) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='strategy_version'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `strategy_version` VARCHAR(40) NULL DEFAULT ''1.0'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `timeframe` VARCHAR(8) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='direction'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `direction` VARCHAR(12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='market_regime'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `market_regime` VARCHAR(30) NULL DEFAULT ''ALL'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='sample_count'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `sample_count` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='entries'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `entries` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='wins'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `wins` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='losses'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `losses` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='ambiguous'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `ambiguous` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='expired_no_entry'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `expired_no_entry` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='avg_mfe_r'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `avg_mfe_r` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='avg_mae_r'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `avg_mae_r` DECIMAL(12,6) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='avg_duration_seconds'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `avg_duration_seconds` INT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='strategy_slug'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `strategy_slug` VARCHAR(100) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='strategy_version'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `strategy_version` VARCHAR(40) NULL DEFAULT ''1.0'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='timeframe'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `timeframe` VARCHAR(8) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='direction'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `direction` VARCHAR(12) NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='market_regime'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `market_regime` VARCHAR(30) NULL DEFAULT ''ALL'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='sample_size'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `sample_size` INT UNSIGNED NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='win_rate'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `win_rate` DECIMAL(8,4) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='ambiguous_rate'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `ambiguous_rate` DECIMAL(8,4) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='reliability_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `reliability_score` DECIMAL(6,2) NULL DEFAULT 50'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='recency_weighted_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `recency_weighted_score` DECIMAL(6,2) NULL DEFAULT 50'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='evidence_level'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `evidence_level` VARCHAR(20) NULL DEFAULT ''insufficient'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='meta'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `meta` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='calculated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `calculated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_strategy_learning_states` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_system_settings` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='key'),
  'SELECT 1',
  'ALTER TABLE `pulse_system_settings` ADD COLUMN `key` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='value'),
  'SELECT 1',
  'ALTER TABLE `pulse_system_settings` ADD COLUMN `value` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='type'),
  'SELECT 1',
  'ALTER TABLE `pulse_system_settings` ADD COLUMN `type` VARCHAR(20) NOT NULL DEFAULT ''string'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='group'),
  'SELECT 1',
  'ALTER TABLE `pulse_system_settings` ADD COLUMN `group` VARCHAR(50) NOT NULL DEFAULT ''general'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='description'),
  'SELECT 1',
  'ALTER TABLE `pulse_system_settings` ADD COLUMN `description` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='signal_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `signal_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `symbol` VARCHAR(30) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='side'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `side` VARCHAR(12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='environment'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `environment` VARCHAR(20) NOT NULL DEFAULT ''testnet'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='order_type'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `order_type` VARCHAR(20) NOT NULL DEFAULT ''MARKET'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='leverage'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `leverage` INT UNSIGNED NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='quantity'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `quantity` DECIMAL(24,12) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='entry_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `entry_price` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='current_price'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `current_price` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='stop_loss'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `stop_loss` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='take_profit'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `take_profit` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_order_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `exchange_order_id` VARCHAR(100) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_tp_order_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `exchange_tp_order_id` VARCHAR(100) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_sl_order_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `exchange_sl_order_id` VARCHAR(100) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_position_side'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `exchange_position_side` VARCHAR(20) NOT NULL DEFAULT ''BOTH'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''pending'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='realized_pnl'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `realized_pnl` DECIMAL(20,8) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='fees'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `fees` DECIMAL(20,8) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='opened_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `opened_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='closed_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `closed_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='close_reason'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `close_reason` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='meta'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `meta` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_close_order_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `exchange_close_order_id` VARCHAR(100) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='protection_status'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `protection_status` VARCHAR(30) NOT NULL DEFAULT ''not_required'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='unrealized_pnl'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `unrealized_pnl` DECIMAL(20,8) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='commission_asset'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `commission_asset` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='last_synced_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_trades` ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='environment'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `environment` VARCHAR(20) NOT NULL DEFAULT ''testnet'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='auto_trade_enabled'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `auto_trade_enabled` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='default_leverage'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `default_leverage` INT UNSIGNED NOT NULL DEFAULT 3'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='risk_per_trade_percent'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `risk_per_trade_percent` DECIMAL(6,2) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='default_order_type'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `default_order_type` VARCHAR(20) NOT NULL DEFAULT ''MARKET'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='take_profit_percent'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `take_profit_percent` DECIMAL(6,2) NOT NULL DEFAULT 2'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='stop_loss_percent'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `stop_loss_percent` DECIMAL(6,2) NOT NULL DEFAULT 1'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='daily_loss_limit'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `daily_loss_limit` DECIMAL(14,2) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='max_open_positions'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `max_open_positions` INT UNSIGNED NOT NULL DEFAULT 2'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='selected_pairs'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `selected_pairs` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='notification_preferences'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `notification_preferences` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='pair_selection_saved_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `pair_selection_saved_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='pair_selection_locked_until'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `pair_selection_locked_until` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='execution_mode'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `execution_mode` VARCHAR(30) NOT NULL DEFAULT ''signal_only'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='emergency_stop'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `emergency_stop` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='margin_type'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `margin_type` VARCHAR(20) NOT NULL DEFAULT ''ISOLATED'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='position_mode'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `position_mode` VARCHAR(20) NOT NULL DEFAULT ''BOTH'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='sizing_mode'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `sizing_mode` VARCHAR(30) NOT NULL DEFAULT ''fixed_notional'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='fixed_notional'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `fixed_notional` DECIMAL(20,8) NOT NULL DEFAULT 25'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='fixed_quantity'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `fixed_quantity` DECIMAL(24,12) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='minimum_signal_score'),
  'SELECT 1',
  'ALTER TABLE `pulse_user_settings` ADD COLUMN `minimum_signal_score` DECIMAL(6,2) NOT NULL DEFAULT 70'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='title'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='slug'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='summary'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `summary` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='body'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `body` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='category'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `category` VARCHAR(255) NOT NULL DEFAULT ''Research'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='asset_symbol'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `asset_symbol` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='risk_level'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `risk_level` VARCHAR(30) NOT NULL DEFAULT ''not_rated'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='image_url'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `image_url` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''draft'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='is_featured'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='published_at'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `published_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='deleted_at'),
  'SELECT 1',
  'ALTER TABLE `research_reports` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `sessions` ADD COLUMN `id` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `sessions` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='ip_address'),
  'SELECT 1',
  'ALTER TABLE `sessions` ADD COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='user_agent'),
  'SELECT 1',
  'ALTER TABLE `sessions` ADD COLUMN `user_agent` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='payload'),
  'SELECT 1',
  'ALTER TABLE `sessions` ADD COLUMN `payload` LONGTEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='last_activity'),
  'SELECT 1',
  'ALTER TABLE `sessions` ADD COLUMN `last_activity` INT NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `site_settings` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='key'),
  'SELECT 1',
  'ALTER TABLE `site_settings` ADD COLUMN `key` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='value'),
  'SELECT 1',
  'ALTER TABLE `site_settings` ADD COLUMN `value` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='type'),
  'SELECT 1',
  'ALTER TABLE `site_settings` ADD COLUMN `type` VARCHAR(255) NOT NULL DEFAULT ''string'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='group'),
  'SELECT 1',
  'ALTER TABLE `site_settings` ADD COLUMN `group` VARCHAR(255) NOT NULL DEFAULT ''general'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='service'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `service` VARCHAR(50) NOT NULL DEFAULT ''pulse'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT ''pending'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='pulse_plan_id'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `pulse_plan_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='approved_by'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `approved_by` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='starts_at'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `starts_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='ends_at'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `ends_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='permissions'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `permissions` JSON NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='notes'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `notes` TEXT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='trial_used_at'),
  'SELECT 1',
  'ALTER TABLE `user_service_access` ADD COLUMN `trial_used_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='name'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email_verified_at'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `email_verified_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country_code'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `country_code` VARCHAR(8) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(32) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `country` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `role` VARCHAR(40) NOT NULL DEFAULT ''user'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `status` VARCHAR(40) NOT NULL DEFAULT ''active'''
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='private_member_approved_at'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `private_member_approved_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_login_at'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `last_login_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='remember_token'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(100) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='deleted_at'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='id'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `id` BIGINT UNSIGNED NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='user_id'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='symbol'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `symbol` VARCHAR(30) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='display_name'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `display_name` VARCHAR(255) NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='sort_order'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='created_at'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='updated_at'),
  'SELECT 1',
  'ALTER TABLE `watchlists` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL'
);
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

-- Compatibility normalization used by ABS repair logic.
-- Widen legacy enum-like user columns so current role/status values are not blocked.
SET @abs_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role' AND DATA_TYPE='enum'), 'ALTER TABLE `users` MODIFY `role` VARCHAR(40) NOT NULL DEFAULT ''user''', 'SELECT 1');
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;
SET @abs_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status' AND DATA_TYPE='enum'), 'ALTER TABLE `users` MODIFY `status` VARCHAR(40) NOT NULL DEFAULT ''active''', 'SELECT 1');
PREPARE abs_stmt FROM @abs_sql; EXECUTE abs_stmt; DEALLOCATE PREPARE abs_stmt;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- Verification: should return 0 missing required columns after a successful import.
SELECT COUNT(*) AS missing_required_columns FROM (
  SELECT 'users' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'users' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'users' AS table_name, 'email' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email')
UNION ALL
  SELECT 'users' AS table_name, 'email_verified_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email_verified_at')
UNION ALL
  SELECT 'users' AS table_name, 'country_code' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country_code')
UNION ALL
  SELECT 'users' AS table_name, 'phone' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone')
UNION ALL
  SELECT 'users' AS table_name, 'country' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='country')
UNION ALL
  SELECT 'users' AS table_name, 'password' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password')
UNION ALL
  SELECT 'users' AS table_name, 'role' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role')
UNION ALL
  SELECT 'users' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'users' AS table_name, 'private_member_approved_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='private_member_approved_at')
UNION ALL
  SELECT 'users' AS table_name, 'last_login_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_login_at')
UNION ALL
  SELECT 'users' AS table_name, 'remember_token' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='remember_token')
UNION ALL
  SELECT 'users' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'users' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='updated_at')
  UNION ALL
  SELECT 'users' AS table_name, 'deleted_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='deleted_at')
UNION ALL
  SELECT 'password_reset_tokens' AS table_name, 'email' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='email')
UNION ALL
  SELECT 'password_reset_tokens' AS table_name, 'token' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='token')
UNION ALL
  SELECT 'password_reset_tokens' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'sessions' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'sessions' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'sessions' AS table_name, 'ip_address' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='ip_address')
UNION ALL
  SELECT 'sessions' AS table_name, 'user_agent' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='user_agent')
UNION ALL
  SELECT 'sessions' AS table_name, 'payload' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='payload')
UNION ALL
  SELECT 'sessions' AS table_name, 'last_activity' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessions' AND COLUMN_NAME='last_activity')
UNION ALL
  SELECT 'cache' AS table_name, 'key' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache' AND COLUMN_NAME='key')
UNION ALL
  SELECT 'cache' AS table_name, 'value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache' AND COLUMN_NAME='value')
UNION ALL
  SELECT 'cache' AS table_name, 'expiration' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache' AND COLUMN_NAME='expiration')
UNION ALL
  SELECT 'cache_locks' AS table_name, 'key' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache_locks' AND COLUMN_NAME='key')
UNION ALL
  SELECT 'cache_locks' AS table_name, 'owner' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache_locks' AND COLUMN_NAME='owner')
UNION ALL
  SELECT 'cache_locks' AS table_name, 'expiration' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cache_locks' AND COLUMN_NAME='expiration')
UNION ALL
  SELECT 'jobs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'jobs' AS table_name, 'queue' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='queue')
UNION ALL
  SELECT 'jobs' AS table_name, 'payload' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='payload')
UNION ALL
  SELECT 'jobs' AS table_name, 'attempts' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='attempts')
UNION ALL
  SELECT 'jobs' AS table_name, 'reserved_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='reserved_at')
UNION ALL
  SELECT 'jobs' AS table_name, 'available_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='available_at')
UNION ALL
  SELECT 'jobs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'job_batches' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'job_batches' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'job_batches' AS table_name, 'total_jobs' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='total_jobs')
UNION ALL
  SELECT 'job_batches' AS table_name, 'pending_jobs' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='pending_jobs')
UNION ALL
  SELECT 'job_batches' AS table_name, 'failed_jobs' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='failed_jobs')
UNION ALL
  SELECT 'job_batches' AS table_name, 'failed_job_ids' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='failed_job_ids')
UNION ALL
  SELECT 'job_batches' AS table_name, 'options' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='options')
UNION ALL
  SELECT 'job_batches' AS table_name, 'cancelled_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='cancelled_at')
UNION ALL
  SELECT 'job_batches' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'job_batches' AS table_name, 'finished_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='job_batches' AND COLUMN_NAME='finished_at')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'uuid' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='uuid')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'connection' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='connection')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'queue' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='queue')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'payload' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='payload')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'exception' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='exception')
UNION ALL
  SELECT 'failed_jobs' AS table_name, 'failed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='failed_jobs' AND COLUMN_NAME='failed_at')
UNION ALL
  SELECT 'products' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'products' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'products' AS table_name, 'slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='slug')
UNION ALL
  SELECT 'products' AS table_name, 'category' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='category')
UNION ALL
  SELECT 'products' AS table_name, 'tagline' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='tagline')
UNION ALL
  SELECT 'products' AS table_name, 'description' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='description')
UNION ALL
  SELECT 'products' AS table_name, 'icon' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='icon')
UNION ALL
  SELECT 'products' AS table_name, 'accent' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='accent')
UNION ALL
  SELECT 'products' AS table_name, 'features' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='features')
UNION ALL
  SELECT 'products' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'products' AS table_name, 'sort_order' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='sort_order')
UNION ALL
  SELECT 'products' AS table_name, 'is_featured' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='is_featured')
UNION ALL
  SELECT 'products' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'products' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'products' AS table_name, 'deleted_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='deleted_at')
UNION ALL
  SELECT 'news_articles' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'news_articles' AS table_name, 'title' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='title')
UNION ALL
  SELECT 'news_articles' AS table_name, 'slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='slug')
UNION ALL
  SELECT 'news_articles' AS table_name, 'excerpt' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='excerpt')
UNION ALL
  SELECT 'news_articles' AS table_name, 'body' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='body')
UNION ALL
  SELECT 'news_articles' AS table_name, 'category' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='category')
UNION ALL
  SELECT 'news_articles' AS table_name, 'image_url' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='image_url')
UNION ALL
  SELECT 'news_articles' AS table_name, 'source_name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='source_name')
UNION ALL
  SELECT 'news_articles' AS table_name, 'source_url' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='source_url')
UNION ALL
  SELECT 'news_articles' AS table_name, 'author_name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='author_name')
UNION ALL
  SELECT 'news_articles' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'news_articles' AS table_name, 'is_featured' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='is_featured')
UNION ALL
  SELECT 'news_articles' AS table_name, 'published_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='published_at')
UNION ALL
  SELECT 'news_articles' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'news_articles' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'news_articles' AS table_name, 'deleted_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='news_articles' AND COLUMN_NAME='deleted_at')
UNION ALL
  SELECT 'research_reports' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'research_reports' AS table_name, 'title' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='title')
UNION ALL
  SELECT 'research_reports' AS table_name, 'slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='slug')
UNION ALL
  SELECT 'research_reports' AS table_name, 'summary' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='summary')
UNION ALL
  SELECT 'research_reports' AS table_name, 'body' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='body')
UNION ALL
  SELECT 'research_reports' AS table_name, 'category' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='category')
UNION ALL
  SELECT 'research_reports' AS table_name, 'asset_symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='asset_symbol')
UNION ALL
  SELECT 'research_reports' AS table_name, 'risk_level' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='risk_level')
UNION ALL
  SELECT 'research_reports' AS table_name, 'image_url' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='image_url')
UNION ALL
  SELECT 'research_reports' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'research_reports' AS table_name, 'is_featured' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='is_featured')
UNION ALL
  SELECT 'research_reports' AS table_name, 'published_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='published_at')
UNION ALL
  SELECT 'research_reports' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'research_reports' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'research_reports' AS table_name, 'deleted_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='research_reports' AND COLUMN_NAME='deleted_at')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'title' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='title')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='slug')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'excerpt' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='excerpt')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'body' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='body')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'category' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='category')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'level' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='level')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'duration_minutes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='duration_minutes')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'is_featured' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='is_featured')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'published_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='published_at')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'learning_articles' AS table_name, 'deleted_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='learning_articles' AND COLUMN_NAME='deleted_at')
UNION ALL
  SELECT 'economic_events' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'economic_events' AS table_name, 'title' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='title')
UNION ALL
  SELECT 'economic_events' AS table_name, 'country' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='country')
UNION ALL
  SELECT 'economic_events' AS table_name, 'currency' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='currency')
UNION ALL
  SELECT 'economic_events' AS table_name, 'impact' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='impact')
UNION ALL
  SELECT 'economic_events' AS table_name, 'event_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='event_at')
UNION ALL
  SELECT 'economic_events' AS table_name, 'previous_value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='previous_value')
UNION ALL
  SELECT 'economic_events' AS table_name, 'forecast_value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='forecast_value')
UNION ALL
  SELECT 'economic_events' AS table_name, 'actual_value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='actual_value')
UNION ALL
  SELECT 'economic_events' AS table_name, 'source' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='source')
UNION ALL
  SELECT 'economic_events' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'economic_events' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='economic_events' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'site_settings' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'site_settings' AS table_name, 'key' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='key')
UNION ALL
  SELECT 'site_settings' AS table_name, 'value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='value')
UNION ALL
  SELECT 'site_settings' AS table_name, 'type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='type')
UNION ALL
  SELECT 'site_settings' AS table_name, 'group' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_settings' AND COLUMN_NAME='group')
UNION ALL
  SELECT 'community_posts' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'community_posts' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'community_posts' AS table_name, 'title' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='title')
UNION ALL
  SELECT 'community_posts' AS table_name, 'body' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='body')
UNION ALL
  SELECT 'community_posts' AS table_name, 'category' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='category')
UNION ALL
  SELECT 'community_posts' AS table_name, 'sentiment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='sentiment')
UNION ALL
  SELECT 'community_posts' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'community_posts' AS table_name, 'is_pinned' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='is_pinned')
UNION ALL
  SELECT 'community_posts' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'community_posts' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'community_posts' AS table_name, 'deleted_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_posts' AND COLUMN_NAME='deleted_at')
UNION ALL
  SELECT 'community_comments' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'community_comments' AS table_name, 'community_post_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='community_post_id')
UNION ALL
  SELECT 'community_comments' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'community_comments' AS table_name, 'body' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='body')
UNION ALL
  SELECT 'community_comments' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'community_comments' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'community_comments' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='community_comments' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'email' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='email')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'preferences' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='preferences')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'confirmed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='confirmed_at')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'unsubscribed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='unsubscribed_at')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'newsletter_subscribers' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'watchlists' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'watchlists' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'watchlists' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'watchlists' AS table_name, 'display_name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='display_name')
UNION ALL
  SELECT 'watchlists' AS table_name, 'sort_order' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='sort_order')
UNION ALL
  SELECT 'watchlists' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'watchlists' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='watchlists' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'account_name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='account_name')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'currency' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='currency')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'opening_value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='opening_value')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'current_value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='current_value')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'net_contributions' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='net_contributions')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'total_profit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='total_profit')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'monthly_profit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='monthly_profit')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'valuation_date' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='valuation_date')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'is_active' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='is_active')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='notes')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'portfolio_accounts' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_accounts' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'portfolio_account_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='portfolio_account_id')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='type')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'amount' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='amount')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'transaction_date' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='transaction_date')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'reference' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='reference')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'description' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='description')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'portfolio_transactions' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='portfolio_transactions' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'portfolio_account_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='portfolio_account_id')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'statement_month' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='statement_month')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'opening_balance' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='opening_balance')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'contributions' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='contributions')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'withdrawals' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='withdrawals')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'profit_loss' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='profit_loss')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'closing_balance' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='closing_balance')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='notes')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'pdf_path' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='pdf_path')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'published_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='published_at')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'monthly_statements' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monthly_statements' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'notifications' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'notifications' AS table_name, 'type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='type')
UNION ALL
  SELECT 'notifications' AS table_name, 'notifiable_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='notifiable_type')
UNION ALL
  SELECT 'notifications' AS table_name, 'notifiable_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='notifiable_id')
UNION ALL
  SELECT 'notifications' AS table_name, 'data' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='data')
UNION ALL
  SELECT 'notifications' AS table_name, 'read_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='read_at')
UNION ALL
  SELECT 'notifications' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'notifications' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'event' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='event')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'recipient_email' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='recipient_email')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'subject' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='subject')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'error_message' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='error_message')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'metadata' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='metadata')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'sent_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='sent_at')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'email_delivery_logs' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_delivery_logs' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'device_uuid' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='device_uuid')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'platform' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='platform')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'device_name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='device_name')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'app_version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='app_version')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'os_version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='os_version')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'push_token' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='push_token')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'is_active' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='is_active')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'last_seen_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='last_seen_at')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'mobile_devices' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mobile_devices' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'email' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='email')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'subject' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='subject')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'category' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='category')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'message' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='message')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'priority' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='priority')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'admin_notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='admin_notes')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'replied_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='replied_at')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'contact_messages' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_messages' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'tokenable_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='tokenable_type')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'tokenable_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='tokenable_id')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'token' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='token')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'abilities' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='abilities')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'last_used_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='last_used_at')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'expires_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='expires_at')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'personal_access_tokens' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personal_access_tokens' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='slug')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'description' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='description')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'monthly_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='monthly_price')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'currency' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='currency')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'scanner_runs_per_day' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='scanner_runs_per_day')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'signals_per_day' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='signals_per_day')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'minimum_signal_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='minimum_signal_score')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'manual_trades_per_day' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='manual_trades_per_day')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'auto_trades_per_day' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='auto_trades_per_day')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'max_open_trades' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='max_open_trades')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'max_selected_pairs' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='max_selected_pairs')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'pair_access_mode' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='pair_access_mode')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'allow_testnet_trading' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_testnet_trading')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'allow_manual_trading' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_manual_trading')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'allow_live_trading' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_live_trading')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'allow_auto_trading' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_auto_trading')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'allow_mobile_api' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_mobile_api')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'capabilities' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='capabilities')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'is_active' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_active')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'sort_order' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='sort_order')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'is_trial' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_trial')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'is_public' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_public')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'request_enabled' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='request_enabled')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'requires_payment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='requires_payment')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'access_days' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='access_days')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'badge' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='badge')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'is_featured' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='is_featured')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_plans' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'pulse_plan_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='pulse_plan_id')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'pulse_strategy_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='pulse_strategy_id')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'is_enabled' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='is_enabled')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'weight_override' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='weight_override')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_plan_strategies' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_strategies' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_plan_pairs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_plan_pairs' AS table_name, 'pulse_plan_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='pulse_plan_id')
UNION ALL
  SELECT 'pulse_plan_pairs' AS table_name, 'pulse_pair_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='pulse_pair_id')
UNION ALL
  SELECT 'pulse_plan_pairs' AS table_name, 'is_enabled' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='is_enabled')
UNION ALL
  SELECT 'pulse_plan_pairs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_plan_pairs' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plan_pairs' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'service' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='service')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'pulse_plan_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='pulse_plan_id')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'approved_by' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='approved_by')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'starts_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='starts_at')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'ends_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='ends_at')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'trial_used_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='trial_used_at')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'permissions' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='permissions')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='notes')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'user_service_access' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_service_access' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'name' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='name')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='slug')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='version')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'description' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='description')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'weight' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='weight')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'minimum_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='minimum_score')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'settings' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='settings')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'is_enabled' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='is_enabled')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'sort_order' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='sort_order')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_strategies' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategies' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'base_asset' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='base_asset')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'quote_asset' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='quote_asset')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'is_enabled' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='is_enabled')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'sort_order' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='sort_order')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'price_precision' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='price_precision')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'quantity_precision' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='quantity_precision')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'tick_size' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='tick_size')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'step_size' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='step_size')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'minimum_quantity' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='minimum_quantity')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'minimum_notional' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='minimum_notional')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'last_synced_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='last_synced_at')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_pairs' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_pairs' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'environment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='environment')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'execution_mode' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='execution_mode')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'auto_trade_enabled' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='auto_trade_enabled')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'emergency_stop' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='emergency_stop')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'default_leverage' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='default_leverage')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'margin_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='margin_type')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'position_mode' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='position_mode')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'risk_per_trade_percent' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='risk_per_trade_percent')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'sizing_mode' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='sizing_mode')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'fixed_notional' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='fixed_notional')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'fixed_quantity' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='fixed_quantity')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'minimum_signal_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='minimum_signal_score')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'default_order_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='default_order_type')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'take_profit_percent' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='take_profit_percent')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'stop_loss_percent' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='stop_loss_percent')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'daily_loss_limit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='daily_loss_limit')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'max_open_positions' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='max_open_positions')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'selected_pairs' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='selected_pairs')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'notification_preferences' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='notification_preferences')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'pair_selection_saved_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='pair_selection_saved_at')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'pair_selection_locked_until' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='pair_selection_locked_until')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_user_settings' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_user_settings' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'environment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='environment')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'label' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='label')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'api_key' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='api_key')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'api_secret' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='api_secret')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'is_active' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='is_active')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'permissions' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='permissions')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'last_tested_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='last_tested_at')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'last_error' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='last_error')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'binance_connections' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='binance_connections' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'pairs_scanned' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='pairs_scanned')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'signals_created' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='signals_created')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'started_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='started_at')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'completed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='completed_at')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'summary' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='summary')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'error_message' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='error_message')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_scanner_runs' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'scanner_run_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='scanner_run_id')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'direction' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='direction')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'entry_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='entry_price')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'stop_loss' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='stop_loss')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'take_profit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='take_profit')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='score')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'confidence_label' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='confidence_label')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'strategy_breakdown' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='strategy_breakdown')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'generated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='generated_at')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'expires_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='expires_at')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'signal_fingerprint' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='signal_fingerprint')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'strategy_version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='strategy_version')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'strategy_snapshot' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='strategy_snapshot')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'take_profit_levels' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='take_profit_levels')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'technical_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='technical_score')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'reliability_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='reliability_score')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'confidence_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='confidence_score')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_signals' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'signal_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='signal_id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'side' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='side')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'environment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='environment')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'order_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='order_type')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'leverage' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='leverage')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'quantity' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='quantity')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'entry_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='entry_price')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'current_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='current_price')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'stop_loss' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='stop_loss')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'take_profit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='take_profit')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'exchange_order_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_order_id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'exchange_tp_order_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_tp_order_id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'exchange_sl_order_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_sl_order_id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'exchange_close_order_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_close_order_id')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'exchange_position_side' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='exchange_position_side')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'protection_status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='protection_status')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'realized_pnl' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='realized_pnl')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'unrealized_pnl' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='unrealized_pnl')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'fees' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='fees')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'commission_asset' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='commission_asset')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'opened_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='opened_at')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'closed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='closed_at')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'last_synced_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='last_synced_at')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'close_reason' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='close_reason')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'meta' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='meta')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_trades' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_trades' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='type')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'title' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='title')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'message' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='message')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'severity' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='severity')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'is_read' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='is_read')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'action_url' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='action_url')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'data' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='data')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_alerts' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_alerts' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_system_settings' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_system_settings' AS table_name, 'key' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='key')
UNION ALL
  SELECT 'pulse_system_settings' AS table_name, 'value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='value')
UNION ALL
  SELECT 'pulse_system_settings' AS table_name, 'type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='type')
UNION ALL
  SELECT 'pulse_system_settings' AS table_name, 'group' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='group')
UNION ALL
  SELECT 'pulse_system_settings' AS table_name, 'description' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_system_settings' AND COLUMN_NAME='description')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'action' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='action')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'entity_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='entity_type')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'entity_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='entity_id')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'environment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='environment')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'ip_address' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='ip_address')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'context' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='context')
UNION ALL
  SELECT 'pulse_audit_logs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_audit_logs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'environment' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='environment')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'signals_reviewed' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='signals_reviewed')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'trades_created' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='trades_created')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'summary' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='summary')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'error_message' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='error_message')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'started_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='started_at')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'completed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='completed_at')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_automation_runs' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_automation_runs' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'code' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='code')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'label' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='label')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='type')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'discount_type' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='discount_type')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'discount_value' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='discount_value')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'applicable_plan_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='applicable_plan_id')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'assigned_user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='assigned_user_id')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'access_days' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='access_days')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'max_uses' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='max_uses')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'per_user_limit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='per_user_limit')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'valid_from' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='valid_from')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'valid_until' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='valid_until')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'auto_activate' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='auto_activate')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'is_active' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='is_active')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='notes')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'created_by' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='created_by')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_promotion_codes' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_codes' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'pulse_plan_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='pulse_plan_id')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'base_amount' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='base_amount')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'discount_amount' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='discount_amount')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'final_amount' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='final_amount')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'currency' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='currency')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'network' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='network')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'wallet_address_snapshot' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='wallet_address_snapshot')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'payment_reference' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='payment_reference')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'payment_proof_path' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='payment_proof_path')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'promotion_code_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='promotion_code_id')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'promotion_code_snapshot' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='promotion_code_snapshot')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'activation_days' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='activation_days')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'user_notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='user_notes')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'admin_notes' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='admin_notes')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'reviewed_by' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='reviewed_by')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'reviewed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='reviewed_at')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'activated_access_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='activated_access_id')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_membership_requests' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_membership_requests' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='price')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'change_percent_24h' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='change_percent_24h')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'high_24h' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='high_24h')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'low_24h' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='low_24h')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'volume_24h' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='volume_24h')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'source' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='source')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'observed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='observed_at')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_market_prices' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_prices' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'open_time_ms' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='open_time_ms')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'close_time_ms' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='close_time_ms')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'open' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='open')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'high' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='high')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'low' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='low')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'close' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='close')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'volume' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='volume')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'is_closed' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='is_closed')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'source' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='source')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_market_candles' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_candles' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'status' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='status')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'prices_updated' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='prices_updated')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'candle_symbols_updated' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='candle_symbols_updated')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'validation_symbols_updated' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='validation_symbols_updated')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'summary' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='summary')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'error_message' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='error_message')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'started_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='started_at')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'completed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='completed_at')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_market_data_runs' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_market_data_runs' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'signal_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='signal_id')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'signal_fingerprint' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='signal_fingerprint')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'symbol' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='symbol')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'direction' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='direction')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'strategy_version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='strategy_version')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'strategy_snapshot' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='strategy_snapshot')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'entry_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='entry_price')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'stop_loss' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='stop_loss')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'take_profit_levels' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='take_profit_levels')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'technical_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='technical_score')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'reliability_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='reliability_score')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'confidence_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='confidence_score')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'state' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='state')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'outcome' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='outcome')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'generated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='generated_at')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'entry_hit_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='entry_hit_at')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'resolved_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='resolved_at')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'last_checked_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='last_checked_at')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'entry_observed_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='entry_observed_price')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'mfe_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mfe_price')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'mae_price' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mae_price')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'mfe_r' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mfe_r')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'mae_r' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='mae_r')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'duration_seconds' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='duration_seconds')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'highest_tp_level_hit' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='highest_tp_level_hit')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'market_regime' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='market_regime')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'context' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='context')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'meta' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='meta')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_signal_validations' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_validations' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'metric_date' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='metric_date')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'direction' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='direction')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'signals' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='signals')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'entries' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='entries')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'wins' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='wins')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'losses' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='losses')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'ambiguous' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='ambiguous')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'expired_no_entry' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='expired_no_entry')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'expired_after_entry' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='expired_after_entry')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'avg_mfe_r' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='avg_mfe_r')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'avg_mae_r' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='avg_mae_r')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'avg_duration_seconds' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='avg_duration_seconds')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_signal_daily_metrics' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signal_daily_metrics' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'metric_date' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='metric_date')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'strategy_slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='strategy_slug')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'strategy_version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='strategy_version')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'direction' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='direction')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'market_regime' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='market_regime')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'sample_count' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='sample_count')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'entries' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='entries')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'wins' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='wins')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'losses' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='losses')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'ambiguous' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='ambiguous')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'expired_no_entry' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='expired_no_entry')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'avg_mfe_r' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='avg_mfe_r')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'avg_mae_r' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='avg_mae_r')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'avg_duration_seconds' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='avg_duration_seconds')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_strategy_daily_metrics' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_daily_metrics' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'strategy_slug' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='strategy_slug')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'strategy_version' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='strategy_version')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'timeframe' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='timeframe')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'direction' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='direction')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'market_regime' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='market_regime')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'sample_size' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='sample_size')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'win_rate' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='win_rate')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'ambiguous_rate' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='ambiguous_rate')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'reliability_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='reliability_score')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'recency_weighted_score' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='recency_weighted_score')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'evidence_level' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='evidence_level')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'meta' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='meta')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'calculated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='calculated_at')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_strategy_learning_states' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_strategy_learning_states' AND COLUMN_NAME='updated_at')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='id')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'promotion_code_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='promotion_code_id')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'user_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='user_id')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'pulse_plan_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='pulse_plan_id')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'membership_request_id' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='membership_request_id')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'discount_amount' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='discount_amount')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'redeemed_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='redeemed_at')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'created_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='created_at')
UNION ALL
  SELECT 'pulse_promotion_redemptions' AS table_name, 'updated_at' AS missing_column WHERE NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_promotion_redemptions' AND COLUMN_NAME='updated_at')
) AS abs_missing;

SELECT 'ABS V14.8.22 schema create/repair completed. missing_required_columns should be 0.' AS result;