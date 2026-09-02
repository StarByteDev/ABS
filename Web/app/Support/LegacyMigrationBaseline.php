<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyMigrationBaseline
{
    /**
     * Records table-creation migrations as already satisfied when every table
     * they create is physically present. This prevents old, unrecorded ABS/Pulse
     * migrations from trying to recreate tables such as `users` after a safe
     * schema repair.
     *
     * Existing migration records are preserved. Migrations containing only
     * ALTER/data operations are deliberately left pending and are never run by
     * this helper.
     *
     * @return array{baselined: list<string>, skipped: list<string>}
     */
    public static function synchronize(): array
    {
        self::ensureMigrationRepository();

        $applied = DB::table('migrations')
            ->pluck('migration')
            ->mapWithKeys(fn (string $migration): array => [$migration => true])
            ->all();

        $nextBatch = ((int) DB::table('migrations')->max('batch')) + 1;
        $baselined = [];
        $skipped = [];

        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            $migration = pathinfo($file, PATHINFO_FILENAME);

            if (isset($applied[$migration])) {
                continue;
            }

            $source = file_get_contents($file);
            if ($source === false) {
                $skipped[] = $migration;
                continue;
            }

            $createdTables = self::createdTables($source);

            // Do not guess about migrations that do not explicitly create tables.
            if ($createdTables === []) {
                $skipped[] = $migration;
                continue;
            }

            $allTablesExist = collect($createdTables)
                ->every(fn (string $table): bool => Schema::hasTable($table));

            if (! $allTablesExist) {
                $skipped[] = $migration;
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $nextBatch,
            ]);

            $baselined[] = $migration;
        }

        return compact('baselined', 'skipped');
    }

    private static function ensureMigrationRepository(): void
    {
        if (! Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });

            return;
        }

        $needsMigration = ! Schema::hasColumn('migrations', 'migration');
        $needsBatch = ! Schema::hasColumn('migrations', 'batch');

        if (! $needsMigration && ! $needsBatch) {
            return;
        }

        Schema::table('migrations', function (Blueprint $table) use ($needsMigration, $needsBatch): void {
            if ($needsMigration) {
                $table->string('migration')->nullable();
            }
            if ($needsBatch) {
                $table->integer('batch')->default(0);
            }
        });
    }

    /** @return list<string> */
    private static function createdTables(string $source): array
    {
        preg_match_all(
            "/Schema::create\\s*\\(\\s*['\"]([^'\"]+)['\"]/",
            $source,
            $matches,
        );

        return array_values(array_unique($matches[1] ?? []));
    }
}
