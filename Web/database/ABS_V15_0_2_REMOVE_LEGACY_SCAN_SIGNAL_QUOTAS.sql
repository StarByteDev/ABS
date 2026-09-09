-- ABS V15.0.2 — remove obsolete daily scanner/signal plan quotas.
-- This cleanup only removes the two legacy V14 plan-limit columns.
-- V15.0.2 access is governed by Pulse entitlement + Pulse Points.

SET @abs_drop_scan_quota = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'pulse_plans'
        AND COLUMN_NAME = 'scanner_runs_per_day'
    ),
    'ALTER TABLE `pulse_plans` DROP COLUMN `scanner_runs_per_day`',
    'SELECT ''scanner_runs_per_day already absent'' AS ABS_V15_0_2'
  )
);
PREPARE abs_stmt FROM @abs_drop_scan_quota;
EXECUTE abs_stmt;
DEALLOCATE PREPARE abs_stmt;

SET @abs_drop_signal_quota = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'pulse_plans'
        AND COLUMN_NAME = 'signals_per_day'
    ),
    'ALTER TABLE `pulse_plans` DROP COLUMN `signals_per_day`',
    'SELECT ''signals_per_day already absent'' AS ABS_V15_0_2'
  )
);
PREPARE abs_stmt FROM @abs_drop_signal_quota;
EXECUTE abs_stmt;
DEALLOCATE PREPARE abs_stmt;

SELECT 'ABS V15.0.2 legacy scanner/signal daily quota cleanup complete.' AS result;
