-- ABS V15.1.2 — Premium usability copy update (phpMyAdmin/shared-hosting fallback)
-- No schema changes. Existing Admin-customized rewarded-signal wording is preserved.
UPDATE pulse_system_settings SET value='FREE SIGNAL', updated_at=CURRENT_TIMESTAMP
WHERE `key`='public_rewarded_signal_badge' AND value='FREE SIGNAL · REWARDED ACCESS';
UPDATE pulse_system_settings SET value='Watch a short ad to unlock your Pulse signal.', updated_at=CURRENT_TIMESTAMP
WHERE `key`='public_rewarded_signal_title' AND value='Watch one ad. Unlock one qualified Pulse signal.';
UPDATE pulse_system_settings SET value='Your qualified signal appears immediately after the ad completes.', updated_at=CURRENT_TIMESTAMP
WHERE `key`='public_rewarded_signal_description' AND value='No registration required. Complete the rewarded ad and your signal appears for 30 seconds.';
UPDATE pulse_system_settings SET value='Watch Ad & Unlock Signal', updated_at=CURRENT_TIMESTAMP
WHERE `key`='public_rewarded_signal_cta' AND value='Watch Ad & Reveal Signal';
