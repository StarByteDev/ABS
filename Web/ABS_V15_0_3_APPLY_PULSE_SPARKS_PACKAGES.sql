-- ABS V15.0.3 — Pulse Sparks packages and naming upgrade
-- Apply after the V15.0.2 create-missing repair on an existing ABS database.
-- Non-destructive and idempotent: no users, access, wallets, ledger, signals,
-- validations, metrics, strategies or market data are deleted.
-- The one-time marker prevents a second import from overwriting later Admin edits.

INSERT IGNORE INTO `pulse_system_settings` (`key`,`value`,`type`,`group`,`description`) VALUES
('v1503_spark_packages_seeded','0','boolean','system','Internal marker: ABS V15.0.3 Pulse Sparks packages were initialized once.');

SET @abs_v1503_apply := IF(
  COALESCE((SELECT `value` FROM `pulse_system_settings` WHERE `key`='v1503_spark_packages_seeded' LIMIT 1),'0')='1',
  0,
  1
);

INSERT INTO `pulse_plans`
(`name`,`slug`,`description`,`monthly_price`,`currency`,`minimum_signal_score`,
 `manual_trades_per_day`,`auto_trades_per_day`,`max_open_trades`,`max_selected_pairs`,`pair_access_mode`,
 `allow_testnet_trading`,`allow_manual_trading`,`allow_live_trading`,`allow_auto_trading`,`allow_mobile_api`,
 `capabilities`,`is_active`,`sort_order`,`is_trial`,`is_public`,`request_enabled`,`requires_payment`,
 `access_days`,`badge`,`is_featured`,`price_points`,`best_signal_cost_points`,`allow_points_activation`,`created_at`,`updated_at`)
SELECT
 'Spark Day Pass','spark-day-pass','A focused 24-hour pass for checking today’s strongest Admin-qualified market opportunity.',
 0,'SPARKS',70,0,0,0,10,'all',0,0,0,0,1,
 '{"scanner":true,"signals":true,"orders":false,"trades":false,"reports":false,"binance":false,"alerts":true,"plan_view":true,"settings":true,"mobile_api":true,"testnet_trading":false,"manual_trading":false,"live_trading":false,"auto_trading":false}',
 1,10,0,1,0,0,1,'24-Hour Access',0,75,10,1,NOW(),NOW()
WHERE @abs_v1503_apply=1
ON DUPLICATE KEY UPDATE
 `name`=VALUES(`name`),`description`=VALUES(`description`),`monthly_price`=0,`currency`='SPARKS',
 `minimum_signal_score`=VALUES(`minimum_signal_score`),`manual_trades_per_day`=VALUES(`manual_trades_per_day`),
 `auto_trades_per_day`=VALUES(`auto_trades_per_day`),`max_open_trades`=VALUES(`max_open_trades`),
 `max_selected_pairs`=VALUES(`max_selected_pairs`),`pair_access_mode`='all',
 `allow_testnet_trading`=VALUES(`allow_testnet_trading`),`allow_manual_trading`=VALUES(`allow_manual_trading`),
 `allow_live_trading`=VALUES(`allow_live_trading`),`allow_auto_trading`=VALUES(`allow_auto_trading`),
 `allow_mobile_api`=1,`capabilities`=VALUES(`capabilities`),`is_active`=1,`sort_order`=10,
 `is_trial`=0,`is_public`=1,`request_enabled`=0,`requires_payment`=0,`access_days`=1,
 `badge`=VALUES(`badge`),`is_featured`=0,`price_points`=75,`best_signal_cost_points`=10,
 `allow_points_activation`=1,`updated_at`=NOW();

INSERT INTO `pulse_plans`
(`name`,`slug`,`description`,`monthly_price`,`currency`,`minimum_signal_score`,
 `manual_trades_per_day`,`auto_trades_per_day`,`max_open_trades`,`max_selected_pairs`,`pair_access_mode`,
 `allow_testnet_trading`,`allow_manual_trading`,`allow_live_trading`,`allow_auto_trading`,`allow_mobile_api`,
 `capabilities`,`is_active`,`sort_order`,`is_trial`,`is_public`,`request_enabled`,`requires_payment`,
 `access_days`,`badge`,`is_featured`,`price_points`,`best_signal_cost_points`,`allow_points_activation`,`created_at`,`updated_at`)
SELECT
 'Spark Flex','spark-flex','Three days of broader market coverage with signals, alerts and performance reporting.',
 0,'SPARKS',70,0,0,0,40,'all',0,0,0,0,1,
 '{"scanner":true,"signals":true,"orders":false,"trades":false,"reports":true,"binance":false,"alerts":true,"plan_view":true,"settings":true,"mobile_api":true,"testnet_trading":false,"manual_trading":false,"live_trading":false,"auto_trading":false}',
 1,20,0,1,0,0,3,'Flexible Access',0,180,8,1,NOW(),NOW()
WHERE @abs_v1503_apply=1
ON DUPLICATE KEY UPDATE
 `name`=VALUES(`name`),`description`=VALUES(`description`),`monthly_price`=0,`currency`='SPARKS',
 `minimum_signal_score`=VALUES(`minimum_signal_score`),`manual_trades_per_day`=VALUES(`manual_trades_per_day`),
 `auto_trades_per_day`=VALUES(`auto_trades_per_day`),`max_open_trades`=VALUES(`max_open_trades`),
 `max_selected_pairs`=VALUES(`max_selected_pairs`),`pair_access_mode`='all',
 `allow_testnet_trading`=VALUES(`allow_testnet_trading`),`allow_manual_trading`=VALUES(`allow_manual_trading`),
 `allow_live_trading`=VALUES(`allow_live_trading`),`allow_auto_trading`=VALUES(`allow_auto_trading`),
 `allow_mobile_api`=1,`capabilities`=VALUES(`capabilities`),`is_active`=1,`sort_order`=20,
 `is_trial`=0,`is_public`=1,`request_enabled`=0,`requires_payment`=0,`access_days`=3,
 `badge`=VALUES(`badge`),`is_featured`=0,`price_points`=180,`best_signal_cost_points`=8,
 `allow_points_activation`=1,`updated_at`=NOW();

INSERT INTO `pulse_plans`
(`name`,`slug`,`description`,`monthly_price`,`currency`,`minimum_signal_score`,
 `manual_trades_per_day`,`auto_trades_per_day`,`max_open_trades`,`max_selected_pairs`,`pair_access_mode`,
 `allow_testnet_trading`,`allow_manual_trading`,`allow_live_trading`,`allow_auto_trading`,`allow_mobile_api`,
 `capabilities`,`is_active`,`sort_order`,`is_trial`,`is_public`,`request_enabled`,`requires_payment`,
 `access_days`,`badge`,`is_featured`,`price_points`,`best_signal_cost_points`,`allow_points_activation`,`created_at`,`updated_at`)
SELECT
 'Spark Momentum','spark-momentum','A complete seven-day trading workflow with wider coverage, reports and protected Practice execution.',
 0,'SPARKS',70,25,0,3,150,'all',1,1,0,0,1,
 '{"scanner":true,"signals":true,"orders":true,"trades":true,"reports":true,"binance":true,"alerts":true,"plan_view":true,"settings":true,"mobile_api":true,"testnet_trading":true,"manual_trading":true,"live_trading":false,"auto_trading":false}',
 1,30,0,1,0,0,7,'Most Popular',0,380,5,1,NOW(),NOW()
WHERE @abs_v1503_apply=1
ON DUPLICATE KEY UPDATE
 `name`=VALUES(`name`),`description`=VALUES(`description`),`monthly_price`=0,`currency`='SPARKS',
 `minimum_signal_score`=VALUES(`minimum_signal_score`),`manual_trades_per_day`=VALUES(`manual_trades_per_day`),
 `auto_trades_per_day`=VALUES(`auto_trades_per_day`),`max_open_trades`=VALUES(`max_open_trades`),
 `max_selected_pairs`=VALUES(`max_selected_pairs`),`pair_access_mode`='all',
 `allow_testnet_trading`=VALUES(`allow_testnet_trading`),`allow_manual_trading`=VALUES(`allow_manual_trading`),
 `allow_live_trading`=VALUES(`allow_live_trading`),`allow_auto_trading`=VALUES(`allow_auto_trading`),
 `allow_mobile_api`=1,`capabilities`=VALUES(`capabilities`),`is_active`=1,`sort_order`=30,
 `is_trial`=0,`is_public`=1,`request_enabled`=0,`requires_payment`=0,`access_days`=7,
 `badge`=VALUES(`badge`),`is_featured`=0,`price_points`=380,`best_signal_cost_points`=5,
 `allow_points_activation`=1,`updated_at`=NOW();

INSERT INTO `pulse_plans`
(`name`,`slug`,`description`,`monthly_price`,`currency`,`minimum_signal_score`,
 `manual_trades_per_day`,`auto_trades_per_day`,`max_open_trades`,`max_selected_pairs`,`pair_access_mode`,
 `allow_testnet_trading`,`allow_manual_trading`,`allow_live_trading`,`allow_auto_trading`,`allow_mobile_api`,
 `capabilities`,`is_active`,`sort_order`,`is_trial`,`is_public`,`request_enabled`,`requires_payment`,
 `access_days`,`badge`,`is_featured`,`price_points`,`best_signal_cost_points`,`allow_points_activation`,`created_at`,`updated_at`)
SELECT
 'Spark Professional','pulse-professional','Thirty days of maximum Pulse coverage, the lowest Best Signal Spark cost and permission-ready trading workflows.',
 0,'SPARKS',70,50,25,5,500,'all',1,1,1,1,1,
 '{"scanner":true,"signals":true,"orders":true,"trades":true,"reports":true,"binance":true,"alerts":true,"plan_view":true,"settings":true,"mobile_api":true,"testnet_trading":true,"manual_trading":true,"live_trading":true,"auto_trading":true}',
 1,40,0,1,0,0,30,'Best Value',1,1200,3,1,NOW(),NOW()
WHERE @abs_v1503_apply=1
ON DUPLICATE KEY UPDATE
 `name`=VALUES(`name`),`description`=VALUES(`description`),`monthly_price`=0,`currency`='SPARKS',
 `minimum_signal_score`=VALUES(`minimum_signal_score`),`manual_trades_per_day`=VALUES(`manual_trades_per_day`),
 `auto_trades_per_day`=VALUES(`auto_trades_per_day`),`max_open_trades`=VALUES(`max_open_trades`),
 `max_selected_pairs`=VALUES(`max_selected_pairs`),`pair_access_mode`='all',
 `allow_testnet_trading`=VALUES(`allow_testnet_trading`),`allow_manual_trading`=VALUES(`allow_manual_trading`),
 `allow_live_trading`=VALUES(`allow_live_trading`),`allow_auto_trading`=VALUES(`allow_auto_trading`),
 `allow_mobile_api`=1,`capabilities`=VALUES(`capabilities`),`is_active`=1,`sort_order`=40,
 `is_trial`=0,`is_public`=1,`request_enabled`=0,`requires_payment`=0,`access_days`=30,
 `badge`=VALUES(`badge`),`is_featured`=1,`price_points`=1200,`best_signal_cost_points`=3,
 `allow_points_activation`=1,`updated_at`=NOW();

UPDATE `pulse_plans`
SET `is_public`=0,`allow_points_activation`=0,`request_enabled`=0,`requires_payment`=0,`updated_at`=NOW()
WHERE `slug`='pulse-intelligence' AND @abs_v1503_apply=1;

UPDATE `pulse_point_packs` SET `name`='Spark Starter',`description`='A simple Pulse Sparks wallet top-up.',`updated_at`=NOW()
WHERE `slug`='pulse-starter' AND @abs_v1503_apply=1;
UPDATE `pulse_point_packs` SET `name`='Spark Boost',`description`='A flexible Pulse Sparks bundle with bonus Sparks.',`updated_at`=NOW()
WHERE `slug`='pulse-plus' AND @abs_v1503_apply=1;
UPDATE `pulse_point_packs` SET `name`='Spark Vault',`description`='The best-value Pulse Sparks bundle with extra bonus Sparks.',`updated_at`=NOW()
WHERE `slug`='pulse-pro' AND @abs_v1503_apply=1;

UPDATE `pulse_system_settings` SET `description`='Charge the active package Best Signal Spark cost only when a qualifying new signal is unlocked.'
WHERE `key`='best_signal_points_enabled' AND @abs_v1503_apply=1;
UPDATE `pulse_system_settings` SET `description`='Allow authenticated users to submit Admin-verified USDT purchases for Pulse Sparks bundles.'
WHERE `key`='point_purchases_enabled' AND @abs_v1503_apply=1;
UPDATE `pulse_system_settings` SET `description`='Pulse Sparks awarded for one daily check-in.'
WHERE `key`='daily_checkin_points' AND @abs_v1503_apply=1;
UPDATE `pulse_system_settings` SET `description`='Pulse Sparks awarded once per qualifying signal share.'
WHERE `key`='social_share_points' AND @abs_v1503_apply=1;
UPDATE `pulse_system_settings` SET `description`='Pulse Sparks awarded for one provider-verified rewarded ad.'
WHERE `key`='rewarded_ad_points' AND @abs_v1503_apply=1;
UPDATE `pulse_system_settings` SET `description`='Optional Spark cost for generating a signal explanation.'
WHERE `key`='openai_signal_explanation_points' AND @abs_v1503_apply=1;

INSERT INTO `pulse_plan_strategies`
(`pulse_plan_id`,`pulse_strategy_id`,`is_enabled`,`weight_override`,`created_at`,`updated_at`)
SELECT p.`id`,s.`id`,1,NULL,NOW(),NOW()
FROM `pulse_plans` p
CROSS JOIN `pulse_strategies` s
WHERE p.`slug` IN ('spark-day-pass','spark-flex','spark-momentum','pulse-professional')
  AND s.`is_enabled`=1
ON DUPLICATE KEY UPDATE `is_enabled`=1,`updated_at`=NOW();

UPDATE `pulse_system_settings`
SET `value`='1',`type`='boolean',`group`='system',
    `description`='Internal marker: ABS V15.0.3 Pulse Sparks packages were initialized once.'
WHERE `key`='v1503_spark_packages_seeded' AND @abs_v1503_apply=1;

SELECT IF(@abs_v1503_apply=1,
  'ABS V15.0.3 Pulse Sparks packages installed.',
  'ABS V15.0.3 Pulse Sparks packages already initialized; no Admin settings were overwritten.'
) AS result;

