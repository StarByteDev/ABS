-- ABS V15.0.2 — Pulse Points / Gamification / Best Signal
-- NON-DESTRUCTIVE CREATE/ADD-ONLY REPAIR for MySQL / phpMyAdmin.
-- V15.0.2 does not create or require legacy daily scan/signal quota columns.
-- Existing business data is preserved. No destructive table/data reset operations are used.

DELIMITER $$
DROP PROCEDURE IF EXISTS abs_v15_add_column$$
CREATE PROCEDURE abs_v15_add_column(IN p_table VARCHAR(128), IN p_column VARCHAR(128), IN p_definition TEXT)
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = p_table)
     AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = p_table AND column_name = p_column) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL abs_v15_add_column('users','pulse_xp','BIGINT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('users','pulse_level','INT UNSIGNED NOT NULL DEFAULT 1');
CALL abs_v15_add_column('users','pulse_streak_days','INT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('users','pulse_longest_streak','INT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('users','pulse_last_checkin_date','DATE NULL');
CALL abs_v15_add_column('pulse_plans','price_points','INT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('pulse_plans','best_signal_cost_points','INT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('pulse_plans','allow_points_activation','TINYINT(1) NOT NULL DEFAULT 0');
CALL abs_v15_add_column('pulse_scanner_runs','best_signal_id','BIGINT UNSIGNED NULL');
CALL abs_v15_add_column('pulse_scanner_runs','points_charged','INT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('pulse_signals','points_cost','INT UNSIGNED NOT NULL DEFAULT 0');
CALL abs_v15_add_column('pulse_signals','unlocked_at','TIMESTAMP NULL');
CALL abs_v15_add_column('pulse_signals','ai_explanation','LONGTEXT NULL');
CALL abs_v15_add_column('pulse_signals','ai_explained_at','TIMESTAMP NULL');
CALL abs_v15_add_column('pulse_signals','share_count','INT UNSIGNED NOT NULL DEFAULT 0');

CREATE TABLE IF NOT EXISTS `pulse_point_wallets` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
 `balance` BIGINT NOT NULL DEFAULT 0, `lifetime_earned` BIGINT UNSIGNED NOT NULL DEFAULT 0, `lifetime_spent` BIGINT UNSIGNED NOT NULL DEFAULT 0,
 `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL, PRIMARY KEY (`id`), UNIQUE KEY `pulse_point_wallets_user_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_point_ledger` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `amount` BIGINT NOT NULL, `balance_after` BIGINT NOT NULL,
 `type` VARCHAR(40) NOT NULL, `source` VARCHAR(80) NOT NULL, `reference_type` VARCHAR(100) NULL, `reference_id` BIGINT UNSIGNED NULL,
 `idempotency_key` VARCHAR(190) NOT NULL, `description` VARCHAR(255) NULL, `meta` JSON NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`), UNIQUE KEY `pulse_point_ledger_idempotency_unique` (`idempotency_key`), KEY `pulse_point_ledger_user_created` (`user_id`,`created_at`), KEY `pulse_point_ledger_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_point_packs` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(120) NOT NULL, `description` TEXT NULL,
 `points` INT UNSIGNED NOT NULL, `bonus_points` INT UNSIGNED NOT NULL DEFAULT 0, `price_usdt` DECIMAL(12,2) NOT NULL,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `pulse_point_packs_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_point_purchases` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `pulse_point_pack_id` BIGINT UNSIGNED NULL, `status` VARCHAR(30) NOT NULL DEFAULT 'submitted',
 `points_snapshot` INT UNSIGNED NOT NULL, `bonus_points_snapshot` INT UNSIGNED NOT NULL DEFAULT 0, `amount_usdt` DECIMAL(12,2) NOT NULL,
 `network` VARCHAR(80) NULL, `wallet_address_snapshot` VARCHAR(500) NULL, `payment_reference` VARCHAR(190) NULL, `payment_proof_path` VARCHAR(500) NULL,
 `user_notes` TEXT NULL, `admin_notes` TEXT NULL, `reviewed_by` BIGINT UNSIGNED NULL, `reviewed_at` TIMESTAMP NULL, `credited_at` TIMESTAMP NULL,
 `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL, PRIMARY KEY (`id`), UNIQUE KEY `pulse_point_purchase_ref_unique` (`payment_reference`), KEY `pulse_point_purchase_user_created` (`user_id`,`created_at`), KEY `pulse_point_purchase_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_missions` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(120) NOT NULL, `description` TEXT NULL, `event_key` VARCHAR(80) NOT NULL,
 `period` VARCHAR(20) NOT NULL DEFAULT 'daily', `target_count` INT UNSIGNED NOT NULL DEFAULT 1, `reward_points` INT UNSIGNED NOT NULL DEFAULT 0, `reward_xp` INT UNSIGNED NOT NULL DEFAULT 0,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `pulse_missions_slug_unique` (`slug`), KEY `pulse_missions_event` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_user_missions` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `pulse_mission_id` BIGINT UNSIGNED NOT NULL, `period_key` VARCHAR(40) NOT NULL,
 `progress` INT UNSIGNED NOT NULL DEFAULT 0, `completed_at` TIMESTAMP NULL, `claimed_at` TIMESTAMP NULL, `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `pulse_user_mission_period_unique` (`user_id`,`pulse_mission_id`,`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_achievements` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(120) NOT NULL, `description` TEXT NULL, `metric_key` VARCHAR(80) NOT NULL,
 `target_value` BIGINT UNSIGNED NOT NULL DEFAULT 1, `reward_points` INT UNSIGNED NOT NULL DEFAULT 0, `reward_xp` INT UNSIGNED NOT NULL DEFAULT 0,
 `is_active` TINYINT(1) NOT NULL DEFAULT 1, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `pulse_achievements_slug_unique` (`slug`), KEY `pulse_achievements_metric` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_user_achievements` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `pulse_achievement_id` BIGINT UNSIGNED NOT NULL, `unlocked_at` TIMESTAMP NULL, `claimed_at` TIMESTAMP NULL,
 `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL, PRIMARY KEY (`id`), UNIQUE KEY `pulse_user_achievement_unique` (`user_id`,`pulse_achievement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pulse_reward_claims` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `reward_type` VARCHAR(60) NOT NULL, `reward_key` VARCHAR(190) NOT NULL,
 `points` INT UNSIGNED NOT NULL DEFAULT 0, `xp` INT UNSIGNED NOT NULL DEFAULT 0, `provider_reference` VARCHAR(190) NULL, `meta` JSON NULL, `claimed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`), UNIQUE KEY `pulse_reward_claim_unique` (`user_id`,`reward_type`,`reward_key`), KEY `pulse_reward_type` (`reward_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pulse_system_settings` (`key`,`value`,`type`,`group`,`description`) VALUES
('best_signal_points_enabled','1','boolean','points','Charge Best Signal PP only when a qualifying signal is unlocked.'),
('point_purchases_enabled','1','boolean','points','Allow authenticated users to submit Admin-verified USDT purchases for Pulse Points packs.'),
('daily_checkin_points','5','integer','gamification','Pulse Points awarded for one daily check-in.'),
('daily_checkin_xp','10','integer','gamification','XP awarded for one daily check-in.'),
('social_share_points','3','integer','gamification','Pulse Points awarded once per qualifying signal share.'),
('social_share_xp','5','integer','gamification','XP awarded once per qualifying signal share.'),
('rewarded_ads_enabled','0','boolean','gamification','Disabled until server-verified rewarded-ad callback is configured.'),
('rewarded_ad_points','5','integer','gamification','PP for provider-verified rewarded ad.'),
('rewarded_ad_xp','5','integer','gamification','XP for provider-verified rewarded ad.'),
('openai_signal_explanations_enabled','0','boolean','ai','Optional OpenAI plain-language signal explanations.'),
('openai_signal_explanation_points','0','integer','ai','Optional PP cost for AI signal explanation.');

INSERT IGNORE INTO `pulse_point_packs` (`name`,`slug`,`description`,`points`,`bonus_points`,`price_usdt`,`is_active`,`sort_order`,`created_at`,`updated_at`) VALUES
('Pulse Starter','pulse-starter','Entry Pulse Points pack.',100,0,10.00,1,10,NOW(),NOW()),
('Pulse Plus','pulse-plus','Mid-size Pulse Points pack with bonus PP.',500,50,45.00,1,20,NOW(),NOW()),
('Pulse Pro','pulse-pro','Large Pulse Points pack with bonus PP.',1200,200,99.00,1,30,NOW(),NOW());

INSERT IGNORE INTO `pulse_missions` (`name`,`slug`,`description`,`event_key`,`period`,`target_count`,`reward_points`,`reward_xp`,`is_active`,`sort_order`,`created_at`,`updated_at`) VALUES
('Daily Pulse','daily-checkin','Check in to Pulse once today.','daily_checkin','daily',1,0,10,1,10,NOW(),NOW()),
('Signal Hunter','find-best-signal','Find one qualified Best Signal.','best_signal_unlocked','daily',1,2,15,1,20,NOW(),NOW()),
('Market Routine','three-best-signals-week','Unlock three Best Signals this week.','best_signal_unlocked','weekly',3,5,30,1,30,NOW(),NOW());

INSERT IGNORE INTO `pulse_achievements` (`name`,`slug`,`description`,`metric_key`,`target_value`,`reward_points`,`reward_xp`,`is_active`,`sort_order`,`created_at`,`updated_at`) VALUES
('First Pulse','first-best-signal','Unlock your first qualified Best Signal.','best_signals',1,5,25,1,10,NOW(),NOW()),
('Pulse Regular','seven-day-streak','Reach a 7-day Pulse check-in streak.','streak_days',7,10,75,1,20,NOW(),NOW()),
('Signal Explorer','twenty-five-best-signals','Unlock 25 qualified Best Signals.','best_signals',25,25,150,1,30,NOW(),NOW());

-- Initialize the new plan PP columns once. The marker prevents later recovery
-- runs from resetting an administrator's PP price/activation choices.
DELIMITER $$
DROP PROCEDURE IF EXISTS abs_v15_seed_plan_defaults$$
CREATE PROCEDURE abs_v15_seed_plan_defaults()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM `pulse_system_settings` WHERE `key`='v15_points_defaults_seeded') THEN
    UPDATE `pulse_plans` SET `price_points`=500, `best_signal_cost_points`=10, `allow_points_activation`=1 WHERE `slug`='pulse-intelligence';
    UPDATE `pulse_plans` SET `price_points`=1200, `best_signal_cost_points`=5, `allow_points_activation`=1 WHERE `slug`='pulse-professional';
    INSERT IGNORE INTO `pulse_system_settings` (`key`,`value`,`type`,`group`,`description`) VALUES
    ('v15_points_defaults_seeded',NOW(),'string','system','Internal marker: ABS V15 Pulse Points commercial defaults were initialized once.');
  END IF;
END$$
CALL abs_v15_seed_plan_defaults()$$
DROP PROCEDURE IF EXISTS abs_v15_seed_plan_defaults$$
DROP PROCEDURE IF EXISTS abs_v15_add_column$$
DELIMITER ;


-- ABS V15.0.2 commerce reconciliation ---------------------------------------
-- USDT is accepted only for Pulse Points packs. Plans activate with PP.
-- These statements do not delete tables, columns, PP history or signals.
INSERT INTO `pulse_system_settings` (`key`,`value`,`type`,`group`,`description`) VALUES
('membership_requests_enabled','0','boolean','membership','Legacy direct membership requests are disabled in ABS V15.0.2. Plans activate with Pulse Points.'),
('promotion_codes_enabled','0','boolean','membership','Legacy direct membership checkout promotions are disabled in the V15 Pulse Points commerce model.')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),`type`=VALUES(`type`),`group`=VALUES(`group`),`description`=VALUES(`description`);

UPDATE `pulse_plans` SET `request_enabled`=0, `requires_payment`=0;
