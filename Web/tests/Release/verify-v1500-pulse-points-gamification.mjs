import fs from 'node:fs';

const read = (file) => fs.readFileSync(file, 'utf8');
const must = (condition, message) => {
  if (!condition) {
    console.error('FAIL:', message);
    process.exitCode = 1;
  } else {
    console.log('PASS:', message);
  }
};

const version = read('VERSION.txt');
const build = read('BUILD_VERSION.txt');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const apiRoutes = read('routes/api.php');
const webRoutes = read('routes/web.php');
const scanner = read('app/Services/PulseScannerService.php');
const points = read('app/Services/PulsePointService.php');
const gamification = read('app/Services/PulseGamificationService.php');
const ledger = read('app/Models/PulsePointLedger.php');
const adminPoints = read('app/Http/Controllers/Admin/AdminPulsePointsController.php');
const migration = read('database/migrations/2026_09_03_000900_add_abs_v15_pulse_points_gamification.php');
const repair = read('app/Support/PulseSchemaRepair.php');
const sql = read('database/ABS_V15_0_0_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql');
const openapi = read('docs/openapi.yaml');
const settingsView = read('resources/views/pulse/settings.blade.php');
const scannerView = read('resources/views/pulse/scanner.blade.php');
const adminView = read('resources/views/admin/pulse/points.blade.php');
const services = read('config/services.php');
const webPointsController = read('app/Http/Controllers/Pulse/PointsController.php');
const apiPulseController = read('app/Http/Controllers/Api/V1/PulseController.php');

must(version.includes('ABS V15.0.0'), 'VERSION identifies ABS V15.0.0');
must(build.includes('Pulse Points, Gamification & Best Signal Build'), 'build identity names the V15 release');
must(bootstrap.includes("'build' => '15.0.0'"), 'mobile bootstrap identifies V15.0.0');
must(bootstrap.includes("'pulse_points'") && bootstrap.includes("'pulse_gamification'") && bootstrap.includes("'signal_sharing'") && bootstrap.includes("'ai_signal_explanations'"), 'mobile bootstrap advertises V15 modules');

for (const fragment of ["'/pulse/points'", "'/pulse/points/purchases'", "'/pulse/points/check-in'", "'/pulse/points/plans/{plan}/activate'", "'/signals/{signal}/share'", "'/signals/{signal}/explain'"]) {
  must(apiRoutes.includes(fragment), `V15 mobile API route present: ${fragment}`);
}
must(webRoutes.includes("->name('pulse.points.index')") && webRoutes.includes("->name('pulse.points.purchase')") && webRoutes.includes("->name('pulse.points.check-in')"), 'member Pulse Points web routes are present');
must(webRoutes.includes("Route::prefix('pulse')->name('pulse.')->group") && webRoutes.includes("->name('points.purchases.approve')"), 'Admin Pulse Points routes are present');
const webPointsPos = webRoutes.indexOf("Route::get('/pulse/points'");
const webPulseAccessPos = webRoutes.indexOf("Route::middleware('pulse.access')");
must(webPointsPos !== -1 && webPulseAccessPos !== -1 && webPointsPos < webPulseAccessPos, 'web PP economy is available before Pulse-plan middleware');
const apiPointsPos = apiRoutes.indexOf("Route::get('/pulse/points'");
const apiPulseAccessPos = apiRoutes.indexOf("Route::middleware(['pulse.access', 'pulse.capability:mobile_api'])");
must(apiPointsPos !== -1 && apiPulseAccessPos !== -1 && apiPointsPos < apiPulseAccessPos, 'mobile PP economy is available before Pulse-plan middleware');

must(scanner.includes("if ($user) $timeframe = 'all';"), 'signed-in scanner forces automatic 15M + 4H evaluation');
must(scanner.includes("$pairs = $allowed->values();") && scanner.includes('Admin/package market access is authoritative'), 'scanner uses Admin/package market universe instead of user pair selection');
must(!scanner.includes("settings?->selected_pairs"), 'scanner no longer reads user-selected pairs');
must(scanner.includes('qualifiedCandidates') && scanner.includes('usort($qualifiedCandidates'), 'scanner ranks all qualified candidates before choosing Best Signal');
must(scanner.includes("'best_signal'" ) && scanner.includes("'best-signal:run:'"), 'Best Signal PP debit is tied idempotently to scanner run');
must(scanner.includes("'points_charged' => $pointsCharged"), 'scanner persists PP charged on the run');
must(scanner.includes('No Admin/package-approved Pulse markets'), 'scanner error wording reflects Admin-controlled markets');
must(scanner.includes('completed_with_warning'), 'post-unlock side-effect failure cannot invalidate a paid Best Signal');
must(scanner.includes("? ['symbol' => $symbol, 'timeframe' => $scanTimeframe, 'status' => 'evaluated']") && scanner.includes(': $analysis;'), 'signed-in scanner summaries sanitize unpaid candidate technical details');

const strategySlugs = [
  'trend-alignment','ema-crossover','rsi-recovery','macd-momentum','breakout-confirmation',
  'volume-expansion','atr-volatility','market-structure','trend-pullback','bollinger-reversion',
  'momentum-continuation','range-compression','candle-strength','swing-sequence','risk-reward-quality'
];
for (const slug of strategySlugs) must(scanner.includes(`'${slug}'`), `existing strategy engine retained: ${slug}`);

must(points.includes("lockForUpdate()"), 'PP wallet updates are row-locked');
must(points.includes("where('idempotency_key', $idempotencyKey)"), 'PP ledger enforces transaction idempotency');
must(points.includes('Not enough Pulse Points for this action.'), 'PP debit blocks negative balances');
must(webPointsController.includes('wasRecentlyCreated') && apiPulseController.includes('wasRecentlyCreated'), 'web and mobile PP plan activation retries cannot extend access twice');
must(apiPulseController.includes("'missions' => $missions") && apiPulseController.includes("'achievements' => $achievements") && apiPulseController.includes("'plans' => $plans"), 'mobile PP summary includes missions, achievements, and PP-eligible plans');
must(ledger.includes('ledger entries are immutable') && ledger.includes('static::updating') && ledger.includes('static::deleting'), 'PP ledger model is immutable');

must(gamification.includes("reward_type', 'daily_checkin'") && gamification.includes('pulse_streak_days'), 'daily check-in and streak progression are implemented');
must(gamification.includes('PulseMission') && gamification.includes('PulseAchievement'), 'missions and achievements are implemented');
must(gamification.includes("rewarded_ads_enabled") && gamification.includes('hash_hmac') && gamification.includes('hash_equals'), 'rewarded-ad PP requires server-side HMAC verification');
must(services.includes("'rewarded_ads'") && services.includes("'secret' => env('PULSE_REWARDED_AD_SECRET'"), 'rewarded-ad verification secret is server configuration only');

must(adminPoints.includes("'point-purchase:'") && adminPoints.includes('credited_at'), 'Admin purchase approval has exactly-once PP credit marker');
must(adminPoints.includes('lockForUpdate()'), 'Admin purchase review locks the purchase row');
must((adminPoints.match(/lockForUpdate\(\)/g) || []).length >= 2, 'Admin approve and reject paths both lock PP purchase rows');
must(webPointsController.includes('cancelPurchase') && webPointsController.includes("PulsePointPurchase::query()->whereKey($purchase->id)->lockForUpdate()"), 'member PP purchase cancellation is race-safe against Admin review');
must(adminView.includes('Pulse Points') && adminView.includes('USDT verification queue'), 'Admin economy UI is present');
must(scannerView.includes('Find Best Signal') && scannerView.includes('0 PP if no signal qualifies'), 'member scanner communicates one-click PP contract');
must(settingsView.includes('Admin controlled') && settingsView.includes('15M + 4H automatic'), 'member settings expose scanner controls as Admin-managed');

must(migration.includes("Schema::create('pulse_point_wallets'") && migration.includes("Schema::create('pulse_point_ledger'"), 'V15 migration creates PP wallet and immutable ledger tables');
must(migration.includes('v15_points_defaults_seeded') && migration.includes('insertOrIgnore'), 'V15 defaults are repeat-repair safe and do not overwrite Admin choices');
must(repair.includes('2026_09_03_000900_add_abs_v15_pulse_points_gamification.php'), 'protected schema repair includes V15 migration');

must(sql.includes('CREATE TABLE IF NOT EXISTS `pulse_point_wallets`'), 'phpMyAdmin V15 repair creates missing PP tables');
must(sql.includes("column_name = p_column") && sql.includes('ADD COLUMN'), 'phpMyAdmin V15 repair adds only missing V15 columns');
must(sql.includes('v15_points_defaults_seeded'), 'phpMyAdmin plan PP defaults are one-time initialized');
must(!/\bDROP\s+TABLE\b/i.test(sql), 'phpMyAdmin V15 repair contains no DROP TABLE');
must(!/\bTRUNCATE\b/i.test(sql), 'phpMyAdmin V15 repair contains no TRUNCATE');
must(!/\bDELETE\s+FROM\b/i.test(sql), 'phpMyAdmin V15 repair contains no DELETE FROM');
must(!/ALTER\s+TABLE[^;]+\b(MODIFY|CHANGE|DROP)\b/i.test(sql), 'phpMyAdmin V15 repair never modifies/drops existing columns');

for (const path of ['/pulse/points:', '/pulse/points/purchases:', '/pulse/points/check-in:', '/pulse/points/plans/{plan}/activate:', '/pulse/signals/{signal}/share:', '/pulse/signals/{signal}/explain:']) {
  must(openapi.includes(path), `OpenAPI documents V15 endpoint ${path}`);
}
must(openapi.includes('version: 15.0.0'), 'OpenAPI identifies V15.0.0');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.0 Pulse Points / Gamification / Best Signal release contract verified.');
