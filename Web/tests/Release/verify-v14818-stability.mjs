import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const exists = rel => fs.existsSync(path.join(root, rel));
const checks = [];
const check = (name, ok) => checks.push({ name, ok: Boolean(ok) });
const has = (rel, ...needles) => {
  if (!exists(rel)) return false;
  const src = read(rel);
  return needles.every(needle => src.includes(needle));
};

const pageData = read('app/Services/PulsePageDataService.php');
const dashboardStart = pageData.indexOf('public function dashboard');
const dashboardEnd = pageData.indexOf('\n    public function', dashboardStart + 1);
const dashboard = pageData.slice(dashboardStart, dashboardEnd > dashboardStart ? dashboardEnd : undefined);
const capInit = dashboard.indexOf('$scannerCapabilities = $this->capabilities($user);');
const readyInit = dashboard.indexOf('$scannerConnectionReady = $this->connectionReady($connection);');
const capUse = dashboard.indexOf("'capabilities' => $scannerCapabilities");
const readyUse = dashboard.indexOf("'connection_ready' => $scannerConnectionReady");

check('Build version is V14.8.18 stability build', read('BUILD_VERSION.txt').includes('ABS V14.8.18'));
check('API build reports 14.8.18', has('app/Http/Controllers/Api/V1/AppController.php', "'build' => '14.8.18'"));
check('OpenAPI info version reports 14.8.18', /version:\s*["']?14\.8\.18["']?/.test(read('docs/openapi.yaml')));
check('abs:about identifies V14.8.18', has('routes/console.php', 'Alpha Block Solutions V14.8.18'));

check('Dashboard initializes scanner capabilities', capInit >= 0);
check('Dashboard initializes scanner connection readiness', readyInit >= 0);
check('Dashboard initializes capabilities before returning them', capInit >= 0 && capUse > capInit);
check('Dashboard initializes connection readiness before returning it', readyInit >= 0 && readyUse > readyInit);

check('Mobile reports preserves legacy summary key', has('app/Http/Controllers/Api/V1/PulseController.php', "'summary' => $tradingSummary"));
check('Mobile reports also exposes trading key', has('app/Http/Controllers/Api/V1/PulseController.php', "'trading' => $tradingSummary"));
check('Mobile signal validation exposes schema readiness metadata', has('app/Http/Controllers/Api/V1/PulseController.php', "'schema_ready'=>false", 'Run php artisan abs:repair --seed.'));
check('Mobile signal performance reports schema readiness', has('app/Http/Controllers/Api/V1/PulseController.php', "Schema::hasTable('pulse_signal_daily_metrics')", "Schema::hasTable('pulse_signal_validations')"));
check('Mobile strategy reports schema readiness', has('app/Http/Controllers/Api/V1/PulseController.php', "Schema::hasTable('pulse_strategy_daily_metrics')"));
check('Mobile learning insights reports schema readiness', has('app/Http/Controllers/Api/V1/PulseController.php', "Schema::hasTable('pulse_strategy_learning_states')"));

check('Central market-data service has schemaReady()', has('app/Services/PulseMarketDataService.php', 'public function schemaReady(): bool'));
check('Central market-data service has missingTables()', has('app/Services/PulseMarketDataService.php', 'public function missingTables(): array'));
check('Central market-data sync gives actionable recovery error', has('app/Services/PulseMarketDataService.php', 'Pulse central market-data schema is not ready', 'abs:repair --seed'));
check('Central market-data health exposes controlled schema_not_ready state', has('app/Services/PulseMarketDataService.php', "'schema_ready' => false", "'last_run_status' => 'schema_not_ready'"));
check('Central latestPrice is guarded by its required table', has('app/Services/PulseMarketDataService.php', 'public function latestPrice', "Schema::hasTable('pulse_market_prices')"));
check('Central scannerRows is guarded by its required table', has('app/Services/PulseMarketDataService.php', 'public function scannerRows', "Schema::hasTable('pulse_market_candles')"));

check('User reports guard signal daily table', has('app/Http/Controllers/Pulse/ReportsController.php', "Schema::hasTable('pulse_signal_daily_metrics')"));
check('User reports guard strategy daily table', has('app/Http/Controllers/Pulse/ReportsController.php', "Schema::hasTable('pulse_strategy_daily_metrics')"));
check('User reports guard learning-state table', has('app/Http/Controllers/Pulse/ReportsController.php', "Schema::hasTable('pulse_strategy_learning_states')"));
check('User reports guard validation table', has('app/Http/Controllers/Pulse/ReportsController.php', "Schema::hasTable('pulse_signal_validations')"));
check('Admin intelligence guards daily metrics', has('app/Http/Controllers/Admin/AdminPulseController.php', "Schema::hasTable('pulse_signal_daily_metrics')"));
check('Admin intelligence guards learning states', has('app/Http/Controllers/Admin/AdminPulseController.php', "Schema::hasTable('pulse_strategy_learning_states')"));
check('Admin intelligence guards market-data runs', has('app/Http/Controllers/Admin/AdminPulseController.php', "Schema::hasTable('pulse_market_data_runs')"));
check('Pulse reports presents setup-required notice', has('resources/views/pulse/reports.blade.php', "schema_ready", 'abs:repair --seed'));
check('Admin intelligence presents setup-required notice', has('resources/views/admin/pulse/intelligence.blade.php', "schema_ready", 'abs:repair --seed'));

check('Validation scheduler path safely handles absent schema', has('app/Services/PulseSignalValidationService.php', "'schema_ready' => false", "Schema::hasTable('pulse_signal_validations')"));
check('Validation creation gives actionable recovery error', has('app/Services/PulseSignalValidationService.php', 'Pulse signal-validation schema is not ready', 'abs:repair --seed'));
check('Learning reliability returns neutral prior if schema missing', has('app/Services/PulseLearningService.php', "Schema::hasTable('pulse_strategy_learning_states')", "'score' => 50.0", "'source' => 'neutral_prior'"));
check('Learning daily rebuild is guarded by all required architecture tables', has('app/Services/PulseLearningService.php', "['pulse_signal_validations','pulse_signal_daily_metrics','pulse_strategy_daily_metrics','pulse_strategy_learning_states']", 'Schema::hasTable($table)')); 

// Preserve the approved V14.8.17 price/signal architecture without falsely changing engine identity.
check('Architecture migration remains packaged', exists('database/migrations/2026_08_29_000840_add_abs_price_signal_intelligence_architecture.php'));
for (const table of ['pulse_market_prices','pulse_market_candles','pulse_market_data_runs','pulse_signal_validations','pulse_signal_daily_metrics','pulse_strategy_daily_metrics','pulse_strategy_learning_states']) {
  check(`Architecture table retained: ${table}`, read('database/migrations/2026_08_29_000840_add_abs_price_signal_intelligence_architecture.php').includes(`'${table}'`));
  check(`Schema repair retains: ${table}`, read('app/Support/PulseSchemaRepair.php').includes(`'${table}'`));
}
check('Scanner still reads central latest prices', has('app/Services/PulseScannerService.php', '$this->market->latestPrices', '$this->market->scannerRows'));
check('Scanner still avoids direct Binance public ticker/klines calls', !/publicTickers\(|publicKlines(?:Batch)?\(/.test(read('app/Services/PulseScannerService.php')));
check('Central service retains 15m + 4h scanner buffers', has('app/Services/PulseMarketDataService.php', "['15m', '4h']", 'scannerRows'));
check('Central service retains 1m validation candles', has('app/Services/PulseMarketDataService.php', "syncCandleBatch($validationSymbols, '1m'", 'minuteCandles'));
check('Signal engine identity remains 14.8.17 for learning continuity', has('app/Services/PulseScannerService.php', 'engine-14.8.17-'));
check('Signal immutability contract remains present', has('app/Services/PulseScannerService.php', 'a generated signal is immutable', 'must not rewrite its frozen'));
check('Entry candle remains excluded from TP/SL validation', has('app/Services/PulseSignalValidationService.php', 'Entry candle is intentionally excluded from TP/SL validation', 'entryMinuteMs'));
check('Same-minute TP+SL ambiguity remains protected', has('app/Services/PulseSignalValidationService.php', "'ambiguous'", "'same_minute_tp_sl'"));
check('MFE/MAE/duration validation metrics remain present', has('app/Services/PulseSignalValidationService.php', 'mfe_r', 'mae_r', 'duration_seconds'));
check('Evidence-protected learning remains present', has('app/Services/PulseLearningService.php', 'prior_samples', 'established_samples', 'daily_decay', 'context_minimum_samples'));
check('Hierarchical learning fallback remains present', has('app/Services/PulseLearningService.php', 'bestState', "where('market_regime','ALL')"));
check('Market-data scheduler remains every minute', has('routes/console.php', "Schedule::command('abs:pulse-market-data')->everyMinute()"));
check('Signal validator scheduler remains every minute', has('routes/console.php', "Schedule::command('abs:pulse-validate-signals')->everyMinute()"));
check('Learning scheduler remains daily', has('routes/console.php', "Schedule::command('abs:pulse-learning')->dailyAt('00:20')"));

for (const route of ['/reports/signals','/reports/strategies','/reports/learning','/market-data/health','/market-data/prices','/signals/{signal}/validation']) {
  check(`Mobile route retained: ${route}`, read('routes/api.php').includes(`'${route}'`));
  check(`OpenAPI route retained: ${route}`, read('docs/openapi.yaml').includes(`  /pulse${route}:`));
}

check('V14.8.18 stability documentation exists', exists('docs/ABS_V14_8_18_FULL_SITE_STABILITY_RUNTIME_REPAIR.md'));
check('V14.8.18 mobile compatibility documentation exists', exists('docs/MOBILE_API_V14_8_18.md'));
check('V14.8.17 architecture documentation remains packaged', exists('docs/ABS_V14_8_17_CENTRAL_PRICE_SIGNAL_INTELLIGENCE_ARCHITECTURE.md'));
check('V14.8.17 mobile architecture documentation remains packaged', exists('docs/MOBILE_API_V14_8_17.md'));

const readme = read('README.md');
for (const version of ['V14.8.18','V14.8.17','V14.8.16','V14.8.15','V14.8.14','V14.8.13','V14.8.12','V14.8.2','V14.7.7','V14.6']) {
  check(`README cumulative history includes ${version}`, readme.includes(version));
}
check('README documents dashboard runtime repair', readme.includes('scannerCapabilities') && readme.includes('scannerConnectionReady'));
check('README documents no V14.8.18 migration', readme.includes('No new database migration is introduced by V14.8.18'));
check('No V14.8.18 migration was introduced', !fs.readdirSync(path.join(root, 'database/migrations')).some(name => /14_8_18|14818/i.test(name)));

const failed = checks.filter(c => !c.ok);
for (const c of checks) console.log(`${c.ok ? 'PASS' : 'FAIL'}  ${c.name}`);
console.log(`\nABS V14.8.18 stability + architecture preservation contract: ${checks.length - failed.length}/${checks.length} checks passed.`);
if (failed.length) process.exit(1);
