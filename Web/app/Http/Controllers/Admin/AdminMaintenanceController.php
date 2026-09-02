<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationBackupService;
use App\Services\MarketDataService;
use App\Support\AbsSchemaRepair;
use App\Support\LegacyMigrationBaseline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class AdminMaintenanceController extends Controller
{
    public function index()
    {
        $diagnosis = AbsSchemaRepair::diagnose();
        [$applied, $pending] = $this->migrationSummary();

        return view('admin.enterprise.maintenance', [
            'diagnosis' => $diagnosis,
            'appliedMigrations' => $applied,
            'pendingMigrations' => $pending,
            'buildVersion' => $this->buildVersion(),
            'phpVersion' => PHP_VERSION,
            'databaseName' => DB::getDatabaseName(),
            'cacheStore' => config('cache.default'),
        ]);
    }

    public function run(Request $request, ApplicationBackupService $backups, MarketDataService $market)
    {
        $data = $request->validate([
            'action' => ['required', 'in:update_latest,repair_schema,sync_content,run_migrations,clear_cache,refresh_market'],
            'confirmation' => ['nullable', 'string', 'max:32'],
        ]);

        $action = $data['action'];
        if ($action === 'update_latest' && strtoupper(trim((string) ($data['confirmation'] ?? ''))) !== 'UPDATE') {
            return back()->with('warning', 'Type UPDATE exactly before applying the complete database update.');
        }

        $backupName = null;
        $output = [];

        try {
            if (in_array($action, ['update_latest', 'repair_schema', 'sync_content', 'run_migrations'], true)) {
                try {
                    $backup = $backups->create(false);
                    $backupName = $backup['name'];
                } catch (Throwable $e) {
                    // A missing ZipArchive extension should not make a schema repair impossible,
                    // but the administrator is told clearly that the automatic safety archive failed.
                    $output[] = 'Safety backup warning: '.$e->getMessage();
                }
            }

            if ($action === 'update_latest') {
                AbsSchemaRepair::repair();
                $baseline = LegacyMigrationBaseline::synchronize();
                $output[] = 'Schema repair completed.';
                $output[] = 'Legacy migrations baselined: '.count($baseline['baselined'] ?? []).'.';
                $this->callArtisan('migrate', ['--force' => true], $output);
                $this->callArtisan('db:seed', ['--force' => true], $output);
                $this->callArtisan('optimize:clear', [], $output);
                $diagnosis = AbsSchemaRepair::diagnose();
                if (! ($diagnosis['ready'] ?? false)) {
                    throw new \RuntimeException('Update completed but schema diagnostics still report missing tables or columns.');
                }
                return back()->with('success', 'Latest ABS database structure and baseline content applied successfully.'.($backupName ? ' Safety backup: '.$backupName : ''))->with('maintenance_output', $output);
            }

            if ($action === 'repair_schema') {
                AbsSchemaRepair::repair();
                $baseline = LegacyMigrationBaseline::synchronize();
                $output[] = 'Required ABS/Pulse tables and columns reconciled.';
                $output[] = 'Legacy migrations baselined: '.count($baseline['baselined'] ?? []).'.';
                $this->callArtisan('migrate', ['--force' => true], $output);
                $this->callArtisan('db:seed', ['--force' => true], $output);
                $this->callArtisan('optimize:clear', [], $output);

                $diagnosis = AbsSchemaRepair::diagnose();
                if (! ($diagnosis['ready'] ?? false)) {
                    throw new \RuntimeException('Database Fix completed but required schema is still missing. Review the maintenance output and server database permissions.');
                }
                $output[] = 'Final schema verification: READY.';
                return back()->with('success', 'Database Fix completed: missing schema repaired, safe migrations applied, baseline records synchronized and caches cleared.'.($backupName ? ' Safety backup: '.$backupName : ''))->with('maintenance_output', $output);
            }

            if ($action === 'sync_content') {
                $this->callArtisan('db:seed', ['--force' => true], $output);
                $this->callArtisan('optimize:clear', [], $output);
                return back()->with('success', 'Reviewed ABS baseline content, plans and system settings synchronized. Existing administrator passwords are not reset.'.($backupName ? ' Safety backup: '.$backupName : ''))->with('maintenance_output', $output);
            }

            if ($action === 'run_migrations') {
                $baseline = LegacyMigrationBaseline::synchronize();
                $output[] = 'Legacy migrations baselined: '.count($baseline['baselined'] ?? []).'.';
                $this->callArtisan('migrate', ['--force' => true], $output);
                $this->callArtisan('optimize:clear', [], $output);
                return back()->with('success', 'Pending Laravel migrations applied.'.($backupName ? ' Safety backup: '.$backupName : ''))->with('maintenance_output', $output);
            }

            if ($action === 'clear_cache') {
                $this->callArtisan('optimize:clear', [], $output);
                return back()->with('success', 'Application caches cleared successfully.')->with('maintenance_output', $output);
            }

            if ($action === 'refresh_market') {
                $overview = $market->overview(true);
                $movers = $market->movers(5, true);
                $global = (array) ($overview['global'] ?? []);
                $output[] = 'Overview status: '.($overview['status'] ?? 'unavailable');
                $output[] = 'Overview sources: '.($overview['source'] ?? 'unavailable');
                $output[] = 'Total market cap: '.(is_numeric($global['total_market_cap'] ?? null) ? '$'.number_format((float) $global['total_market_cap'], 0) : 'unavailable');
                $output[] = '24H trading volume: '.(is_numeric($global['total_volume'] ?? null) ? '$'.number_format((float) $global['total_volume'], 0) : 'unavailable');
                $output[] = 'BTC dominance: '.(is_numeric($global['btc_dominance'] ?? null) ? number_format((float) $global['btc_dominance'], 2).'%' : 'unavailable');
                $output[] = 'Fear & Greed: '.(is_numeric($global['fear_greed_score'] ?? null) ? (int) $global['fear_greed_score'].' '.($global['fear_greed_label'] ?? '') : 'unavailable');
                $output[] = '24H liquidations: '.(is_numeric($global['liquidation_24h_usd'] ?? null) ? '$'.number_format((float) $global['liquidation_24h_usd'], 0) : 'unavailable');
                $output[] = 'Open Interest: '.(is_numeric($global['open_interest_usd'] ?? null) ? '$'.number_format((float) $global['open_interest_usd'], 0) : 'unavailable');
                $output[] = 'Funding rate: '.(is_numeric($global['funding_rate'] ?? null) ? number_format((float) $global['funding_rate'], 4).'%' : 'unavailable');
                $output[] = 'Long / Short ratio: '.(is_numeric($global['long_short_ratio'] ?? null) ? number_format((float) $global['long_short_ratio'], 3) : 'unavailable');
                $output[] = 'Perp premium basis: '.(is_numeric($global['perp_premium_basis'] ?? null) ? number_format((float) $global['perp_premium_basis'], 4).'%' : 'unavailable');
                $output[] = 'Industries loaded: '.count($overview['industries'] ?? []);
                $output[] = 'Movers loaded: '.count($movers['gainers'] ?? []).' gainers / '.count($movers['losers'] ?? []).' losers ('.($movers['source'] ?? 'unavailable').')';
                return back()->with('success', 'Live market cache refresh finished. Review the provider results below.')->with('maintenance_output', $output);
            }
        } catch (Throwable $e) {
            report($e);
            $output[] = 'ERROR: '.$e->getMessage();
            return back()->with('warning', 'Maintenance action failed: '.$e->getMessage())->with('maintenance_output', $output);
        }

        return back();
    }

    private function callArtisan(string $command, array $parameters, array &$output): void
    {
        $code = Artisan::call($command, $parameters);
        $text = trim(Artisan::output());
        $output[] = '$ php artisan '.$command.($parameters ? ' '.implode(' ', array_keys($parameters)) : '');
        if ($text !== '') $output[] = Str::limit($text, 8000, "\n…output truncated…");
        if ($code !== 0) throw new \RuntimeException("Artisan command {$command} returned exit code {$code}.");
    }

    private function migrationSummary(): array
    {
        $applied = [];
        try {
            $applied = DB::table('migrations')->pluck('migration')->all();
        } catch (Throwable) {
            // The schema diagnostic will communicate a missing migrations table if relevant.
        }

        $files = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->values()
            ->all();

        return [$applied, array_values(array_diff($files, $applied))];
    }

    private function buildVersion(): string
    {
        $path = base_path('BUILD_VERSION.txt');
        return File::isFile($path) ? trim((string) File::get($path)) : 'ABS';
    }
}
