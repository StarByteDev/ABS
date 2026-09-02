import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const exists = rel => fs.existsSync(path.join(root, rel));
const checks = [];
const check = (name, ok) => checks.push({name, ok: Boolean(ok)});
const has = (rel, ...needles) => {
  const src = read(rel);
  return needles.every(needle => src.includes(needle));
};

check('Build version is V14.8.17 architecture build', read('BUILD_VERSION.txt').includes('ABS V14.8.17'));
check('Architecture migration exists', exists('database/migrations/2026_08_29_000840_add_abs_price_signal_intelligence_architecture.php'));
for (const table of ['pulse_market_prices','pulse_market_candles','pulse_market_data_runs','pulse_signal_validations','pulse_signal_daily_metrics','pulse_strategy_daily_metrics','pulse_strategy_learning_states']) {
  check(`Migration creates ${table}`, read('database/migrations/2026_08_29_000840_add_abs_price_signal_intelligence_architecture.php').includes(`'${table}'`));
  check(`Direct schema repair covers ${table}`, read('app/Support/PulseSchemaRepair.php').includes(`'${table}'`));
}
for (const model of ['PulseMarketPrice','PulseMarketCandle','PulseMarketDataRun','PulseSignalValidation','PulseSignalDailyMetric','PulseStrategyDailyMetric','PulseStrategyLearningState']) {
  check(`Model ${model} exists`, exists(`app/Models/${model}.php`));
}
check('Central market data service exists', exists('app/Services/PulseMarketDataService.php'));
check('Central service fetches one shared ticker snapshot', has('app/Services/PulseMarketDataService.php', "publicTickers('live')", 'syncCentral()', 'pulse_market_prices'));
check('Central service maintains 15m + 4h scanner buffers', has('app/Services/PulseMarketDataService.php', "['15m', '4h']", 'scannerRows', "where('is_closed', true)"));
check('Central service maintains 1m validation candles', has('app/Services/PulseMarketDataService.php', "syncCandleBatch($validationSymbols, '1m'", 'minuteCandles'));
check('Scanner uses central stored prices', has('app/Services/PulseScannerService.php', '$this->market->latestPrices', '$this->market->scannerRows'));
check('Scanner does not fetch Binance public tickers/klines itself', !/publicTickers\(|publicKlines(?:Batch)?\(/.test(read('app/Services/PulseScannerService.php')));
check('Per-user live status uses central price store', has('app/Services/PulsePageDataService.php', 'PulseMarketDataService', 'latestPrices'));
check('Scanner supports approved 15m + 4h architecture', has('app/Services/PulseScannerService.php', "['15m', '4h']", "'15m+4h'"));
check('Scanner web/API validation restricts to 15m,4h,all', has('app/Http/Controllers/Pulse/ScannerController.php', 'in:15m,4h,all') && has('app/Http/Controllers/Api/V1/PulseController.php', 'in:15m,4h,all'));
check('Signal bundle version freezes engine build', has('app/Services/PulseScannerService.php', 'engine-14.8.17-'));
check('Active signals are explicitly immutable', has('app/Services/PulseScannerService.php', 'a generated signal is immutable', 'must not rewrite its frozen'));
check('Signal validation service exists', exists('app/Services/PulseSignalValidationService.php'));
check('Entry minute is excluded from TP/SL', has('app/Services/PulseSignalValidationService.php', 'Entry candle is intentionally excluded from TP/SL validation', 'entryMinuteMs'));
check('Same-minute TP+SL is ambiguous', has('app/Services/PulseSignalValidationService.php', "'ambiguous'", "'same_minute_tp_sl'"));
check('Validation records MFE/MAE and duration', has('app/Services/PulseSignalValidationService.php', 'mfe_r', 'mae_r', 'duration_seconds', 'highest_tp_level_hit'));
check('Detailed validation retention is configured to 7 days', has('config/pulse.php', 'PULSE_SIGNAL_VALIDATION_RETENTION_DAYS', "config('pulse.validation.detailed_retention_days',7)" ) || (read('config/pulse.php').includes('PULSE_SIGNAL_VALIDATION_RETENTION_DAYS') && read('app/Services/PulseSignalValidationService.php').includes("config('pulse.validation.detailed_retention_days',7)")));
check('Learning service exists with evidence protection', has('app/Services/PulseLearningService.php', 'prior_samples', 'established_samples', 'daily_decay', 'context_minimum_samples'));
check('Learning deduplicates identical market setup', has('app/Services/PulseLearningService.php', 'signal_fingerprint', 'must not overweight the same market setup'));
check('Learning separates strategy/version/timeframe/direction/regime', has('app/Services/PulseLearningService.php', 'strategy_version', 'timeframe', 'direction', 'market_regime'));
check('Hierarchical learning fallback exists', has('app/Services/PulseLearningService.php', 'bestState', "where('market_regime','ALL')"));
check('Permanent daily strategy metrics are not pruned', !/PulseStrategyDailyMetric::[^;]*delete\(/s.test(read('app/Services/PulseLearningService.php')));
check('Scheduler refreshes central market data every minute', has('routes/console.php', "Schedule::command('abs:pulse-market-data')->everyMinute()"));
check('Scheduler validates signals every minute', has('routes/console.php', "Schedule::command('abs:pulse-validate-signals')->everyMinute()"));
check('Scheduler rebuilds learning daily', has('routes/console.php', "Schedule::command('abs:pulse-learning')->dailyAt('00:20')"));
for (const route of ['/reports/signals','/reports/strategies','/reports/learning','/market-data/health','/market-data/prices','/signals/{signal}/validation']) {
  check(`Mobile API route ${route}`, read('routes/api.php').includes(`'${route}'`));
  check(`OpenAPI documents ${route}`, read('docs/openapi.yaml').includes(`  /pulse${route}:`));
}
check('Mobile architecture documentation exists', exists('docs/MOBILE_API_V14_8_17.md'));
check('Production architecture deployment documentation exists', exists('docs/ABS_V14_8_17_CENTRAL_PRICE_SIGNAL_INTELLIGENCE_ARCHITECTURE.md'));
check('Pulse Reports shows signal intelligence', has('resources/views/pulse/reports.blade.php', 'Signal Intelligence Validation', 'Strategy Signal Performance', 'Strategy Learning State', 'Recent Signal Validation'));
check('Market health is included in web reports', has('app/Http/Controllers/Pulse/ReportsController.php', 'PulseMarketDataService', 'marketHealth'));
check('Admin Signal Intelligence reporting is packaged', exists('resources/views/admin/pulse/intelligence.blade.php') && has('routes/web.php', "name('intelligence')") && has('app/Http/Controllers/Admin/AdminPulseController.php', 'public function intelligence'));
check('Platform daily signal quality is fingerprint-deduplicated', has('app/Services/PulseLearningService.php', 'Platform-level reporting is also deduplicated', '$globalGroups', 'signal_fingerprint'));

check('API build reports 14.8.17', has('app/Http/Controllers/Api/V1/AppController.php', "'build' => '14.8.17'"));

const failed = checks.filter(c => !c.ok);
for (const c of checks) console.log(`${c.ok ? 'PASS' : 'FAIL'}  ${c.name}`);
console.log(`\nABS V14.8.17 price architecture contract: ${checks.length - failed.length}/${checks.length} checks passed.`);
if (failed.length) process.exit(1);
