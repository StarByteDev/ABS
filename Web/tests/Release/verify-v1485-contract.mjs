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

const partial = read('resources/views/pulse/partials/scanner-bottom.blade.php');
const controller = read('app/Http/Controllers/Pulse/ScannerController.php');
const pageData = read('app/Services/PulsePageDataService.php');

assert(versionAtLeast(read('BUILD_VERSION.txt').match(/V(\d+\.\d+\.\d+)/)?.[1], '14.8.5'), 'Build identity remains at or above V14.8.5');
assert(versionAtLeast(read('app/Http/Controllers/Api/V1/AppController.php').match(/'build'\s*=>\s*'([^']+)'/)?.[1], '14.8.5'), 'Mobile bootstrap remains at or above V14.8.5');
assert(versionAtLeast(read('docs/openapi.yaml').match(/version:\s*([^\s]+)/)?.[1], '14.8.5'), 'OpenAPI remains at or above V14.8.5');
assert(partial.includes("$filters = (array) ($page['filters'] ?? [])"), 'Scanner bottom fragment derives filters from page payload');
assert(partial.includes("$minimumScore = (float) ($filters['min_score'] ?? 70)"), 'Scanner bottom fragment has a safe score default');
assert(partial.includes('number_format($minimumScore, 0)'), 'Saved scanner view uses normalized score');
assert(controller.includes("'filters' => $page['filters'] ?? []"), 'Async refresh explicitly supplies filter context');
assert(pageData.includes("'filters' => ["), 'Scanner page service returns canonical filter state');
assert(controller.includes("'bottom_html' => view('pulse.partials.scanner-bottom'"), 'Async refresh still returns scanner bottom fragment');
assert(read('docs/ABS_V14_8_5_SCANNER_FRAGMENT_HOTFIX.md').includes('`$filters` variable'), 'Hotfix documentation is packaged');

// Preserve key V14.8.4 and V14.8.3 requirements.
assert(read('app/Support/PulseSchemaRepair.php').includes("'pair_selection_locked_until'"), 'Database self-repair still includes pair lock schema');
assert(read('app/Services/PulseMembershipService.php').includes('nextUpgradePlan'), 'Next-tier package UX remains present');
assert(read('public/assets/js/pulse-premium.js').includes('fetch(scanRunForm.action'), 'Market scan remains asynchronous');
assert(read('config/pulse.php').includes("PULSE_PAIR_CHANGE_LOCK_HOURS', 50"), '50-hour pair lock remains configured');
const strategyMigration = read('database/migrations/2026_08_25_000810_ensure_canonical_strategy_catalog.php');
const strategySlugs = ['trend-alignment','ema-crossover','rsi-recovery','macd-momentum','breakout-confirmation','volume-expansion','atr-volatility','market-structure','trend-pullback','bollinger-reversion','momentum-continuation','range-compression','candle-strength','swing-sequence','risk-reward-quality'];
assert(strategySlugs.length === 15 && strategySlugs.every(slug => strategyMigration.includes(`'${slug}'`)), '15-strategy catalog remains packaged');
assert(read('app/Services/BinanceFuturesService.php').includes('PERPETUAL'), 'Binance perpetual Futures catalog remains present');
assert(read('app/Services/ApplicationReleaseService.php').includes('restoreRestorePoint($restorePoint'), 'Admin rollback remains present');

if (failures.length) {
  console.error(`ABS V14.8.5 contract: FAIL (${failures.length}/${checks})`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log(`ABS V14.8.5 contract: PASS (${checks} checks)`);
