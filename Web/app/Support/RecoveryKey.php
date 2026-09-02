<?php

namespace App\Support;

final class RecoveryKey
{
    /**
     * Return the configured recovery key without ever exposing it to a response.
     *
     * Shared-hosting users may not have Terminal access to clear a previously
     * cached Laravel configuration file. Prefer config(), but safely fall back
     * to reading ABS_RECOVERY_KEY directly from the private project .env file so
     * database self-repair remains usable after an upgrade.
     */
    public static function value(): string
    {
        $configured = trim((string) config('app.recovery_key'));
        if ($configured !== '') {
            return $configured;
        }

        $path = base_path('.env');
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            return '';
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_starts_with($line, 'ABS_RECOVERY_KEY=')) {
                continue;
            }

            $value = trim(substr($line, strlen('ABS_RECOVERY_KEY=')));
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            return trim($value);
        }

        return '';
    }

    public static function enabled(): bool
    {
        return self::value() !== '';
    }
}
