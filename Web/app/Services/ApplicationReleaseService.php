<?php

namespace App\Services;

use App\Support\AbsSchemaRepair;
use App\Support\LegacyMigrationBaseline;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ApplicationReleaseService
{
    public const RESTORE_FORMAT = 1;

    public function __construct(private readonly ApplicationBackupService $backups) {}

    public function packageDirectory(): string
    {
        $path = storage_path('app/abs-release-packages');
        File::ensureDirectoryExists($path);
        return $path;
    }

    public function restorePointDirectory(): string
    {
        $path = storage_path('app/abs-restore-points');
        File::ensureDirectoryExists($path);
        return $path;
    }

    public function listPackages(): array
    {
        return $this->listArchives($this->packageDirectory(), 'ABS_UPDATE_');
    }

    public function listRestorePoints(): array
    {
        return $this->listArchives($this->restorePointDirectory(), 'ABS_RESTORE_POINT_');
    }

    public function stageUploadedPackage(string $sourcePath, string $originalName): array
    {
        $this->assertZipAvailable();
        if (! File::isFile($sourcePath)) throw new RuntimeException('Uploaded release package was not found.');

        $inspection = $this->inspectPackage($sourcePath);
        $version = preg_replace('/[^A-Za-z0-9._-]+/', '_', $inspection['version']);
        $stamp = now()->format('Ymd_His');
        $filename = "ABS_UPDATE_{$version}_{$stamp}_".Str::lower(Str::random(5)).'.zip';
        $destination = $this->packageDirectory().DIRECTORY_SEPARATOR.$filename;
        if (! File::copy($sourcePath, $destination)) throw new RuntimeException('Unable to store the uploaded release package.');

        return array_merge($inspection, [
            'name' => $filename,
            'original_name' => basename($originalName),
            'path' => $destination,
            'size' => File::size($destination),
            'sha256' => hash_file('sha256', $destination),
        ]);
    }

    public function inspectPackage(string $path): array
    {
        $this->assertZipAvailable();
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('The package is not a readable ZIP archive.');

        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                $this->assertSafeEntry($entry);
                $entries[$entry] = true;
            }

            foreach (['artisan','composer.json','BUILD_VERSION.txt','app/','routes/','resources/','database/migrations/'] as $required) {
                $found = isset($entries[$required]) || collect(array_keys($entries))->contains(fn ($entry) => str_starts_with($entry, $required));
                if (! $found) throw new RuntimeException("Release package is missing required application content: {$required}");
            }

            $version = trim((string) $zip->getFromName('BUILD_VERSION.txt'));
            if ($version === '') throw new RuntimeException('BUILD_VERSION.txt is empty.');

            return [
                'version' => $version,
                'entries' => $zip->numFiles,
                'has_vendor' => collect(array_keys($entries))->contains(fn ($entry) => str_starts_with($entry, 'vendor/')),
                'has_migrations' => collect(array_keys($entries))->contains(fn ($entry) => str_starts_with($entry, 'database/migrations/')),
            ];
        } finally {
            $zip->close();
        }
    }

    public function createRestorePoint(string $reason = 'manual'): array
    {
        $this->assertZipAvailable();
        $state = $this->backups->create(true);
        $stamp = now()->format('Ymd_His');
        $token = Str::lower(Str::random(6));
        $currentVersion = $this->applicationVersion();
        $safeVersion = preg_replace('/[^A-Za-z0-9._-]+/', '_', $currentVersion);
        $filename = "ABS_RESTORE_POINT_{$safeVersion}_{$stamp}_{$token}.zip";
        $target = $this->restorePointDirectory().DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the application restore point.');
        }

        try {
            $manifest = [
                'product' => 'Alpha Block Solutions',
                'restore_format' => self::RESTORE_FORMAT,
                'created_at' => now()->toIso8601String(),
                'app_version' => $currentVersion,
                'reason' => $reason,
                'state_backup_name' => basename($state['path']),
                'state_backup_sha256' => $state['sha256'],
                'environment_preserved' => true,
            ];
            $zip->addFromString('restore-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->addFile($state['path'], 'state-backup.zip');

            foreach ($this->applicationFiles() as [$absolute, $relative]) {
                $zip->addFile($absolute, 'application/'.$relative);
            }
        } finally {
            $zip->close();
        }

        return [
            'name' => $filename,
            'path' => $target,
            'version' => $currentVersion,
            'size' => File::size($target),
            'sha256' => hash_file('sha256', $target),
        ];
    }

    public function installStagedPackage(string $name): array
    {
        $path = $this->resolveArchive($this->packageDirectory(), $name, 'ABS_UPDATE_');
        $inspection = $this->inspectPackage($path);
        $restorePoint = $this->createRestorePoint('automatic pre-upgrade backup for '.$inspection['version']);
        $work = storage_path('app/abs-release-packages/.install-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5)));
        File::ensureDirectoryExists($work);

        try {
            $this->extractSafe($path, $work);
            $this->synchronizeApplication($work);

            // V14.8.4+: every Admin package install reconciles the schema before
            // normal migrations. This repairs columns that may be missing even
            // when an old migration was already marked as applied.
            AbsSchemaRepair::repair();
            $baseline = LegacyMigrationBaseline::synchronize();
            $this->runArtisanOrFail('migrate', ['--force' => true]);
            $this->runArtisanOrFail('db:seed', ['--force' => true]);
            $this->runArtisanOrFail('optimize:clear');

            $diagnosis = AbsSchemaRepair::diagnose();
            if (! ($diagnosis['ready'] ?? false)) {
                $missing = [];
                if (! empty($diagnosis['missing_tables'])) $missing[] = 'tables: '.implode(', ', $diagnosis['missing_tables']);
                foreach (($diagnosis['missing_columns'] ?? []) as $table => $columns) $missing[] = $table.': '.implode(', ', $columns);
                throw new RuntimeException('Release installed but database verification still found missing required schema'.($missing ? ' ('.implode('; ', $missing).')' : '').'.');
            }

            return [
                'installed_version' => $inspection['version'],
                'restore_point' => $restorePoint,
                'schema_repaired' => true,
                'legacy_migrations_baselined' => count($baseline['baselined'] ?? []),
                'migrations_ran' => true,
                'baseline_content_synchronized' => true,
                'schema_verified' => true,
                'cache_cleared' => true,
            ];
        } catch (Throwable $e) {
            try {
                $this->restoreRestorePoint($restorePoint['name'], false);
            } catch (Throwable $rollbackError) {
                throw new RuntimeException('Upgrade failed and automatic rollback also failed. Upgrade error: '.$e->getMessage().' Rollback error: '.$rollbackError->getMessage(), previous: $e);
            }
            throw new RuntimeException('Upgrade failed. ABS automatically restored the pre-upgrade application and database. '.$e->getMessage(), previous: $e);
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function restoreRestorePoint(string $name, bool $createSafetyPoint = true): array
    {
        $path = $this->resolveArchive($this->restorePointDirectory(), $name, 'ABS_RESTORE_POINT_');
        $work = storage_path('app/abs-restore-points/.restore-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5)));
        File::ensureDirectoryExists($work);

        try {
            $this->extractSafe($path, $work);
            $manifestPath = $work.'/restore-manifest.json';
            if (! File::isFile($manifestPath) || ! File::isFile($work.'/state-backup.zip') || ! File::isDirectory($work.'/application')) {
                throw new RuntimeException('This is not a complete ABS application restore point.');
            }
            $manifest = json_decode((string) File::get($manifestPath), true);
            if (($manifest['product'] ?? null) !== 'Alpha Block Solutions' || (int) ($manifest['restore_format'] ?? 0) !== self::RESTORE_FORMAT) {
                throw new RuntimeException('Unsupported application restore-point format.');
            }

            $safetyPoint = $createSafetyPoint ? $this->createRestorePoint('automatic safety point before rollback') : null;
            $this->synchronizeApplication($work.'/application');
            $state = $this->backups->restore($work.'/state-backup.zip', true, false, true);
            Artisan::call('optimize:clear');

            return [
                'restored_version' => $manifest['app_version'] ?? 'unknown',
                'safety_point' => $safetyPoint,
                'state' => $state,
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function deletePackage(string $name): void
    {
        File::delete($this->resolveArchive($this->packageDirectory(), $name, 'ABS_UPDATE_'));
    }

    public function deleteRestorePoint(string $name): void
    {
        File::delete($this->resolveArchive($this->restorePointDirectory(), $name, 'ABS_RESTORE_POINT_'));
    }

    public function resolvePackage(string $name): string
    {
        return $this->resolveArchive($this->packageDirectory(), $name, 'ABS_UPDATE_');
    }

    public function resolveRestorePoint(string $name): string
    {
        return $this->resolveArchive($this->restorePointDirectory(), $name, 'ABS_RESTORE_POINT_');
    }

    private function applicationFiles(): array
    {
        $root = base_path();
        $include = ['app','bootstrap','config','database','public','resources','routes','scripts','docs','tests'];
        $files = [];
        foreach ($include as $directory) {
            $dir = $root.DIRECTORY_SEPARATOR.$directory;
            if (! File::isDirectory($dir)) continue;
            foreach (File::allFiles($dir) as $file) {
                $relative = str_replace('\\', '/', $directory.'/'.$file->getRelativePathname());
                if ($this->excludedRelativePath($relative)) continue;
                $files[] = [$file->getPathname(), $relative];
            }
        }
        foreach (['.env.example','.env.mysql.example','.env.production.example','.gitignore','artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','phpunit.xml','BUILD_VERSION.txt','README.md','CHANGELOG.md','README_RECOVERY_V4_IMPORTANT.txt','REUSABLE_SLIM_BUILD.txt','RUN-ABS-LARAGON.bat','cleanup-legacy-migrations.php','configure-mysql.php','repair-existing-database.bat','repair-existing-database.sh','setup-local.bat','setup-local.sh','switch-to-mysql.bat','switch-to-mysql.sh'] as $file) {
            $absolute = $root.DIRECTORY_SEPARATOR.$file;
            if (File::isFile($absolute)) $files[] = [$absolute, $file];
        }
        return $files;
    }

    private function synchronizeApplication(string $sourceRoot): void
    {
        $sourceRoot = rtrim($sourceRoot, DIRECTORY_SEPARATOR);
        $sourceFiles = [];
        foreach (File::allFiles($sourceRoot) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ($this->excludedRelativePath($relative)) continue;
            $sourceFiles[$relative] = $file->getPathname();
        }

        // Remove obsolete files only from ABS-managed application locations.
        // Runtime state (.env, storage, vendor, bootstrap/cache and public/storage)
        // remains untouched by an update or rollback.
        $managedDirectories = ['app','bootstrap','config','database','public','resources','routes','scripts','docs','tests'];
        foreach ($managedDirectories as $directory) {
            $current = base_path($directory);
            if (! File::isDirectory($current)) continue;
            foreach (File::allFiles($current) as $file) {
                $relative = str_replace('\\', '/', $directory.'/'.$file->getRelativePathname());
                if ($this->excludedRelativePath($relative)) continue;
                if (! array_key_exists($relative, $sourceFiles)) File::delete($file->getPathname());
            }
        }

        $managedRootFiles = ['.env.example','.env.mysql.example','.env.production.example','.gitignore','artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','phpunit.xml','BUILD_VERSION.txt','README.md','CHANGELOG.md','README_RECOVERY_V4_IMPORTANT.txt','REUSABLE_SLIM_BUILD.txt','RUN-ABS-LARAGON.bat','cleanup-legacy-migrations.php','configure-mysql.php','repair-existing-database.bat','repair-existing-database.sh','setup-local.bat','setup-local.sh','switch-to-mysql.bat','switch-to-mysql.sh'];
        foreach ($managedRootFiles as $relative) {
            $current = base_path($relative);
            if (File::isFile($current) && ! array_key_exists($relative, $sourceFiles)) File::delete($current);
        }

        foreach ($sourceFiles as $relative => $source) {
            $destination = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
            File::ensureDirectoryExists(dirname($destination));
            if (! File::copy($source, $destination)) {
                throw new RuntimeException('Unable to deploy application file: '.$relative);
            }
        }
    }

    private function extractSafe(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) throw new RuntimeException('Unable to open ZIP archive.');
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) $this->assertSafeEntry((string) $zip->getNameIndex($i));
            if (! $zip->extractTo($destination)) throw new RuntimeException('Unable to extract ZIP archive.');
        } finally {
            $zip->close();
        }
    }

    private function assertSafeEntry(string $entry): void
    {
        $normalized = str_replace('\\', '/', $entry);
        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new RuntimeException('Unsafe path detected inside ZIP archive.');
        }
        if (preg_match('#(^|/)(\.env|storage|vendor|node_modules|\.git)(/|$)#i', $normalized)) {
            throw new RuntimeException('Release archives may not replace .env, storage, vendor, node_modules or .git content.');
        }
    }

    private function excludedRelativePath(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        return str_starts_with($relative, 'bootstrap/cache/')
            || str_starts_with($relative, 'public/storage/')
            || str_starts_with($relative, 'public/hot');
    }

    private function resolveArchive(string $directory, string $name, string $prefix): string
    {
        $name = basename($name);
        if (! str_starts_with($name, $prefix) || ! Str::endsWith(strtolower($name), '.zip')) throw new RuntimeException('Invalid archive filename.');
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        if (! File::isFile($path)) throw new RuntimeException('Archive not found.');
        return $path;
    }

    private function listArchives(string $directory, string $prefix): array
    {
        return collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), $prefix) && Str::endsWith(strtolower($file->getFilename()), '.zip'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                'sha256' => hash_file('sha256', $file->getPathname()),
            ])->values()->all();
    }

    private function runArtisanOrFail(string $command, array $parameters = []): void
    {
        $exitCode = Artisan::call($command, $parameters);
        if ($exitCode !== 0) {
            $output = trim((string) Artisan::output());
            throw new RuntimeException('Release command failed: php artisan '.$command.($output !== '' ? ' — '.Str::limit($output, 1200) : ''));
        }
    }

    private function applicationVersion(): string
    {
        $path = base_path('BUILD_VERSION.txt');
        return File::isFile($path) ? trim((string) File::get($path)) : (string) config('app.version', 'unknown');
    }

    private function assertZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required for ABS release management.');
    }
}
