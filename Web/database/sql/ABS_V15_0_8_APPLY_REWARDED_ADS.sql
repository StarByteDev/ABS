-- ABS V15.0.8 — Rewarded Ads / Executive Admin safe upgrade
-- Non-destructive. Existing ABS V15 data is preserved.

CREATE TABLE IF NOT EXISTS `pulse_rewarded_ad_receipts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(40) NOT NULL,
  `provider_reference` VARCHAR(190) NOT NULL,
  `ad_unit` VARCHAR(255) NULL,
  `reward_amount` INT UNSIGNED NOT NULL DEFAULT 0,
  `reward_item` VARCHAR(100) NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'verified',
  `verification_mode` VARCHAR(60) NULL,
  `meta` JSON NULL,
  `verified_at` TIMESTAMP NULL,
  `rewarded_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_rewarded_ad_provider_reference_unique` (`provider`,`provider_reference`),
  KEY `pulse_rewarded_ad_receipts_user_id_index` (`user_id`),
  KEY `pulse_rewarded_ad_receipts_provider_index` (`provider`),
  KEY `pulse_rewarded_ad_receipts_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pulse_system_settings` (`key`,`value`,`type`,`group`,`description`) VALUES
('rewarded_ads_enabled','0','boolean','gamification','Allow members to opt in to rewarded ads and receive Pulse Sparks after verification.'),
('rewarded_ad_points','5','integer','gamification','Pulse Sparks credited for each verified rewarded ad.'),
('rewarded_ad_xp','5','integer','gamification','Experience points credited for each verified rewarded ad.'),
('rewarded_ads_provider','google_ad_manager','string','gamification','Web rewarded-ad provider.'),
('rewarded_ads_daily_limit','10','integer','gamification','Maximum rewarded ads credited to one member per day.'),
('rewarded_ads_cooldown_seconds','120','integer','gamification','Minimum wait between credited rewarded ads.'),
('rewarded_web_ad_unit_path','','string','gamification','Google Ad Manager rewarded web ad unit path.'),
('rewarded_web_test_mode','1','boolean','gamification','Use Google demo rewarded inventory while testing web rewards.'),
('rewarded_mobile_test_mode','1','boolean','gamification','Return Google test rewarded ad unit IDs to mobile clients.'),
('rewarded_admob_android_ad_unit_id','','string','gamification','Production Android AdMob rewarded ad unit ID.'),
('rewarded_admob_ios_ad_unit_id','','string','gamification','Production iOS AdMob rewarded ad unit ID.'),
('rewarded_admob_ssv_enabled','1','boolean','gamification','Require AdMob server-side reward signature verification.'),
('rewarded_admob_max_callback_age_seconds','3600','integer','gamification','Maximum accepted age of an AdMob SSV callback.');
