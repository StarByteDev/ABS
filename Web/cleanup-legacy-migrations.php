<?php

/**
 * ABS V14.6.1 local migration cleanup.
 *
 * This standalone helper is intentionally framework-free so it can run before
 * Composer/Laravel bootstraps. It fixes the specific failure caused by copying
 * a newer ABS build over an older project folder and leaving a second legacy
 * create-users migration behind.
 *
 * It never deletes migration files. Any conflicting legacy create-users
 * migration is moved to database/migrations_legacy_disabled/ so it can be
 * reviewed or restored manually if required.
 */

$root = __DIR__;
$migrationsDir = $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
$quarantineDir = $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations_legacy_disabled';
$canonicalUsers = '0001_01_01_000000_create_users_table.php';

if (! is_dir($migrationsDir)) {
    fwrite(STDERR, "ERROR: database/migrations was not found.\n");
    exit(1);
}

$files = glob($migrationsDir.DIRECTORY_SEPARATOR.'*.php') ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$usersCreators = [];
foreach ($files as $file) {
    $source = (string) @file_get_contents($file);
    if ($source !== '' && preg_match("/Schema::create\\s*\\(\\s*['\"]users['\"]/", $source)) {
        $usersCreators[] = $file;
    }
}

if (count($usersCreators) <= 1) {
    echo "Migration cleanup: no duplicate create-users migration detected.\n";
    exit(0);
}

$canonicalPath = $migrationsDir.DIRECTORY_SEPARATOR.$canonicalUsers;
if (! is_file($canonicalPath)) {
    fwrite(STDERR, "ERROR: Canonical V14.6 users migration is missing: {$canonicalUsers}\n");
    fwrite(STDERR, "No files were changed. Extract a clean V14.6.1 build and try again.\n");
    exit(1);
}

if (! is_dir($quarantineDir) && ! mkdir($quarantineDir, 0775, true) && ! is_dir($quarantineDir)) {
    fwrite(STDERR, "ERROR: Unable to create migration quarantine directory.\n");
    exit(1);
}

$moved = [];
foreach ($usersCreators as $file) {
    if (realpath($file) === realpath($canonicalPath)) {
        continue;
    }

    $name = basename($file);
    $target = $quarantineDir.DIRECTORY_SEPARATOR.$name.'.disabled';
    if (file_exists($target)) {
        $target .= '.'.date('YmdHis');
    }

    if (! @rename($file, $target)) {
        fwrite(STDERR, "ERROR: Unable to quarantine conflicting migration: {$name}\n");
        exit(1);
    }

    $moved[] = $name;
}

if ($moved === []) {
    echo "Migration cleanup: duplicate scan completed; nothing needed moving.\n";
    exit(0);
}

echo "Migration cleanup: quarantined ".count($moved)." conflicting legacy create-users migration(s):\n";
foreach ($moved as $name) {
    echo "  - {$name}\n";
}
echo "Original files are preserved under database/migrations_legacy_disabled/.\n";
exit(0);
