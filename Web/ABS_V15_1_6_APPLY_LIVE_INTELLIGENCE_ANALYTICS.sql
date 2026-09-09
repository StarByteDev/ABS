-- ABS V15.1.6 — Live Deployment Intelligence & Strategy Profitability columns
-- phpMyAdmin fallback for an existing V15.1.5 database.
-- Preferred method remains: php artisan migrate --force
-- This script is non-destructive and only adds missing analytics columns.

SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_signal_daily_metrics' AND column_name='model_trades')=0,
 'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `model_trades` INT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_signal_daily_metrics' AND column_name='model_net_r')=0,
 'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `model_net_r` DECIMAL(16,6) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_signal_daily_metrics' AND column_name='model_gross_profit_r')=0,
 'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `model_gross_profit_r` DECIMAL(16,6) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_signal_daily_metrics' AND column_name='model_gross_loss_r')=0,
 'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `model_gross_loss_r` DECIMAL(16,6) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_signal_daily_metrics' AND column_name='model_return_pct')=0,
 'ALTER TABLE `pulse_signal_daily_metrics` ADD COLUMN `model_return_pct` DECIMAL(18,8) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_strategy_daily_metrics' AND column_name='model_trades')=0,
 'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `model_trades` INT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_strategy_daily_metrics' AND column_name='model_net_r')=0,
 'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `model_net_r` DECIMAL(16,6) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_strategy_daily_metrics' AND column_name='model_gross_profit_r')=0,
 'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `model_gross_profit_r` DECIMAL(16,6) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_strategy_daily_metrics' AND column_name='model_gross_loss_r')=0,
 'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `model_gross_loss_r` DECIMAL(16,6) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='pulse_strategy_daily_metrics' AND column_name='model_return_pct')=0,
 'ALTER TABLE `pulse_strategy_daily_metrics` ADD COLUMN `model_return_pct` DECIMAL(18,8) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
