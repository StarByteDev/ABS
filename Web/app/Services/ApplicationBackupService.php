<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ApplicationBackupService
{
    public const FORMAT_VERSION = 1;

    public function backupDirectory(): string
    {
        $path = storage_path('app/abs-backups');
        File::ensureDirectoryExists($path);
        return $path;
    }

    public function listBackups(): array
    {
        $files = collect(File::files($this->backupDirectory()))
            ->filter(fn ($file) => Str::endsWith(strtolower($file->getFilename()), '.zip'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    'sha256' => hash_file('sha256', $file->getPathname()),
                ];
            })->values()->all();

        return $files;
    }

    public function create(bool $includeUploads = true): array
    {
        $this->assertZipAvailable();
        $stamp = now()->format('Ymd_His');
        $token = Str::lower(Str::random(6));
        $filename = "ABS_BACKUP_{$stamp}_{$token}.zip";
        $zipPath = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;
        $work = storage_path('app/abs-backups/.tmp-'.$stamp.'-'.$token);
        File::ensureDirectoryExists($work);

        try {
            $sqlPath = $work.DIRECTORY_SEPARATOR.'database.sql';
            $this->exportDatabase($sqlPath);

            $config = $this->safeConfigurationSnapshot();
            File::put($work.DIRECTORY_SEPARATOR.'configuration.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $manifest = [
                'product' => 'Alpha Block Solutions',
                'backup_format' => self::FORMAT_VERSION,
                'created_at' => now()->toIso8601String(),
                'app_version' => $this->applicationVersion(),
                'database_driver' => config('database.default'),
                'app_key_fingerprint' => $this->appKeyFingerprint(),
                'includes' => [
                    'database' => true,
                    'uploads' => $includeUploads,
                    'safe_configuration_snapshot' => true,
                    'environment_secrets' => false,
                ],
                'checksums' => [
                    'database.sql' => hash_file('sha256', $sqlPath),
                    'configuration.json' => hash_file('sha256', $work.DIRECTORY_SEPARATOR.'configuration.json'),
                ],
            ];
            File::put($work.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the backup archive.');
            }
            $zip->addFile($work.DIRECTORY_SEPARATOR.'manifest.json', 'manifest.json');
            $zip->addFile($sqlPath, 'database.sql');
            $zip->addFile($work.DIRECTORY_SEPARATOR.'configuration.json', 'configuration.json');

            if ($includeUploads) {
                $uploadRoot = storage_path('app/public');
                if (File::isDirectory($uploadRoot)) {
                    foreach (File::allFiles($uploadRoot) as $file) {
                        $relative = str_replace('\\', '/', $file->getRelativePathname());
                        $zip->addFile($file->getPathname(), 'uploads/'.$relative);
                    }
                }
            }
            $zip->close();

            return [
                'name' => $filename,
                'path' => $zipPath,
                'size' => File::size($zipPath),
                'sha256' => hash_file('sha256', $zipPath),
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function restore(string $archivePath, bool $restoreUploads = true, bool $runMigrations = true, bool $replaceUploads = false): array
    {
        $this->assertZipAvailable();
        $work = storage_path('app/abs-backups/.restore-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)));
        File::ensureDirectoryExists($work);

        try {
            $zip = new ZipArchive();
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('The selected backup archive cannot be opened.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                $normalized = str_replace('\\', '/', $entry);
                if (str_starts_with($normalized, '/') || preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
                    $zip->close();
                    throw new RuntimeException('Unsafe path detected inside the backup archive.');
                }
            }
            if ($zip->locateName('manifest.json') === false || $zip->locateName('database.sql') === false) {
                $zip->close();
                throw new RuntimeException('This is not a valid ABS backup archive.');
            }
            $zip->extractTo($work);
            $zip->close();

            $manifest = json_decode((string) File::get($work.'/manifest.json'), true);
            if (($manifest['product'] ?? null) !== 'Alpha Block Solutions' || (int) ($manifest['backup_format'] ?? 0) !== self::FORMAT_VERSION) {
                throw new RuntimeException('Unsupported or invalid ABS backup format.');
            }
            $backupKeyFingerprint = (string) ($manifest['app_key_fingerprint'] ?? '');
            if ($backupKeyFingerprint !== '' && ! hash_equals($backupKeyFingerprint, $this->appKeyFingerprint())) {
                throw new RuntimeException('APP_KEY mismatch. Set HostGator APP_KEY to the same production APP_KEY used by the source ABS installation before restoring, otherwise encrypted database values such as protected exchange credentials cannot be decrypted.');
            }
            $expected = $manifest['checksums']['database.sql'] ?? null;
            if ($expected && ! hash_equals($expected, hash_file('sha256', $work.'/database.sql'))) {
                throw new RuntimeException('Database backup checksum validation failed. The archive may be damaged.');
            }

            $this->importDatabase($work.'/database.sql');

            $uploadsRestored = 0;
            if ($restoreUploads && File::isDirectory($work.'/uploads')) {
                if ($replaceUploads) {
                    File::deleteDirectory(storage_path('app/public'));
                    File::ensureDirectoryExists(storage_path('app/public'));
                }
                foreach (File::allFiles($work.'/uploads') as $file) {
                    $relative = $file->getRelativePathname();
                    $destination = storage_path('app/public/'.$relative);
                    File::ensureDirectoryExists(dirname($destination));
                    File::copy($file->getPathname(), $destination);
                    $uploadsRestored++;
                }
            }

            if ($runMigrations) {
                Artisan::call('migrate', ['--force' => true]);
            }

            return [
                'manifest' => $manifest,
                'uploads_restored' => $uploadsRestored,
                'migrations_ran' => $runMigrations,
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function resolveBackup(string $name): string
    {
        $name = basename($name);
        if (! preg_match('/^ABS_BACKUP_[A-Za-z0-9_.-]+\.zip$/', $name)) {
            throw new RuntimeException('Invalid backup filename.');
        }
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$name;
        if (! File::isFile($path)) {
            throw new RuntimeException('Backup file not found.');
        }
        return $path;
    }

    private function exportDatabase(string $target): void
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('ABS production backup currently supports MySQL/MariaDB databases.');
        }

        $handle = fopen($target, 'wb');
        if (! $handle) throw new RuntimeException('Unable to create database backup file.');

        fwrite($handle, "-- Alpha Block Solutions application database backup\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET NAMES utf8mb4;\n");

        $database = DB::getDatabaseName();
        $tables = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$database, 'BASE TABLE']);
        $pdo = DB::connection()->getPdo();
        DB::beginTransaction();

        try {
        foreach ($tables as $tableRow) {
            $table = $tableRow->TABLE_NAME;
            $quotedTable = '`'.str_replace('`', '``', $table).'`';
            $create = DB::select("SHOW CREATE TABLE {$quotedTable}");
            $createArray = (array) ($create[0] ?? []);
            $createSql = $createArray['Create Table'] ?? array_values($createArray)[1] ?? null;
            if (! $createSql) continue;
            $createSql = str_replace(["\r", "\n"], ' ', $createSql);
            fwrite($handle, "\nDROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n");

            $columns = collect(DB::select("SHOW COLUMNS FROM {$quotedTable}"))
                ->reject(fn ($column) => str_contains(strtoupper((string) ($column->Extra ?? '')), 'GENERATED'))
                ->pluck('Field')->all();
            if (! $columns) continue;
            $columnSql = implode(',', array_map(fn ($col) => '`'.str_replace('`', '``', $col).'`', $columns));

            foreach (DB::table($table)->cursor() as $row) {
                $row = (array) $row;
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    if ($value === null) $values[] = 'NULL';
                    elseif (is_bool($value)) $values[] = $value ? '1' : '0';
                    else {
                        $quoted = $pdo->quote((string) $value);
                        $quoted = str_replace(["\r", "\n", "\t"], ['\\r', '\\n', '\\t'], $quoted);
                        $values[] = $quoted;
                    }
                }
                fwrite($handle, "INSERT INTO {$quotedTable} ({$columnSql}) VALUES (".implode(',', $values).");\n");
            }
        }
        } finally {
            DB::rollBack();
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    private function importDatabase(string $sqlPath): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $handle = fopen($sqlPath, 'rb');
        if (! $handle) throw new RuntimeException('Unable to read database backup file.');

        try {
            while (($line = fgets($handle)) !== false) {
                $statement = trim($line);
                if ($statement === '' || Str::startsWith($statement, '--')) continue;
                if (Str::endsWith($statement, ';')) $statement = substr($statement, 0, -1);
                DB::unprepared($statement);
            }
        } finally {
            fclose($handle);
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function safeConfigurationSnapshot(): array
    {
        return [
            'note' => 'Safe reference only. Restore intentionally does not overwrite HostGator .env credentials, APP_KEY, database password, SMTP password, API secrets or exchange keys.',
            'app' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'runtime' => [
                'database_driver' => config('database.default'),
                'app_key_fingerprint' => $this->appKeyFingerprint(),
                'cache_store' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_connection' => config('queue.default'),
                'filesystem_disk' => config('filesystems.default'),
                'mail_mailer' => config('mail.default'),
            ],
        ];
    }

    private function applicationVersion(): string
    {
        $readme = base_path('README.md');
        if (File::isFile($readme) && preg_match('/ABS V(\d+\.\d+\.\d+)/i', File::get($readme), $match)) {
            return $match[1];
        }
        return '14.7.1';
    }

    private function appKeyFingerprint(): string
    {
        return substr(hash('sha256', (string) config('app.key')), 0, 16);
    }

    private function assertZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP extension is required for ABS backup and restore. Enable ext-zip in PHP first.');
        }
    }
}
