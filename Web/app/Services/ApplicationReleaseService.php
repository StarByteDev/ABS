<?php

namespace App\Services;

use App\Support\AbsSchemaRepair;
use App\Support\LegacyMigrationBaseline;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ApplicationReleaseService
{
    public const RESTORE_FORMAT = 2;
    private const PACKAGE_PREFIX = 'ABS_UPDATE_';
    private const RESTORE_PREFIX = 'ABS_LAST_WORKING_BUILD_';

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
        return $this->listArchives($this->packageDirectory(), self::PACKAGE_PREFIX);
    }

    public function listRestorePoints(): array
    {
        $this->enforceSingleRestorePoint();
        return $this->listArchives($this->restorePointDirectory(), self::RESTORE_PREFIX);
    }

    public function latestRestorePoint(): ?array
    {
        return $this->listRestorePoints()[0] ?? null;
    }

    public function stageUploadedPackage(string $sourcePath, string $originalName): array
    {
        $this->assertZipAvailable();
        if (! File::isFile($sourcePath)) {
            throw new RuntimeException('Uploaded release package was not found.');
        }

        $inspection = $this->inspectPackage($sourcePath);
        if (! empty($inspection['unsafe_pending_migrations'])) {
            throw new RuntimeException('This patch contains a pending database change that could remove, rename or replace live data. ABS blocked the upload. Review: '.implode('; ', $inspection['unsafe_pending_migrations']));
        }
        if (! ($inspection['composer_compatible'] ?? false)) {
            throw new RuntimeException('This patch changes production Composer dependencies. Admin patching cannot safely replace vendor packages on shared hosting. Deploy dependency changes through a controlled server update first.');
        }
        if ($inspection['is_downgrade'] ?? false) {
            throw new RuntimeException('This package is older than the currently deployed ABS build. Use Restore Previous Build for rollback instead of installing an older release package.');
        }

        $version = preg_replace('/[^A-Za-z0-9._-]+/', '_', $inspection['version']);
        $stamp = now()->format('Ymd_His');
        $filename = self::PACKAGE_PREFIX."{$version}_{$stamp}_".Str::lower(Str::random(5)).'.zip';
        $destination = $this->packageDirectory().DIRECTORY_SEPARATOR.$filename;
        if (! File::copy($sourcePath, $destination)) {
            throw new RuntimeException('Unable to store the uploaded release package.');
        }

        // Keep one staged patch, but only prune the older package after the new
        // upload has been stored successfully.
        foreach ($this->listPackages() as $existing) {
            if ($existing['name'] !== $filename) {
                File::delete($this->packageDirectory().DIRECTORY_SEPARATOR.$existing['name']);
            }
        }

        $result = array_merge($inspection, [
            'name' => $filename,
            'original_name' => basename($originalName),
            'path' => $destination,
            'size' => File::size($destination),
            'sha256' => hash_file('sha256', $destination),
        ]);
        $this->audit('patch_staged', ['version' => $inspection['version'], 'package' => $filename, 'sha256' => $result['sha256']]);
        return $result;
    }

    public function inspectPackage(string $path): array
    {
        $this->assertZipAvailable();
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The package is not a readable ZIP archive.');
        }

        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                $this->assertSafeEntry($entry);
                $entries[$entry] = true;
            }

            foreach (['artisan','composer.json','BUILD_VERSION.txt','app/','routes/','resources/','database/migrations/'] as $required) {
                $found = isset($entries[$required]) || collect(array_keys($entries))->contains(fn ($entry) => str_starts_with($entry, $required));
                if (! $found) {
                    throw new RuntimeException("Release package is missing required application content: {$required}");
                }
            }

            $version = trim((string) $zip->getFromName('BUILD_VERSION.txt'));
            if ($version === '') {
                throw new RuntimeException('BUILD_VERSION.txt is empty.');
            }

            $migrationInspection = $this->inspectPendingMigrations($zip);
            $composerInspection = $this->inspectComposerCompatibility($zip);

            return [
                'version' => $version,
                'entries' => $zip->numFiles,
                'has_vendor' => collect(array_keys($entries))->contains(fn ($entry) => str_starts_with($entry, 'vendor/')),
                'has_migrations' => collect(array_keys($entries))->contains(fn ($entry) => str_starts_with($entry, 'database/migrations/')),
                'pending_migrations' => $migrationInspection['pending'],
                'unsafe_pending_migrations' => $migrationInspection['unsafe'],
                'composer_compatible' => $composerInspection['compatible'],
                'composer_changes' => $composerInspection['changes'],
                'is_downgrade' => $this->isDowngrade($version),
            ];
        } finally {
            $zip->close();
        }
    }

    public function createRestorePoint(string $reason = 'manual'): array
    {
        $this->assertZipAvailable();
        $stamp = now()->format('Ymd_His');
        $token = Str::lower(Str::random(6));
        $currentVersion = $this->applicationVersion();
        $safeVersion = preg_replace('/[^A-Za-z0-9._-]+/', '_', $currentVersion);
        $filename = self::RESTORE_PREFIX."{$safeVersion}_{$stamp}_{$token}.zip";
        $target = $this->restorePointDirectory().DIRECTORY_SEPARATOR.$filename;
        $temporary = $target.'.tmp';

        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the application backup.');
        }

        try {
            $manifest = [
                'product' => 'Alpha Block Solutions',
                'restore_format' => self::RESTORE_FORMAT,
                'created_at' => now()->toIso8601String(),
                'app_version' => $currentVersion,
                'reason' => $reason,
                'database_included' => false,
                'database_preserved_on_restore' => true,
                'environment_preserved' => true,
                'storage_preserved' => true,
            ];
            $zip->addFromString('restore-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            foreach ($this->applicationFiles() as [$absolute, $relative]) {
                $zip->addFile($absolute, 'application/'.$relative);
            }
        } finally {
            $zip->close();
        }

        if (! File::move($temporary, $target)) {
            File::delete($temporary);
            throw new RuntimeException('Unable to finalize the application backup.');
        }

        // Keep exactly one previous-build snapshot, and only after the new snapshot
        // exists successfully so a failed backup never destroys the last good copy.
        $this->enforceSingleRestorePoint($filename);

        $result = [
            'name' => $filename,
            'path' => $target,
            'version' => $currentVersion,
            'size' => File::size($target),
            'sha256' => hash_file('sha256', $target),
        ];
        $this->writeReleaseState([
            'last_backup' => [
                'name' => $filename,
                'version' => $currentVersion,
                'created_at' => now()->toIso8601String(),
                'sha256' => $result['sha256'],
            ],
        ]);
        $this->audit('previous_build_saved', ['version' => $currentVersion, 'backup' => $filename, 'reason' => $reason]);
        return $result;
    }

    public function installStagedPackage(string $name): array
    {
        $path = $this->resolveArchive($this->packageDirectory(), $name, self::PACKAGE_PREFIX);
        $inspection = $this->inspectPackage($path);
        if (! empty($inspection['unsafe_pending_migrations'])) {
            throw new RuntimeException('Patch blocked because a pending migration could remove, rename or replace live data: '.implode('; ', $inspection['unsafe_pending_migrations']));
        }
        if (! ($inspection['composer_compatible'] ?? false)) {
            throw new RuntimeException('Patch blocked because production Composer dependencies differ from the currently deployed build.');
        }
        if ($inspection['is_downgrade'] ?? false) {
            throw new RuntimeException('Patch blocked because the staged package is older than the current ABS build.');
        }

        $restorePoint = $this->createRestorePoint('automatic backup before patch '.$inspection['version']);
        $work = storage_path('app/abs-release-packages/.install-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5)));
        File::ensureDirectoryExists($work);
        $maintenanceEnabled = false;

        try {
            $this->extractSafe($path, $work);
            $this->preflightExtractedPackage($work, $inspection['version']);

            try {
                Artisan::call('down');
                $maintenanceEnabled = true;
            } catch (Throwable) {
                // Shared hosts can restrict maintenance commands. File deployment can
                // still proceed because the current request already has the old code loaded.
            }

            $this->synchronizeApplication($work);

            // Database policy: no seeding, no database restore and no destructive
            // pending migration. If the current schema is already complete and the patch
            // has no safe pending migrations, installation does not write business data.
            $schemaBefore = AbsSchemaRepair::diagnose();
            $schemaRepaired = false;
            if (! ($schemaBefore['ready'] ?? false)) {
                AbsSchemaRepair::repair();
                $schemaRepaired = true;
            }

            $baseline = LegacyMigrationBaseline::synchronize();
            $pendingSafety = $this->inspectFilesystemPendingMigrations();
            if (! empty($pendingSafety['unsafe'])) {
                throw new RuntimeException('Patch blocked before migration because a pending database change could remove, rename or replace live data: '.implode('; ', $pendingSafety['unsafe']));
            }
            $migrationsRan = count($pendingSafety['pending']) > 0;
            if ($migrationsRan) {
                $this->runArtisanOrFail('migrate', ['--force' => true]);
            }
            $this->runArtisanOrFail('optimize:clear');

            $installedVersion = $this->applicationVersion();
            if ($installedVersion !== $inspection['version']) {
                throw new RuntimeException('Patch verification failed: installed build marker does not match the staged package.');
            }

            $diagnosis = AbsSchemaRepair::diagnose();
            if (! ($diagnosis['ready'] ?? false)) {
                $missing = [];
                if (! empty($diagnosis['missing_tables'])) $missing[] = 'tables: '.implode(', ', $diagnosis['missing_tables']);
                foreach (($diagnosis['missing_columns'] ?? []) as $table => $columns) $missing[] = $table.': '.implode(', ', $columns);
                throw new RuntimeException('Patch files were installed but database verification still found missing required schema'.($missing ? ' ('.implode('; ', $missing).')' : '').'.');
            }

            File::delete($path);
            $this->writeReleaseState([
                'current_version' => $inspection['version'],
                'last_update' => [
                    'version' => $inspection['version'],
                    'installed_at' => now()->toIso8601String(),
                    'previous_build' => $restorePoint['name'],
                ],
            ]);
            $this->audit('patch_installed', [
                'version' => $inspection['version'],
                'previous_build' => $restorePoint['name'],
                'pending_migrations' => $inspection['pending_migrations'],
            ]);

            return [
                'installed_version' => $inspection['version'],
                'restore_point' => $restorePoint,
                'database_preserved' => true,
                'schema_repaired' => $schemaRepaired,
                'legacy_migrations_baselined' => count($baseline['baselined'] ?? []),
                'migrations_ran' => $migrationsRan,
                'cache_cleared' => true,
            ];
        } catch (Throwable $e) {
            try {
                $this->restoreRestorePoint($restorePoint['name'], false);
                $this->audit('patch_failed_auto_restore', [
                    'target_version' => $inspection['version'],
                    'restored_version' => $restorePoint['version'],
                    'error' => Str::limit($e->getMessage(), 1000),
                ]);
            } catch (Throwable $rollbackError) {
                $this->audit('patch_failed_restore_failed', [
                    'target_version' => $inspection['version'],
                    'error' => Str::limit($e->getMessage(), 800),
                    'restore_error' => Str::limit($rollbackError->getMessage(), 800),
                ]);
                throw new RuntimeException('Patch failed and automatic code rollback also failed. Patch error: '.$e->getMessage().' Rollback error: '.$rollbackError->getMessage(), previous: $e);
            }
            throw new RuntimeException('Patch failed. ABS restored the previous application build automatically. Live database records were not rolled back. '.$e->getMessage(), previous: $e);
        } finally {
            if ($maintenanceEnabled) {
                try { Artisan::call('up'); } catch (Throwable) {}
            }
            File::deleteDirectory($work);
        }
    }

    public function restoreRestorePoint(string $name, bool $unusedCreateSafetyPoint = false): array
    {
        $path = $this->resolveArchive($this->restorePointDirectory(), $name, self::RESTORE_PREFIX, true);
        $work = storage_path('app/abs-restore-points/.restore-'.now()->format('YmdHis').'-'.Str::lower(Str::random(5)));
        File::ensureDirectoryExists($work);
        $maintenanceEnabled = false;

        try {
            $this->extractSafe($path, $work);
            $manifestPath = $work.'/restore-manifest.json';
            if (! File::isFile($manifestPath) || ! File::isDirectory($work.'/application')) {
                throw new RuntimeException('The previous-build backup is incomplete.');
            }
            $manifest = json_decode((string) File::get($manifestPath), true);
            $format = (int) ($manifest['restore_format'] ?? 0);
            if (($manifest['product'] ?? null) !== 'Alpha Block Solutions' || ! in_array($format, [1, self::RESTORE_FORMAT], true)) {
                throw new RuntimeException('Unsupported previous-build backup format.');
            }

            try {
                Artisan::call('down');
                $maintenanceEnabled = true;
            } catch (Throwable) {}

            $this->synchronizeApplication($work.'/application');
            // Deliberately do not import, replace, truncate or otherwise roll back
            // the live database. Additive schema from a newer patch is harmless to
            // older code and keeps every live user/payment/trade record intact.
            try { Artisan::call('optimize:clear'); } catch (Throwable) {}

            $restoredVersion = (string) ($manifest['app_version'] ?? 'unknown');
            $this->writeReleaseState([
                'current_version' => $restoredVersion,
                'last_restore' => [
                    'version' => $restoredVersion,
                    'restored_at' => now()->toIso8601String(),
                    'backup' => basename($path),
                    'database_preserved' => true,
                ],
            ]);
            $this->audit('previous_build_restored', [
                'restored_version' => $restoredVersion,
                'backup' => basename($path),
                'database_preserved' => true,
            ]);

            return [
                'restored_version' => $restoredVersion,
                'database_preserved' => true,
                'restore_point' => basename($path),
            ];
        } finally {
            if ($maintenanceEnabled) {
                try { Artisan::call('up'); } catch (Throwable) {}
            }
            File::deleteDirectory($work);
        }
    }

    public function deletePackage(string $name): void
    {
        File::delete($this->resolveArchive($this->packageDirectory(), $name, self::PACKAGE_PREFIX));
    }

    public function deleteRestorePoint(string $name): void
    {
        File::delete($this->resolveArchive($this->restorePointDirectory(), $name, self::RESTORE_PREFIX, true));
    }

    public function resolvePackage(string $name): string
    {
        return $this->resolveArchive($this->packageDirectory(), $name, self::PACKAGE_PREFIX);
    }

    public function resolveRestorePoint(string $name): string
    {
        return $this->resolveArchive($this->restorePointDirectory(), $name, self::RESTORE_PREFIX, true);
    }

    public function releaseState(): array
    {
        $path = storage_path('app/abs-release-state.json');
        if (! File::isFile($path)) return [];
        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function recentAudit(int $limit = 10): array
    {
        $path = storage_path('app/abs-release-audit.jsonl');
        if (! File::isFile($path)) return [];
        $lines = array_values(array_filter(preg_split('/\R/', trim((string) File::get($path))) ?: []));
        return collect(array_slice($lines, -max(1, $limit)))
            ->reverse()
            ->map(function (string $line) {
                $item = json_decode($line, true);
                return is_array($item) ? $item : ['event' => 'audit_entry', 'message' => $line];
            })->values()->all();
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

        // Delete obsolete files only from application-owned trees. Public root is
        // intentionally not swept so host-managed files such as ads.txt, .well-known
        // verification files or other live public artifacts survive every patch.
        $managedDirectories = ['app','bootstrap','config','database','public/assets','resources','routes','scripts','docs','tests'];
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

    private function preflightExtractedPackage(string $sourceRoot, string $expectedVersion): void
    {
        $build = $sourceRoot.'/BUILD_VERSION.txt';
        if (! File::isFile($build) || trim((string) File::get($build)) !== $expectedVersion) {
            throw new RuntimeException('Patch preflight failed: BUILD_VERSION.txt does not match the staged release.');
        }
        foreach (['artisan','composer.json','routes/web.php','routes/api.php'] as $required) {
            if (! File::exists($sourceRoot.'/'.$required)) {
                throw new RuntimeException('Patch preflight failed: missing '.$required.'.');
            }
        }

        // Parse application PHP before any live file is replaced. TOKEN_PARSE uses the
        // running PHP engine itself, so this works on shared hosting without shell_exec.
        foreach (['app','bootstrap','config','database/migrations','routes'] as $directory) {
            $path = $sourceRoot.'/'.$directory;
            if (! File::isDirectory($path)) continue;
            foreach (File::allFiles($path) as $file) {
                if (strtolower($file->getExtension()) !== 'php' || Str::endsWith(strtolower($file->getFilename()), '.blade.php')) continue;
                try {
                    token_get_all((string) File::get($file->getPathname()), TOKEN_PARSE);
                } catch (\ParseError $e) {
                    throw new RuntimeException('Patch preflight failed PHP syntax check: '.$directory.'/'.$file->getRelativePathname().' — '.$e->getMessage(), previous: $e);
                }
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
            || str_starts_with($relative, 'public/uploads/')
            || str_starts_with($relative, 'public/user-content/')
            || str_starts_with($relative, 'public/.well-known/')
            || str_starts_with($relative, 'public/hot');
    }

    private function resolveArchive(string $directory, string $name, string $prefix, bool $allowLegacyRestoreName = false): string
    {
        $name = basename($name);
        $validPrefix = str_starts_with($name, $prefix)
            || ($allowLegacyRestoreName && str_starts_with($name, 'ABS_RESTORE_POINT_'));
        if (! $validPrefix || ! Str::endsWith(strtolower($name), '.zip')) {
            throw new RuntimeException('Invalid archive filename.');
        }
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

    private function enforceSingleRestorePoint(?string $keep = null): void
    {
        $directory = $this->restorePointDirectory();
        $files = collect(File::files($directory))
            ->filter(fn ($file) => (
                str_starts_with($file->getFilename(), self::RESTORE_PREFIX)
                || str_starts_with($file->getFilename(), 'ABS_RESTORE_POINT_')
            ) && Str::endsWith(strtolower($file->getFilename()), '.zip'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($keep === null) {
            $keep = $files->first()?->getFilename();
        }
        foreach ($files as $file) {
            if ($file->getFilename() !== $keep) File::delete($file->getPathname());
        }

        // If the only retained file uses the legacy name, expose it through the
        // last-working-build listing by renaming it without changing its contents.
        if ($keep && str_starts_with($keep, 'ABS_RESTORE_POINT_')) {
            $source = $directory.DIRECTORY_SEPARATOR.$keep;
            if (File::isFile($source)) {
                $targetName = self::RESTORE_PREFIX.substr($keep, strlen('ABS_RESTORE_POINT_'));
                $target = $directory.DIRECTORY_SEPARATOR.$targetName;
                if (! File::exists($target)) File::move($source, $target);
            }
        }
    }

    private function inspectComposerCompatibility(ZipArchive $zip): array
    {
        $candidateRaw = $zip->getFromName('composer.json');
        $currentPath = base_path('composer.json');
        if ($candidateRaw === false || ! File::isFile($currentPath)) {
            return ['compatible' => false, 'changes' => ['composer.json unavailable for comparison']];
        }

        $candidate = json_decode((string) $candidateRaw, true);
        $current = json_decode((string) File::get($currentPath), true);
        if (! is_array($candidate) || ! is_array($current)) {
            return ['compatible' => false, 'changes' => ['composer.json could not be parsed']];
        }

        $candidateRequire = (array) ($candidate['require'] ?? []);
        $currentRequire = (array) ($current['require'] ?? []);
        ksort($candidateRequire);
        ksort($currentRequire);
        if ($candidateRequire === $currentRequire) {
            return ['compatible' => true, 'changes' => []];
        }

        $changes = [];
        foreach (array_unique(array_merge(array_keys($currentRequire), array_keys($candidateRequire))) as $package) {
            $from = $currentRequire[$package] ?? null;
            $to = $candidateRequire[$package] ?? null;
            if ($from !== $to) $changes[] = $package.': '.($from ?? 'not installed').' -> '.($to ?? 'removed');
        }
        return ['compatible' => false, 'changes' => $changes];
    }

    private function inspectFilesystemPendingMigrations(): array
    {
        $applied = [];
        if (Schema::hasTable('migrations')) {
            $applied = DB::table('migrations')->pluck('migration')->map(fn ($m) => (string) $m)->all();
        }

        $pending = [];
        $unsafe = [];
        $directory = database_path('migrations');
        if (! File::isDirectory($directory)) return ['pending' => [], 'unsafe' => []];

        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'php') continue;
            $migration = $file->getFilenameWithoutExtension();
            if (in_array($migration, $applied, true)) continue;
            $pending[] = $migration;
            $up = $this->migrationUpSection((string) File::get($file->getPathname()));
            $reasons = $this->destructiveMigrationReasons($up);
            if ($reasons) $unsafe[] = $migration.' ['.implode(', ', $reasons).']';
        }

        return ['pending' => $pending, 'unsafe' => $unsafe];
    }

    private function inspectPendingMigrations(ZipArchive $zip): array
    {
        $applied = [];
        try {
            if (Schema::hasTable('migrations')) {
                $applied = DB::table('migrations')->pluck('migration')->map(fn ($m) => (string) $m)->all();
            }
        } catch (Throwable) {
            // Database diagnostics are handled during install. Staging can still inspect
            // migration source and will treat all files as candidates if DB is unavailable.
        }

        $pending = [];
        $unsafe = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (! preg_match('#^database/migrations/([^/]+)\.php$#', $entry, $match)) continue;
            $migration = $match[1];
            if (in_array($migration, $applied, true)) continue;
            $pending[] = $migration;
            $content = (string) $zip->getFromIndex($i);
            $up = $this->migrationUpSection($content);
            $reasons = $this->destructiveMigrationReasons($up);
            if ($reasons) $unsafe[] = $migration.' ['.implode(', ', $reasons).']';
        }

        return ['pending' => $pending, 'unsafe' => $unsafe];
    }

    private function migrationUpSection(string $content): string
    {
        $start = preg_match('/(?:public\s+)?function\s+up\s*\([^)]*\)[^{]*\{/i', $content, $m, PREG_OFFSET_CAPTURE)
            ? ($m[0][1] + strlen($m[0][0]))
            : 0;
        if ($start === 0) return $content;
        $tail = substr($content, $start);
        if (preg_match('/(?:public\s+)?function\s+down\s*\(/i', $tail, $down, PREG_OFFSET_CAPTURE)) {
            return substr($tail, 0, $down[0][1]);
        }
        return $tail;
    }

    private function destructiveMigrationReasons(string $up): array
    {
        $checks = [
            'drop table' => '/Schema::drop(?:IfExists)?\s*\(/i',
            'rename table' => '/Schema::rename\s*\(/i',
            'drop schema item' => '/->\s*drop[A-Za-z0-9_]*\s*\(/i',
            'rename column' => '/->\s*renameColumn\s*\(/i',
            'truncate data' => '/->\s*truncate\s*\(/i',
            'delete rows' => '/->\s*delete\s*\(/i',
            'update existing rows' => '/->\s*(?:update|updateOrInsert)\s*\(/i',
            'raw DELETE' => '/\bDELETE\s+FROM\b/i',
            'raw UPDATE' => '/\bUPDATE\s+[`A-Za-z0-9_]+\s+SET\b/i',
            'raw DROP' => '/\bDROP\s+(?:TABLE|DATABASE|COLUMN)\b/i',
            'raw TRUNCATE' => '/\bTRUNCATE\s+TABLE\b/i',
            'raw RENAME' => '/\bRENAME\s+(?:TABLE|COLUMN)\b/i',
        ];
        $reasons = [];
        foreach ($checks as $label => $pattern) {
            if (preg_match($pattern, $up)) $reasons[] = $label;
        }
        return $reasons;
    }

    private function isDowngrade(string $candidateBuild): bool
    {
        $candidate = $this->numericVersion($candidateBuild);
        $current = $this->numericVersion($this->applicationVersion());
        return $candidate !== null && $current !== null && version_compare($candidate, $current, '<');
    }

    private function numericVersion(string $build): ?string
    {
        return preg_match('/(?:^|\b)V?(\d+\.\d+\.\d+)(?:\b|$)/i', $build, $match) ? $match[1] : null;
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

    private function writeReleaseState(array $updates): void
    {
        $path = storage_path('app/abs-release-state.json');
        $state = $this->releaseState();
        foreach ($updates as $key => $value) $state[$key] = $value;
        $state['updated_at'] = now()->toIso8601String();
        File::put($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function audit(string $event, array $context = []): void
    {
        try {
            $path = storage_path('app/abs-release-audit.jsonl');
            $actorId = null;
            try { $actorId = auth()->id(); } catch (Throwable) {}
            $ip = null;
            try { $ip = request()->ip(); } catch (Throwable) {}
            $record = [
                'time' => now()->toIso8601String(),
                'event' => $event,
                'version' => $this->applicationVersion(),
                'ip' => $ip,
                'actor_id' => $actorId,
                'context' => $context,
            ];
            File::append($path, json_encode($record, JSON_UNESCAPED_SLASHES).PHP_EOL);
        } catch (Throwable) {
            // Audit storage is best-effort and must never block a production rollback.
        }
    }
}
