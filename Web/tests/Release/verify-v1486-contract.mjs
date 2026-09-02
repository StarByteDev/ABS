import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const failures = [];
let checks = 0;
const assert = (condition, message) => { checks += 1; if (!condition) failures.push(message); };

const build = read('BUILD_VERSION.txt');
const app = read('app/Http/Controllers/Api/V1/AppController.php');
const openapi = read('docs/openapi.yaml');
const config = read('config/pulse.php');
const scanner = read('app/Services/PulseScannerService.php');
const binance = read('app/Services/BinanceFuturesService.php');
const pageData = read('app/Services/PulsePageDataService.php');
const scannerController = read('app/Http/Controllers/Pulse/ScannerController.php');
const api = read('app/Http/Controllers/Api/V1/PulseController.php');
const scannerView = read('resources/views/pulse/scanner.blade.php');
const scannerResults = read('resources/views/pulse/partials/scanner-results.blade.php');
const scannerBottom = read('resources/views/pulse/partials/scanner-bottom.blade.php');
const settingsView = read('resources/views/pulse/settings.blade.php');
const appJs = read('public/assets/js/abs-app.js');
const planFields = read('resources/views/admin/pulse/partials/plan-fields.blade.php');
const usage = read('app/Services/PulseUsageService.php');

assert(build.includes('ABS V14.8.6'), 'Build identity is V14.8.6');
assert(app.includes("'build' => '14.8.6'"), 'Mobile bootstrap identifies V14.8.6');
assert(openapi.includes('version: 14.8.6'), 'OpenAPI identifies V14.8.6');
assert(openapi.includes('Omit to scan the complete package market universe'), 'OpenAPI documents package-wide default scanning');

assert(config.includes("PULSE_MAX_PAIRS_PER_RUN', 1000"), 'Scanner server safety ceiling defaults to 1000 markets');
assert(config.includes("PULSE_SCANNER_BATCH_CONCURRENCY', 25"), 'Bounded scanner concurrency is configured');
assert(config.includes("PULSE_SCANNER_STALE_RUN_MINUTES', 15"), 'Stale scanner release timeout is configured');
assert(scanner.includes('// Normal Run Market Scan is package-wide.'), 'Normal scanner run is package-wide');
assert(scanner.includes('$pairs = $allowed;'), 'Normal authenticated scan uses complete allowed package pair collection');
assert(!scannerView.includes('name="symbols[]"'), 'Normal web Run Market Scan no longer posts selected execution pairs');
assert(scannerController.includes("config('pulse.scanner.max_pairs_per_run', 1000)"), 'Web targeted scanner supports server safety ceiling');
assert(api.includes("config('pulse.scanner.max_pairs_per_run', 1000)"), 'Mobile targeted scanner supports server safety ceiling');

assert(binance.includes('publicKlinesBatch'), 'Binance Futures service supports concurrent kline batches');
assert(binance.includes('Http::pool'), 'Large scans use bounded HTTP pooling');
assert(binance.includes('publicTickers'), 'Scanner can enrich results with bulk Binance 24H ticker context');
assert(scanner.includes('publicKlinesBatch'), 'Scanner consumes concurrent candle batches');
assert(scanner.includes('markets_evaluated'), 'Scanner audit records successfully evaluated markets');
assert(scanner.includes('markets_unavailable'), 'Scanner audit records unavailable markets');
assert(scanner.includes('could not be evaluated for any of the'), 'All-provider-failure scan is explicitly failed');
assert(scanner.includes("where('status', 'completed')"), 'Only completed runs count toward daily scan quota');
assert(scanner.includes('stale-run timeout'), 'Interrupted running scans are automatically released');
assert(usage.includes("where('status', 'completed')"), 'Usage counters exclude failed/running scans');

assert(scanner.includes('$maximumWeightedScore'), 'Strategy score normalizer computes plan-specific maximum score');
assert(scanner.includes('100 / $maximumWeightedScore'), 'Strategy results are normalized to a 0–100 scale');
assert(scanner.includes('Reaching the daily signal allowance stops new signal'), 'Signal quota no longer stops package market evaluation');

assert(pageData.includes('$runSummary = collect($latestRun?->summary'), 'Scanner UI reads latest scan evaluation summary');
assert(pageData.includes("'Below Filter'"), 'Below-threshold evaluated candidates are explicitly labelled');
assert(pageData.includes("'No Setup'"), 'Neutral evaluated candidates are explicitly labelled');
assert(pageData.includes('$qualifiedRows->isNotEmpty() ? $qualifiedRows : $baseRows'), 'Scanner shows strongest evaluated candidates when no setup clears filters');
assert(scannerResults.includes('evaluated results'), 'Results table identifies scan evaluations instead of only signals');
assert(scannerResults.includes('No Signal'), 'Evaluated candidates without a created signal cannot link to a nonexistent signal');
assert(scannerBottom.includes("$page['pairs_monitored']"), 'Saved scanner view reports package market count');

assert(settingsView.includes('data-market-select-all'), 'User Trading Markets selector includes Select All');
assert(settingsView.includes('execution/watch markets'), 'Selected pairs are explained as execution/watch selection');
assert(appJs.includes("[data-market-select-all]"), 'User Select All behavior is implemented');
assert(appJs.includes('Run Market Scan still evaluates all'), 'Select All feedback preserves package-wide scanner explanation');
assert(planFields.includes('data-plan-pair-select-all'), 'Admin package pair picker includes Select All');
assert(planFields.includes('data-plan-pair-clear-all'), 'Admin package pair picker includes Clear All');
assert(appJs.includes("[data-plan-pair-select-all]"), 'Admin Select All behavior is implemented');
assert(appJs.includes("[data-plan-pair-clear-all]"), 'Admin Clear All behavior is implemented');

assert(fs.existsSync(path.join(root, 'docs/ABS_V14_8_6_FULL_PACKAGE_SCANNER.md')), 'V14.8.6 scanner guide is packaged');
assert(fs.existsSync(path.join(root, 'docs/MOBILE_API_V14_8_6.md')), 'V14.8.6 mobile API notes are packaged');

if (failures.length) {
  console.error(`ABS V14.8.6 contract: FAIL (${failures.length}/${checks})`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log(`ABS V14.8.6 contract: PASS (${checks} checks)`);
