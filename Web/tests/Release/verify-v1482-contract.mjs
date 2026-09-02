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

const migration = read('database/migrations/2026_08_25_000810_ensure_canonical_strategy_catalog.php');
const canonicalSlugs = [
  'trend-alignment','ema-crossover','rsi-recovery','macd-momentum','breakout-confirmation',
  'volume-expansion','atr-volatility','market-structure','trend-pullback','bollinger-reversion',
  'momentum-continuation','range-compression','candle-strength','swing-sequence','risk-reward-quality',
];
for (const slug of canonicalSlugs) assert(migration.includes(`'${slug}'`), `Canonical strategy missing from production migration: ${slug}`);
assert(canonicalSlugs.length === 15, 'Canonical strategy contract is exactly 15 engines');

const pairMigration = read('database/migrations/2026_08_25_000800_add_plan_pair_scope_and_release_management.php');
assert(pairMigration.includes("pair_access_mode"), 'Plan pair access mode migration exists');
assert(pairMigration.includes("pulse_plan_pairs"), 'Plan/pair assignment pivot migration exists');

const pairAccess = read('app/Services/PulsePairAccessService.php');
assert(pairAccess.includes("pair_access_mode"), 'Pair access service enforces plan market scope');
const scanner = read('app/Services/PulseScannerService.php');
assert(scanner.includes('pairAccess->allowedPairs'), 'Scanner only uses plan-allowed markets');
const trading = read('app/Services/PulseTradeService.php');
assert(trading.includes('pairAccess->isAllowed'), 'Trade execution rechecks package market access server-side');

const binance = read('app/Services/BinanceFuturesService.php');
assert(binance.includes("contractType") && binance.includes("PERPETUAL"), 'Binance sync restricts catalog to perpetual Futures');
assert(binance.includes("status") && binance.includes("TRADING"), 'Binance sync restricts catalog to actively trading markets');
assert(binance.includes('supported_quote_assets'), 'Binance sync uses configurable Futures quote assets');
assert(binance.includes("whereNotIn('symbol', $activeSymbols)"), 'Stale exchange markets are disabled after synchronization');
assert(read('config/pulse.php').includes("USDT,USDC"), 'Default Binance quote catalog includes USDT and USDC');

const userApi = read('routes/api.php');
assert(userApi.includes("'pulse.capability:mobile_api'"), 'Pulse mobile APIs enforce the package mobile_api capability');
assert(userApi.includes("/binance/connections/{connection}/activate"), 'Mobile API exposes Testnet/Live connection activation');
const pulseApiController = read('app/Http/Controllers/Api/V1/PulseController.php');
assert(pulseApiController.includes("locked_by_plan"), 'Mobile strategy catalog exposes locked-by-plan state');
const adminApi = read('app/Http/Controllers/Api/V1/AdminPulseController.php');
assert(adminApi.includes('syncPairsForPlan'), 'Admin API supports package pair assignments');
assert(adminApi.includes("with(['strategies','pairs'])"), 'Admin API returns both plan strategy and market assignments');

const releaseRoutes = read('routes/web.php');
for (const marker of ['updates.upload','updates.restore-point','updates.install','updates.restore']) {
  assert(releaseRoutes.includes(marker), `Admin production release route exists: ${marker}`);
}
const releaseService = read('app/Services/ApplicationReleaseService.php');
assert(releaseService.includes('createRestorePoint'), 'Release manager creates full restore points');
assert(releaseService.includes('automatic pre-upgrade backup'), 'Release manager creates automatic pre-upgrade checkpoint');
assert(releaseService.includes('synchronizeApplication'), 'Release/rollback synchronize managed application files');
assert(releaseService.includes("state-backup.zip"), 'Restore point contains database/uploads state backup');
assert(releaseService.includes('restoreRestorePoint'), 'Manual and automatic rollback service exists');
assert(releaseService.includes("$this->synchronizeApplication($work.'/application')"), 'Rollback restores the exact managed application snapshot');
const backupService = read('app/Services/ApplicationBackupService.php');
assert(backupService.includes('database.sql'), 'Restore state includes MySQL SQL export');
assert(backupService.includes("storage_path('app/public')"), 'Restore state includes uploaded application files');
assert(backupService.includes('$replaceUploads'), 'Full rollback can restore uploads exactly to the checkpoint');

const openApi = read('docs/openapi.yaml');
assert(openApi.includes('/pulse/binance/connections/{connection}/activate:'), 'OpenAPI documents Binance environment activation');
assert(versionAtLeast(currentApiBuild, '14.8.2'), 'Mobile bootstrap remains at or above V14.8.2');

for (const errorView of ['resources/views/errors/500.blade.php','resources/views/errors/503.blade.php']) {
  assert(fs.existsSync(path.join(root, errorView)), `Branded production error view exists: ${errorView}`);
}

if (failures.length) {
  console.error(`ABS V14.8.2 contract: FAIL (${failures.length}/${checks})`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log(`ABS V14.8.2 contract: PASS (${checks} checks)`);
console.log('Verified: 15 strategies, plan markets, Futures sync/execution, Mobile API parity, production backups/rollback.');
