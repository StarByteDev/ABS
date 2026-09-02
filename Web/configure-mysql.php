<?php

/**
 * ABS V14.6.1 local MySQL configurator.
 *
 * - Creates .env from .env.example when needed.
 * - Switches the runtime connection to MySQL without exposing credentials.
 * - Preserves existing MySQL host/database/user/password values when present.
 * - Creates the configured database when the MySQL account has CREATE privilege.
 * - On shared hosting, an already-created database is accepted even when CREATE is denied.
 */

$root = __DIR__;
$envPath = $root.DIRECTORY_SEPARATOR.'.env';
$examplePath = $root.DIRECTORY_SEPARATOR.'.env.example';

if (! extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "ERROR: PHP extension pdo_mysql is not enabled.\n");
    exit(1);
}

if (! file_exists($envPath)) {
    if (! copy($examplePath, $envPath)) {
        fwrite(STDERR, "ERROR: Unable to create .env from .env.example.\n");
        exit(1);
    }
    echo "Created .env from .env.example\n";
}

$content = (string) file_get_contents($envPath);
if ($content === '') {
    fwrite(STDERR, "ERROR: Unable to read .env.\n");
    exit(1);
}

function envValue(string $content, string $key, ?string $default = null): ?string
{
    if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $m)) {
        return $default;
    }
    $value = trim($m[1]);
    if ($value === '') return '';
    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
        return substr($value, 1, -1);
    }
    return $value;
}

function setEnvValue(string $content, string $key, string $value): string
{
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, $line, $content, 1) ?? $content;
    }
    return rtrim($content).PHP_EOL.$line.PHP_EOL;
}

$currentConnection = strtolower((string) envValue($content, 'DB_CONNECTION', ''));
if ($currentConnection !== '' && $currentConnection !== 'mysql') {
    $backup = $root.DIRECTORY_SEPARATOR.'.env.pre-mysql-'.date('Ymd-His');
    if (@copy($envPath, $backup)) {
        echo 'Backed up previous environment to '.basename($backup).PHP_EOL;
    }
}

$host = (string) envValue($content, 'DB_HOST', '127.0.0.1');
$port = (string) envValue($content, 'DB_PORT', '3306');
$database = (string) envValue($content, 'DB_DATABASE', 'abs');
$username = (string) envValue($content, 'DB_USERNAME', 'root');
$password = (string) envValue($content, 'DB_PASSWORD', '');
$charset = (string) envValue($content, 'DB_CHARSET', 'utf8mb4');
$collation = (string) envValue($content, 'DB_COLLATION', 'utf8mb4_unicode_ci');

// Values inherited from an older SQLite .env should use the MySQL local defaults.
if ($database === '' || str_ends_with(strtolower($database), '.sqlite') || str_contains(strtolower($database), 'database/database.sqlite')) {
    $database = 'abs';
}
if ($host === '') $host = '127.0.0.1';
if ($port === '') $port = '3306';
if ($username === '') $username = 'root';

$content = setEnvValue($content, 'DB_CONNECTION', 'mysql');
$content = setEnvValue($content, 'DB_HOST', $host);
$content = setEnvValue($content, 'DB_PORT', $port);
$content = setEnvValue($content, 'DB_DATABASE', $database);
$content = setEnvValue($content, 'DB_USERNAME', $username);
if (! preg_match('/^DB_PASSWORD=/m', $content)) {
    $content = setEnvValue($content, 'DB_PASSWORD', '');
}
$content = setEnvValue($content, 'DB_CHARSET', $charset);
$content = setEnvValue($content, 'DB_COLLATION', $collation);
$content = setEnvValue($content, 'CACHE_PREFIX', 'abs_v14_6_1_mysql');

if (file_put_contents($envPath, $content) === false) {
    fwrite(STDERR, "ERROR: Unable to update .env.\n");
    exit(1);
}

$serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
$dbDsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5,
];

try {
    $pdo = new PDO($serverDsn, $username, $password, $options);
    $quotedDatabase = '`'.str_replace('`', '``', $database).'`';
    $safeCharset = preg_replace('/[^a-zA-Z0-9_]/', '', $charset) ?: 'utf8mb4';
    $safeCollation = preg_replace('/[^a-zA-Z0-9_]/', '', $collation) ?: 'utf8mb4_unicode_ci';
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDatabase} CHARACTER SET {$safeCharset} COLLATE {$safeCollation}");
        echo "MySQL database ready: {$database}\n";
    } catch (Throwable $createError) {
        // Shared-hosting users often cannot CREATE DATABASE from PHP, but can use a database created in cPanel.
        $existing = new PDO($dbDsn, $username, $password, $options);
        echo "MySQL database already exists and is accessible: {$database}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: Unable to connect to MySQL with the values currently in .env.\n");
    fwrite(STDERR, "Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD.\n");
    fwrite(STDERR, "MySQL said: ".$e->getMessage()."\n");
    exit(1);
}

echo "MySQL runtime configured successfully.\n";
