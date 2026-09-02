import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const failures = [];
let checks = 0;
const assert = (condition, message) => { checks += 1; if (!condition) failures.push(message); };
const versionAtLeast = (actual, minimum) => {
  const a = String(actual || '').match(/\d+/g)?.map(Number) || [];
  const b = String(minimum || '').match(/\d+/g)?.map(Number) || [];
  for (let i = 0; i < Math.max(a.length, b.length); i += 1) {
    const av = a[i] || 0; const bv = b[i] || 0;
    if (av !== bv) return av > bv;
  }
  return true;
};
const currentApiBuild = read('app/Http/Controllers/Api/V1/AppController.php').match(/'build'\s*=>\s*'([^']+)'/)?.[1];
const currentOpenApiBuild = read('docs/openapi.yaml').match(/version:\s*([^\s]+)/)?.[1];
const currentBuildVersion = read('BUILD_VERSION.txt').match(/V(\d+\.\d+\.\d+)/)?.[1];

// Preserve the production V14.8.2 platform contract.
const migration = read('database/migrations/2026_08_25_000810_ensure_canonical_strategy_catalog.php');
const canonicalSlugs = [
  'trend-alignment','ema-crossover','rsi-recovery','macd-momentum','breakout-confirmation',
  'volume-expansion','atr-volatility','market-structure','trend-pullback','bollinger-reversion',
  'momentum-continuation','range-compression','candle-strength','swing-sequence','risk-reward-quality',
];
assert(canonicalSlugs.length === 15, 'Canonical strategy catalog remains exactly 15 engines');
for (const slug of canonicalSlugs) assert(migration.includes(`'${slug}'`), `Canonical strategy remains packaged: ${slug}`);
const releaseService = read('app/Services/ApplicationReleaseService.php');
assert(releaseService.includes('createRestorePoint'), 'Production restore-point manager remains present');
assert(releaseService.includes('synchronizeApplication'), 'Production exact application synchronization remains present');
assert(releaseService.includes('restoreRestorePoint'), 'Production rollback remains present');
assert(read('app/Services/ApplicationBackupService.php').includes('database.sql'), 'Production database backup remains present');
assert(read('app/Services/BinanceFuturesService.php').includes('PERPETUAL'), 'Binance perpetual Futures catalog remains present');
assert(read('app/Services/PulseTradeService.php').includes('pairAccess->isAllowed'), 'Trade execution still enforces plan pair access');

// Build/API identity.
assert(versionAtLeast(currentBuildVersion, '14.8.3'), 'Build identity remains at or above V14.8.3');
assert(versionAtLeast(currentApiBuild, '14.8.3'), 'Mobile bootstrap remains at or above V14.8.3');
assert(versionAtLeast(currentOpenApiBuild, '14.8.3'), 'OpenAPI remains at or above V14.8.3');
assert(read('routes/api.php').includes("Route::get('/usage'"), 'Mobile API exposes Pulse usage endpoint');
assert(read('docs/openapi.yaml').includes('/pulse/usage:'), 'OpenAPI documents Pulse usage endpoint');

// Async scanner: no full-page refresh after Run Market Scan.
const webRoutes = read('routes/web.php');
const scannerController = read('app/Http/Controllers/Pulse/ScannerController.php');
const scannerView = read('resources/views/pulse/scanner.blade.php');
const premiumJs = read('public/assets/js/pulse-premium.js');
assert(webRoutes.includes("Route::get('/scanner/refresh'"), 'Scanner has a fragment-refresh endpoint');
assert(scannerController.includes("'metrics_html' => view('pulse.partials.scanner-metrics'"), 'Scanner refresh returns metrics fragment');
assert(scannerController.includes("'results_html' => view('pulse.partials.scanner-results'"), 'Scanner refresh returns results fragment');
assert(scannerController.includes("'bottom_html' => view('pulse.partials.scanner-bottom'"), 'Scanner refresh returns quality/bottom fragment');
assert(scannerView.includes('data-run-market-scan'), 'Run Market Scan is AJAX-enabled');
assert(premiumJs.includes("event.preventDefault();") && premiumJs.includes("fetch(scanRunForm.action"), 'Market Scan submits asynchronously');
assert(premiumJs.includes("replaceScanFragment('metrics'") && premiumJs.includes("replaceScanFragment('results'") && premiumJs.includes("replaceScanFragment('bottom'"), 'Only scanner result fragments are replaced after a scan');

// Logout/account UX and Page Expired protection.
const layout = read('resources/views/pulse/layout.blade.php');
assert(layout.includes('pulse-account-menu'), 'Premium signed-in account menu exists');
assert(layout.includes('Sign Out'), 'Premium account menu contains Sign Out');
assert(webRoutes.includes("Route::match(['GET', 'POST'], '/logout'"), 'Logout supports safe GET plus backward-compatible POST');
assert(read('bootstrap/app.php').includes("validateCsrfTokens(except: ['logout'])"), 'Stale POST logout is exempted from CSRF expiry to prevent 419');
assert(read('resources/views/partials/header.blade.php').includes("route('logout')"), 'Public/home header uses the safe logout route');

// Plan quotas.
const usage = read('app/Services/PulseUsageService.php');
assert(usage.includes("where('status', 'completed')"), 'Only completed scanner runs consume daily scan quota');
assert(usage.includes('signals_per_day'), 'Signal allowance derives from the user plan');
assert(layout.includes('data-usage-scans') && layout.includes('data-usage-signals'), 'Signed-in shell displays scans and signals remaining');
assert(usage.includes("where('status', 'completed')") && read('app/Services/PulseScannerService.php').includes("where('status', 'running')"), 'Scanner quota excludes failed runs while separately blocking concurrent active scans');

// Long/Short ratio false-zero correction.
const appJs = read('public/assets/js/abs-app.js');
assert(appJs.includes("value !== null && value !== undefined && value !== '' && value !== false"), 'Client numeric parser does not treat null as zero');
assert(appJs.includes('Number(global.long_short_ratio) > 0') && appJs.includes('Number(global.long_short_ratio) <= 100'), 'Homepage only renders a valid Long/Short ratio');
assert(read('app/Services/BinanceDerivativesMarketService.php').includes('longAccount') && read('app/Services/BinanceDerivativesMarketService.php').includes('shortAccount'), 'Binance ratio service can derive ratio from account shares');

// 50-hour pair-selection lock in web + mobile.
assert(read('config/pulse.php').includes("PULSE_PAIR_CHANGE_LOCK_HOURS', 50"), 'Default pair-selection cooldown is 50 hours');
assert(fs.existsSync(path.join(root, 'database/migrations/2026_08_25_000820_add_pair_selection_lock.php')), 'Pair-selection cooldown migration is packaged');
const lockService = read('app/Services/PulsePairSelectionLockService.php');
assert(lockService.includes('pair_selection_locked_until') && lockService.includes('addHours($hours)'), 'Changed pair selection records a future lock');
assert(read('app/Http/Controllers/Pulse/SettingsController.php').includes('guardAndAttributes'), 'Web Settings enforces pair cooldown');
const apiController = read('app/Http/Controllers/Api/V1/PulseController.php');
assert(apiController.includes("], 423)"), 'Mobile API returns 423 when pair selection is locked');
assert(apiController.includes('pair_selection_lock'), 'Mobile Settings returns pair-lock status');
assert(read('resources/views/pulse/settings.blade.php').includes('Market selection locked'), 'Settings communicates the pair lock to users');

if (failures.length) {
  console.error(`ABS V14.8.3 contract: FAIL (${failures.length}/${checks})`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log(`ABS V14.8.3 contract: PASS (${checks} checks)`);
console.log('Verified: async scanner, safe logout, plan quotas, valid Long/Short ratio, 50-hour pair cooldown, V14.8.2 production baseline.');
