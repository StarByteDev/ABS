import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => {
    if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
    else console.log(`PASS: ${message}`);
};

const extractMethod = (source, name) => {
    const marker = `function ${name}(`;
    const start = source.indexOf(marker);
    if (start < 0) return '';
    const opening = source.indexOf('{', start);
    let depth = 0, quote = null, lineComment = false, blockComment = false;
    for (let i = opening; i < source.length; i += 1) {
        const char = source[i], next = source[i + 1];
        if (lineComment) { if (char === '\n') lineComment = false; continue; }
        if (blockComment) { if (char === '*' && next === '/') { blockComment = false; i += 1; } continue; }
        if (quote) { if (char === '\\') { i += 1; continue; } if (char === quote) quote = null; continue; }
        if (char === '/' && next === '/') { lineComment = true; i += 1; continue; }
        if (char === '/' && next === '*') { blockComment = true; i += 1; continue; }
        if (char === '"' || char === "'") { quote = char; continue; }
        if (char === '{') depth += 1;
        if (char === '}' && --depth === 0) return source.slice(start, i + 1);
    }
    return '';
};
const sha = value => crypto.createHash('sha256').update(value).digest('hex');

const version = read('VERSION.txt');
const build = read('BUILD_VERSION.txt');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const migration = read('database/migrations/2026_09_04_001000_add_abs_v15_0_3_pulse_sparks_packages.php');
const seeder = read('database/seeders/DatabaseSeeder.php');
const webRoutes = read('routes/web.php');
const apiRoutes = read('routes/api.php');
const pointsController = read('app/Http/Controllers/Admin/AdminPulsePointsController.php');
const pointService = read('app/Services/PulsePointService.php');
const pairAccess = read('app/Services/PulsePairAccessService.php');
const market = read('app/Services/PulseMarketDataService.php');
const intelligenceController = read('app/Http/Controllers/Admin/AdminPulseController.php');
const intelligenceView = read('resources/views/admin/pulse/intelligence.blade.php');
const scheduler = read('routes/console.php');
const validation = read('app/Services/PulseSignalValidationService.php');
const learning = read('app/Services/PulseLearningService.php');
const scanner = read('app/Services/PulseScannerService.php');
const sql = read('ABS_V15_0_3_APPLY_PULSE_SPARKS_PACKAGES.sql');
const sqlCopy = read('database/ABS_V15_0_3_APPLY_PULSE_SPARKS_PACKAGES.sql');

must(['ABS V15.0.3','ABS V15.0.4','ABS V15.0.5'].includes(version.trim()), 'release remains V15.0.3 Spark-contract compatible');
must(build.includes('Pulse Sparks Packages & Strategy Intelligence') || build.includes('Premium Admin Reporting & Signal Oversight') || build.includes('Investor Analytics & Premium Admin'), 'build preserves the requested Spark and intelligence release');
must(bootstrap.includes("'build' => '15.0.3'") || bootstrap.includes("'build' => '15.0.4'") || bootstrap.includes("'build' => '15.0.5'"), 'mobile bootstrap remains V15.0.3 contract compatible');

for (const [name, slug, days, price, cost, markets] of [
    ['Spark Day Pass', 'spark-day-pass', 1, 75, 10, 10],
    ['Spark Flex', 'spark-flex', 3, 180, 8, 40],
    ['Spark Momentum', 'spark-momentum', 7, 380, 5, 150],
    ['Spark Professional', 'pulse-professional', 30, 1200, 3, 500],
]) {
    must(migration.includes(`'name' => '${name}'`) && migration.includes(`'slug' => '${slug}'`), `${name} is migration-backed`);
    const segment = migration.slice(migration.indexOf(`'name' => '${name}'`), migration.indexOf(`'name' => '${name}'`) + 1200);
    must(segment.includes(`'access_days' => ${days}`), `${name} duration is ${days} day(s)`);
    must(segment.includes(`'price_points' => ${price}`), `${name} price is ${price} Sparks`);
    must(segment.includes(`'best_signal_cost_points' => ${cost}`), `${name} Best Signal cost is ${cost} Sparks`);
    must(segment.includes(`'max_selected_pairs' => ${markets}`), `${name} market scope is ${markets}`);
}
must(seeder.includes("'v1503_spark_packages_seeded'"), 'repair seeding protects later Admin package edits');
must(migration.includes("where('slug', 'pulse-intelligence')") && migration.includes("'is_public' => false"), 'superseded plan is retired without deleting history');

must(webRoutes.includes("Route::get('/pulse/sparks'") && webRoutes.includes("Route::redirect('/pulse/points', '/pulse/sparks', 301)"), 'web uses a canonical Pulse Sparks URL with legacy redirect');
must(apiRoutes.includes("Route::get('/pulse/sparks'") && apiRoutes.includes("Route::get('/pulse/points'"), 'mobile API exposes Sparks and legacy aliases');

must(webRoutes.includes("Route::post('/points/gift'") && pointsController.includes('public function gift('), 'Admin gift endpoint is wired');
must(pointsController.includes("'min:1'") && pointsController.includes("'admin_gift'") && pointsController.includes("'admin.sparks_gifted'"), 'Admin gifts are positive-only and audited');
must(pointService.includes('lockForUpdate()') && pointService.includes("where('idempotency_key', $idempotencyKey)"), 'Spark wallet remains transaction-locked and idempotent');

must(pairAccess.includes("if (($plan->pair_access_mode ?: 'all') === 'all')") && pairAccess.includes('->limit($marketLimit)->get()'), 'all-market packages now enforce Admin market limits');
must(market.includes('PulsePlan::query()') && market.includes('UserServiceAccess::query()'), 'central candle warming uses public and actively assigned packages');
must(!market.includes('PulseUserSetting'), 'central scanner universe no longer depends on legacy user-selected pairs');

must(intelligenceController.includes("'confidence_impact' => $reliability === null ? null : ($reliability - 50.0) * 0.25"), 'Admin calculates each strategy confidence impact');
must(intelligenceController.includes("'strategyRollups'=>$strategyRollups") && intelligenceController.includes("'pending_validations'=>$pendingValidations"), 'Admin receives all-strategy rollups and validation backlog');
must((intelligenceView.includes('Strategy results & confidence impact') || intelligenceView.includes('Strategy performance: selected period vs all time')) && intelligenceView.includes('75% technical score + 25% learned reliability'), 'Admin clearly presents result and confidence calculation');
must(intelligenceView.includes('name="strategy"') && intelligenceView.includes('name="timeframe"') && intelligenceView.includes('name="direction"'), 'Admin strategy reporting has practical filters');

must((scheduler.match(/abs:pulse-market-data'\)->everyMinute\(\)/g) || []).length >= 1, 'central Binance market data remains scheduled every minute');
must((scheduler.match(/abs:pulse-validate-signals'\)->everyMinute\(\)/g) || []).length >= 1, 'signal validation remains scheduled every minute');
must(validation.includes('foreach (array_keys($dates) as $date) $this->learning->rebuildDate'), 'resolved outcomes immediately rebuild affected learning dates');
must(scheduler.includes("Schedule::command('abs:pulse-learning')->dailyAt"), 'daily learning catch-up remains scheduled');
must(scanner.includes("$confidence = round(((float) $analysis['score'] * 0.75) + ($reliability * 0.25), 2)"), 'confidence remains 75% technical plus 25% learned reliability');
must(learning.includes("$level = $samples >=") && learning.includes('reliability_score'), 'evidence-protected reliability learning remains active');

const expectedMethods = {
    analyze: '471140c16db31b8cf8f0f10c4d566a5278a2becb99fe505ea5b54875aae0d06e',
    signalBreakdown: '6b378e48ea044b12c82940fe7f9a7001b87bce5dbb4e92667bcb12007f46ea63',
    confidenceLabel: '7b78dc33510b63469ff5013f063e7360f082870a815ded7a6dd7560bc94e05a8',
};
for (const [method, expected] of Object.entries(expectedMethods)) {
    must(sha(extractMethod(scanner, method)) === expected, `${method} calculation body is byte-identical to V15.0.2`);
}

must(!scanner.includes('scanner_runs_per_day') && !scanner.includes('signals_per_day'), 'scanner has no daily scan/signal quota enforcement');
must(sql === sqlCopy, 'root and database phpMyAdmin upgrade scripts are identical');
must(!/DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM/i.test(sql), 'phpMyAdmin upgrade script is non-destructive');
must(sql.includes('v1503_spark_packages_seeded') && sql.includes('@abs_v1503_apply'), 'phpMyAdmin package seed is one-time and idempotent');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.3 Pulse Sparks/packages/strategy intelligence contract: PASS');
