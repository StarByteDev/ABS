import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const exists = file => fs.existsSync(path.join(root, file));
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

// Build/API identity.
assert(versionAtLeast(currentBuildVersion, '14.8.4'), 'Build identity remains at or above V14.8.4');
assert(versionAtLeast(currentApiBuild, '14.8.4'), 'Mobile bootstrap remains at or above V14.8.4');
assert(versionAtLeast(currentOpenApiBuild, '14.8.4'), 'OpenAPI remains at or above V14.8.4');
assert(exists('docs/MOBILE_API_V14_8_4.md'), 'V14.8.4 Mobile API notes are packaged');

// Preserve V14.8.3 scanner/logout/quota/pair-lock behavior.
const webRoutes = read('routes/web.php');
const scannerController = read('app/Http/Controllers/Pulse/ScannerController.php');
const scannerView = read('resources/views/pulse/scanner.blade.php');
const premiumJs = read('public/assets/js/pulse-premium.js');
const layout = read('resources/views/pulse/layout.blade.php');
assert(webRoutes.includes("Route::get('/scanner/refresh'"), 'Scanner fragment-refresh endpoint remains present');
assert(scannerController.includes("'metrics_html' => view('pulse.partials.scanner-metrics'"), 'Scanner refresh returns metrics fragment');
assert(scannerController.includes("'results_html' => view('pulse.partials.scanner-results'"), 'Scanner refresh returns results fragment');
assert(scannerController.includes("'bottom_html' => view('pulse.partials.scanner-bottom'"), 'Scanner refresh returns bottom fragment');
assert(scannerView.includes('data-run-market-scan'), 'Market scan remains AJAX-enabled');
assert(premiumJs.includes('fetch(scanRunForm.action'), 'Market scan remains asynchronous');
assert(layout.includes('pulse-account-menu') && layout.includes('Sign Out'), 'Premium account menu and logout remain present');
assert(webRoutes.includes("Route::match(['GET', 'POST'], '/logout'"), 'Safe logout route remains present');
assert(read('bootstrap/app.php').includes("validateCsrfTokens(except: ['logout'])"), 'Stale logout POST remains protected from 419');
assert(layout.includes('data-usage-scans') && layout.includes('data-usage-signals'), 'Plan usage counters remain visible');
assert(read('config/pulse.php').includes("PULSE_PAIR_CHANGE_LOCK_HOURS', 50"), '50-hour pair-selection cooldown remains configured');
assert(read('app/Services/PulsePairSelectionLockService.php').includes('pair_selection_locked_until'), 'Pair lock remains server-enforced');
assert(read('app/Support/PulseSchemaRepair.php').includes("'pair_selection_saved_at'"), 'Schema repair knows pair-selection saved timestamp');
assert(read('app/Support/PulseSchemaRepair.php').includes("'pair_selection_locked_until'"), 'Schema repair knows pair-selection lock timestamp');

// Package-aware current/next-tier UX.
const membership = read('app/Services/PulseMembershipService.php');
assert(membership.includes('nextUpgradePlan'), 'Membership service computes next upgrade tier');
assert(membership.includes('upgradePathPlans'), 'Membership service filters upgrade path');
assert(membership.includes("'is_current_plan'"), 'Mobile plan payload exposes current-plan state');
assert(membership.includes("'is_next_upgrade'"), 'Mobile plan payload exposes next-upgrade state');
assert(membership.includes("'subscription_state'"), 'Mobile plan payload exposes subscription state');
assert(layout.includes("'NEXT TIER'"), 'Sidebar marks the next higher tier');
assert(layout.includes("'Upgrade to '"), 'Sidebar promotes the next higher package');
const planCard = read('resources/views/pulse/partials/membership-plan-card.blade.php');
assert(planCard.includes('CURRENT PLAN · ACTIVE'), 'Current package card is visibly active');
assert(planCard.includes('NEXT TIER · RECOMMENDED UPGRADE'), 'Next package card is highlighted as recommended upgrade');
assert(read('resources/views/pulse/plans.blade.php').includes('Your current plan and upgrade path'), 'Plans page presents current/upgrade path');
assert(read('resources/views/pulse/membership/index.blade.php').includes('Current package and available upgrades'), 'Membership page presents current/upgrade path');
const pulseApi = read('app/Http/Controllers/Api/V1/PulseController.php');
assert(pulseApi.includes("'next_upgrade_plan'"), 'Mobile Pulse API exposes next upgrade plan');

// Self-healing database repair.
const schema = read('app/Support/AbsSchemaRepair.php');
const pulseSchema = read('app/Support/PulseSchemaRepair.php');
const maintenance = read('app/Http/Controllers/Admin/AdminMaintenanceController.php');
const maintenanceView = read('resources/views/admin/enterprise/maintenance.blade.php');
const middleware = read('app/Http/Middleware/EnsureApplicationInstalled.php');
assert(schema.includes('public static function diagnosisCacheKey'), 'Schema diagnostics expose a required-schema signature cache key');
assert(schema.includes('PulseSchemaRepair::repair()'), 'ABS schema repair includes Pulse schema repair');
assert(schema.includes('PulseSchemaRepair::REQUIRED_SCHEMA'), 'Final schema diagnostics include Pulse requirements');
assert(pulseSchema.includes("'pulse_user_settings'"), 'Pulse required-schema map includes user settings');
assert(maintenance.includes("if ($action === 'repair_schema')"), 'Admin Database Fix repair action exists');
assert(maintenance.includes('AbsSchemaRepair::repair()'), 'Database Fix reconciles required schema');
assert(maintenance.includes('LegacyMigrationBaseline::synchronize()'), 'Database Fix baselines satisfied legacy migrations');
assert(maintenance.includes("$this->callArtisan('migrate'"), 'Database Fix runs pending migrations');
assert(maintenance.includes("$this->callArtisan('db:seed'"), 'Database Fix synchronizes reviewed baseline records');
assert(maintenance.includes("$this->callArtisan('optimize:clear'"), 'Database Fix clears Laravel caches');
assert(maintenance.includes("'Final schema verification: READY.'"), 'Database Fix performs final READY verification');
assert(maintenanceView.includes('Database Fix — Repair Everything'), 'Premium Admin Database Fix UI exists');
assert(maintenanceView.includes('Create Backup & Run Database Fix'), 'Database Fix explicitly creates safety backup');
assert(middleware.includes('AbsSchemaRepair::diagnosisCacheKey()'), 'Readiness middleware uses schema-signature cache key');
assert(middleware.includes('admin/enterprise/maintenance'), 'Database Maintenance remains reachable during schema drift');
assert(read('app/Http/Controllers/RecoveryController.php').includes('AbsSchemaRepair::diagnosisCacheKey()'), 'Recovery clears the current schema-signature cache entry');

// Production upgrade safety.
const release = read('app/Services/ApplicationReleaseService.php');
assert(release.includes('automatic pre-upgrade backup'), 'Release installer creates pre-upgrade restore point');
assert(release.includes('AbsSchemaRepair::repair()'), 'Release installer reconciles schema before migrations');
assert(release.includes("runArtisanOrFail('migrate'"), 'Release installer fails hard on migration failure');
assert(release.includes("runArtisanOrFail('db:seed'"), 'Release installer synchronizes baseline records');
assert(release.includes("runArtisanOrFail('optimize:clear'"), 'Release installer fails hard on cache-clear failure');
assert(release.includes("'baseline_content_synchronized' => true"), 'Release result reports baseline synchronization');
assert(release.includes('restoreRestorePoint($restorePoint'), 'Failed release automatically rolls back');
assert(release.includes("$this->synchronizeApplication($work.'/application')"), 'Rollback restores exact managed application snapshot');
assert(read('app/Services/ApplicationBackupService.php').includes('database.sql'), 'Restore point includes database backup');

// Production seed safety: no silent default admin creation on live repair.
const seeder = read('database/seeders/DatabaseSeeder.php');
assert(seeder.includes("env('ABS_ADMIN_EMAIL', '')"), 'Production seeder requires explicit configured admin email for creation');
assert(seeder.includes("app()->environment('production')"), 'Seeder distinguishes production admin safety');
assert(seeder.includes('ABS_ADMIN_PASSWORD must be configured'), 'Seeder requires password before creating a new production admin');
assert(seeder.includes('private function seedPulse(?User $admin)'), 'Pulse baseline can synchronize safely even without manufacturing an admin');

// Preserve 15 strategies and Binance Futures/mobile production baseline.
const strategyMigration = read('database/migrations/2026_08_25_000810_ensure_canonical_strategy_catalog.php');
const canonicalSlugs = [
  'trend-alignment','ema-crossover','rsi-recovery','macd-momentum','breakout-confirmation',
  'volume-expansion','atr-volatility','market-structure','trend-pullback','bollinger-reversion',
  'momentum-continuation','range-compression','candle-strength','swing-sequence','risk-reward-quality',
];
assert(canonicalSlugs.length === 15, 'Canonical strategy catalog remains exactly 15 engines');
for (const slug of canonicalSlugs) assert(strategyMigration.includes(`'${slug}'`), `Canonical strategy remains packaged: ${slug}`);
assert(read('app/Services/BinanceFuturesService.php').includes('PERPETUAL'), 'Binance Futures catalog remains perpetual-only');
assert(read('app/Services/PulseTradeService.php').includes('pairAccess->isAllowed'), 'Trade execution still enforces package market access');
assert(read('routes/api.php').includes("'pulse.capability:mobile_api'"), 'Mobile Pulse APIs still enforce mobile_api package access');

if (failures.length) {
  console.error(`ABS V14.8.4 contract: FAIL (${failures.length}/${checks})`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log(`ABS V14.8.4 contract: PASS (${checks} checks)`);
console.log('Verified: current/next-tier UX, self-healing Database Fix, production-safe seeding/upgrades, V14.8.3 behavior and V14.8.2 baseline.');
