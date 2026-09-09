import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => {
    if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
    else console.log(`PASS: ${message}`);
};
const mustNot = (source, needle, message) => must(!source.includes(needle), message);

const version = read('VERSION.txt');
const buildVersion = read('BUILD_VERSION.txt');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const scanner = read('app/Services/PulseScannerService.php');
const usage = read('app/Services/PulseUsageService.php');
const plan = read('app/Models/PulsePlan.php');
const membershipService = read('app/Services/PulseMembershipService.php');
const schemaRepair = read('app/Support/PulseSchemaRepair.php');
const layout = read('resources/views/pulse/layout.blade.php');
const planCard = read('resources/views/pulse/partials/membership-plan-card.blade.php');
const adminPlan = read('resources/views/admin/pulse/partials/plan-fields.blade.php');
const pointsView = read('resources/views/pulse/points/index.blade.php');
const js = read('public/assets/js/pulse-premium.js');
const api = read('app/Http/Controllers/Api/V1/PulseController.php');
const points = read('app/Services/PulsePointService.php');
const gamification = read('app/Services/PulseGamificationService.php');
const automation = read('app/Services/PulseAutomationService.php');
const webRoutes = read('routes/web.php');
const apiRoutes = read('routes/api.php');
const openapi = read('docs/openapi.yaml');
const quotaMigration = read('database/migrations/2026_09_04_000200_remove_legacy_scan_signal_daily_quotas.php');
const repair = read('ABS_V15_0_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql');
const repairDb = read('database/ABS_V15_0_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql');
const cleanup = read('ABS_V15_0_2_REMOVE_LEGACY_SCAN_SIGNAL_QUOTAS.sql');
const cleanupDb = read('database/ABS_V15_0_2_REMOVE_LEGACY_SCAN_SIGNAL_QUOTAS.sql');
const catalog = read('database/migrations/2026_08_25_000810_ensure_canonical_strategy_catalog.php');

must(version.trim() === 'ABS V15.0.2', 'VERSION identifies ABS V15.0.2');
must(buildVersion.includes('ABS V15.0.2'), 'BUILD_VERSION identifies ABS V15.0.2');
must(bootstrap.includes("'build' => '15.0.2'"), 'mobile bootstrap identifies build 15.0.2');
must(openapi.includes('version: 15.0.2'), 'OpenAPI identifies API V15.0.2');

// Daily quota model must be gone from runtime behavior and member/Admin interfaces.
mustNot(scanner, 'PulseUsageService', 'Best Signal scanner has no usage-quota service dependency');
mustNot(scanner, 'scanner_runs_per_day', 'Best Signal scanner does not read daily scanner quota');
mustNot(scanner, 'signals_per_day', 'Best Signal scanner does not read daily signal quota');
mustNot(usage, 'PulseScannerRun', 'compatibility usage summary does not count daily scans');
mustNot(usage, 'PulseSignal', 'compatibility usage summary does not count daily signals');
must(usage.includes("'quota_model' => 'removed'"), 'usage compatibility endpoint explicitly reports removed quota model');
must(usage.includes("'points_balance'"), 'usage compatibility endpoint reports PP balance');
mustNot(membershipService, 'scanner_runs_per_day', 'mobile plan payload does not expose daily scanner quota');
mustNot(membershipService, 'signals_per_day', 'mobile plan payload does not expose daily signal quota');
mustNot(layout, 'data-usage-scans', 'member shell has no scans-left counter');
mustNot(layout, 'data-usage-signals', 'member shell has no signals-left counter');
mustNot(js, 'data-usage-scans', 'client JS has no scan-quota updater');
mustNot(js, 'data-usage-signals', 'client JS has no signal-quota updater');
mustNot(adminPlan, 'name="scanner_runs_per_day"', 'Admin plan form has no scanner quota field');
mustNot(adminPlan, 'name="signals_per_day"', 'Admin plan form has no signal quota field');
mustNot(schemaRepair, "'scanner_runs_per_day'", 'protected schema repair does not require/recreate scanner quota field');
mustNot(schemaRepair, "'signals_per_day'", 'protected schema repair does not require/recreate signal quota field');
must(plan.includes("protected $hidden = ['scanner_runs_per_day', 'signals_per_day']"), 'legacy columns are hidden from accidental model serialization during upgrade');
must(planCard.includes('No daily quota'), 'public/member plan card communicates no daily quota');
must(pointsView.includes('No daily scan or signal quotas'), 'Pulse Points center communicates new no-quota economy');

// Upgrade/schema cleanup coverage.
must(quotaMigration.includes("dropColumn($drop)"), 'Laravel upgrade migration drops obsolete quota columns');
must(quotaMigration.includes("Schema::hasColumn('pulse_plans', 'scanner_runs_per_day')"), 'quota migration safely checks scanner quota column before drop');
must(quotaMigration.includes("Schema::hasColumn('pulse_plans', 'signals_per_day')"), 'quota migration safely checks signal quota column before drop');
must(repair === repairDb, 'root/database V15.0.2 create-missing repair SQL copies are identical');
must(!/scanner_runs_per_day|signals_per_day/i.test(repair), 'V15.0.2 create-missing repair SQL never recreates legacy quota columns');
must(!/DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM/i.test(repair), 'V15.0.2 create-missing repair SQL remains non-destructive');
must(cleanup === cleanupDb, 'root/database V15.0.2 quota-cleanup SQL copies are identical');
must(cleanup.includes('DROP COLUMN `scanner_runs_per_day`') && cleanup.includes('DROP COLUMN `signals_per_day`'), 'phpMyAdmin cleanup conditionally removes both legacy quota columns');
must(!/DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM/i.test(cleanup), 'quota cleanup never drops tables or deletes business data');

// Finalized V15 commerce and Best Signal contract remains intact.
must(planCard.includes('$plan->price_points'), 'Pulse plan cards price access in PP');
mustNot(planCard, 'monthly_price', 'Pulse plan cards do not render legacy monthly USDT price');
must(api.includes("'commerce_model' => 'pulse_points'"), 'mobile API declares Pulse Points commerce model');
must(scanner.includes("$scanTimeframes = $timeframe === 'all' ? ['15m', '4h']"), 'Best Signal evaluates 15M + 4H');
must(scanner.includes('$this->pairAccess->allowedPairs($user)'), 'Best Signal uses Admin/package market universe');
must(scanner.includes('usort($qualifiedCandidates'), 'qualified candidates are ranked');
must(scanner.includes('$winner = $qualifiedCandidates[0]'), 'only highest-ranked winner is selected');
must(scanner.includes("'best-signal:run:'.$run->id"), 'Best Signal debit remains idempotent per scanner run');
must(scanner.includes("'points_charged' => 0"), 'no-unlock paths record zero PP charged');
must(scanner.includes("Cache::lock('pulse:best-signal:user:'"), 'Best Signal remains protected by per-user concurrency lock');
must(points.includes('lockForUpdate()'), 'PP wallet mutations remain transaction locked');
must(points.includes("where('idempotency_key', $idempotencyKey)"), 'PP ledger remains idempotent');
must(gamification.includes("reward_type', 'daily_checkin'") && gamification.includes('PulseMission') && gamification.includes('PulseAchievement'), 'check-in, missions and achievements remain implemented');
must(webRoutes.includes('/pulse/points') && apiRoutes.includes('/pulse/points'), 'PP center remains available on web and mobile API');
must(automation.includes("$this->scanner->run($user, null, 'all')") && automation.includes('$scannerRun->bestSignal'), 'automatic trading consumes only Admin-controlled Best Signal');

const strategySlugs = [...catalog.matchAll(/\['[^']+',\s*'([^']+)'/g)].map(m => m[1]);
must(strategySlugs.length === 15, 'canonical strategy catalog still contains exactly 15 strategies');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.2 finalized architecture contract: PASS');
