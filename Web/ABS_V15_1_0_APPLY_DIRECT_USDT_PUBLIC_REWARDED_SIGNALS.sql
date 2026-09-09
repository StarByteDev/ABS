-- Alpha Block Solutions V15.1.0
-- Direct USDT package activation + anonymous rewarded free-signal access.
-- Intended as a phpMyAdmin/shared-hosting fallback when `php artisan migrate --force` is unavailable.
-- Back up the database before importing.

CREATE TABLE IF NOT EXISTS `pulse_public_signal_unlocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_hash` VARCHAR(64) NOT NULL,
  `provider` VARCHAR(40) NOT NULL DEFAULT 'google_ad_manager',
  `provider_reference` VARCHAR(190) NOT NULL,
  `ad_unit` VARCHAR(255) NULL,
  `signal_id` BIGINT UNSIGNED NULL,
  `signal_snapshot` JSON NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'granted',
  `claimed_at` TIMESTAMP NULL,
  `view_expires_at` TIMESTAMP NULL,
  `next_available_at` TIMESTAMP NULL,
  `meta` JSON NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_public_signal_unlocks_provider_reference_unique` (`provider_reference`),
  KEY `pulse_public_signal_unlocks_visitor_hash_index` (`visitor_hash`),
  KEY `pulse_public_signal_unlocks_provider_index` (`provider`),
  KEY `pulse_public_signal_unlocks_signal_id_index` (`signal_id`),
  KEY `pulse_public_signal_unlocks_status_index` (`status`),
  KEY `pulse_public_signal_unlocks_claimed_at_index` (`claimed_at`),
  KEY `pulse_public_signal_unlocks_next_available_at_index` (`next_available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve V15 signal helper fields needed by current Pulse tooling.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='best_signal_id')=0,
  'ALTER TABLE `pulse_scanner_runs` ADD COLUMN `best_signal_id` BIGINT UNSIGNED NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='unlocked_at')=0,
  'ALTER TABLE `pulse_signals` ADD COLUMN `unlocked_at` TIMESTAMP NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='ai_explanation')=0,
  'ALTER TABLE `pulse_signals` ADD COLUMN `ai_explanation` LONGTEXT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='ai_explained_at')=0,
  'ALTER TABLE `pulse_signals` ADD COLUMN `ai_explained_at` TIMESTAMP NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='share_count')=0,
  'ALTER TABLE `pulse_signals` ADD COLUMN `share_count` INT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- Convert the known V15 duration packages to direct USDT checkout.
UPDATE `pulse_plans` SET `slug`='pulse-day-pass', `name`='Pulse Day Pass', `monthly_price`=IF(`monthly_price`>0,`monthly_price`,5.00), `currency`='USDT', `request_enabled`=1, `requires_payment`=1 WHERE `slug`='spark-day-pass';
UPDATE `pulse_plans` SET `slug`='pulse-flex', `name`='Pulse Flex', `monthly_price`=IF(`monthly_price`>0,`monthly_price`,12.00), `currency`='USDT', `request_enabled`=1, `requires_payment`=1 WHERE `slug`='spark-flex';
UPDATE `pulse_plans` SET `slug`='pulse-momentum', `name`='Pulse Momentum', `monthly_price`=IF(`monthly_price`>0,`monthly_price`,25.00), `currency`='USDT', `request_enabled`=1, `requires_payment`=1 WHERE `slug`='spark-momentum';
UPDATE `pulse_plans` SET `name`='Pulse Professional', `monthly_price`=IF(`monthly_price`>0,`monthly_price`,79.00), `currency`='USDT', `request_enabled`=1, `requires_payment`=1 WHERE `slug`='pulse-professional';
UPDATE `pulse_plans` SET `currency`='USDT', `request_enabled`=1, `requires_payment`=1 WHERE `is_trial`=0;
UPDATE `pulse_plans` SET `currency`='USDT', `request_enabled`=0, `requires_payment`=0 WHERE `is_trial`=1;

-- Remove obsolete account-credit settings and install the public rewarded-signal controls.
DELETE FROM `pulse_system_settings` WHERE `key` IN (
 'point_purchases_enabled','best_signal_points_enabled','rewarded_ads_enabled','rewarded_ad_points','rewarded_ad_xp',
 'daily_checkin_points','social_share_points','openai_signal_explanation_points','v15_points_defaults_seeded',
 'v1503_spark_packages_seeded','rewarded_ads_test_mode','rewarded_ads_android_unit_id','rewarded_ads_ios_unit_id',
 'rewarded_ads_web_unit_path','rewarded_ads_daily_limit','rewarded_ads_cooldown_minutes'
);

INSERT INTO `pulse_system_settings` (`key`,`value`,`type`,`group`,`description`) VALUES
('membership_requests_enabled','1','boolean','membership','Allow registered members to submit direct USDT package payments for Admin verification.'),
('promotion_codes_enabled','0','boolean','membership','Optional direct-package promotions; disabled by default.'),
('public_rewarded_signals_enabled','1','boolean','public_signal','Allow visitors without registration to watch a rewarded ad and reveal one random qualified Pulse signal.'),
('public_rewarded_signal_cooldown_minutes','30','integer','public_signal','Minutes a browser must wait after a successful rewarded signal unlock.'),
('public_rewarded_signal_view_seconds','30','integer','public_signal','Seconds a free rewarded signal remains visible after reward grant.'),
('public_rewarded_signal_min_claim_seconds','5','integer','public_signal','Minimum time between issuing a web reward session and accepting its browser claim.'),
('rewarded_web_test_mode','1','boolean','public_signal','Use Google rewarded-web test inventory until a production unit is configured.'),
('rewarded_web_ad_unit_input','','text','public_signal','Google Ad Manager rewarded ad-unit path or copied GPT snippet. ABS extracts only the ad unit path.'),
('rewarded_web_ad_unit_path','','string','public_signal','Resolved Google Ad Manager rewarded-web ad unit path.'),
('public_rewarded_signal_badge','FREE SIGNAL · REWARDED ACCESS','string','public_signal','Badge displayed on the public rewarded-signal gateway.'),
('public_rewarded_signal_title','Watch one ad. Unlock one qualified Pulse signal.','string','public_signal','Headline displayed above the rewarded-ad opt-in.'),
('public_rewarded_signal_description','No registration required. Complete the rewarded ad and your signal appears for 30 seconds.','text','public_signal','Description displayed on the rewarded-ad opt-in.'),
('public_rewarded_signal_cta','Watch Ad & Reveal Signal','string','public_signal','Primary rewarded-ad call-to-action label.')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),`type`=VALUES(`type`),`group`=VALUES(`group`),`description`=VALUES(`description`);

-- Remove retired economy/gamification tables. Membership requests/payment history are preserved.
DROP TABLE IF EXISTS `pulse_rewarded_ad_receipts`;
DROP TABLE IF EXISTS `pulse_reward_claims`;
DROP TABLE IF EXISTS `pulse_user_achievements`;
DROP TABLE IF EXISTS `pulse_achievements`;
DROP TABLE IF EXISTS `pulse_user_missions`;
DROP TABLE IF EXISTS `pulse_missions`;
DROP TABLE IF EXISTS `pulse_point_purchases`;
DROP TABLE IF EXISTS `pulse_point_ledger`;
DROP TABLE IF EXISTS `pulse_point_wallets`;
DROP TABLE IF EXISTS `pulse_point_packs`;

-- Safely remove retired economy columns on MariaDB/MySQL versions without DROP COLUMN IF EXISTS.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='price_points')>0, 'ALTER TABLE `pulse_plans` DROP COLUMN `price_points`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='best_signal_cost_points')>0, 'ALTER TABLE `pulse_plans` DROP COLUMN `best_signal_cost_points`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_plans' AND COLUMN_NAME='allow_points_activation')>0, 'ALTER TABLE `pulse_plans` DROP COLUMN `allow_points_activation`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_scanner_runs' AND COLUMN_NAME='points_charged')>0, 'ALTER TABLE `pulse_scanner_runs` DROP COLUMN `points_charged`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pulse_signals' AND COLUMN_NAME='points_cost')>0, 'ALTER TABLE `pulse_signals` DROP COLUMN `points_cost`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pulse_xp')>0, 'ALTER TABLE `users` DROP COLUMN `pulse_xp`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pulse_level')>0, 'ALTER TABLE `users` DROP COLUMN `pulse_level`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pulse_streak_days')>0, 'ALTER TABLE `users` DROP COLUMN `pulse_streak_days`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pulse_longest_streak')>0, 'ALTER TABLE `users` DROP COLUMN `pulse_longest_streak`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pulse_last_checkin_date')>0, 'ALTER TABLE `users` DROP COLUMN `pulse_last_checkin_date`', 'SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
