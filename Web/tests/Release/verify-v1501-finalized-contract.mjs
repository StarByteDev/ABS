import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => { if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; } else console.log(`PASS: ${message}`); };
const mustNot = (source, needle, message) => must(!source.includes(needle), message);

const version = read('VERSION.txt');
const buildVersion = read('BUILD_VERSION.txt');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const scanner = read('app/Services/PulseScannerService.php');
const scannerController = read('app/Http/Controllers/Pulse/ScannerController.php');
const points = read('app/Services/PulsePointService.php');
const gamification = read('app/Services/PulseGamificationService.php');
const services = read('config/services.php');
const webPoints = read('app/Http/Controllers/Pulse/PointsController.php');
const api = read('app/Http/Controllers/Api/V1/PulseController.php');
const membership = read('app/Http/Controllers/Pulse/MembershipController.php');
const membershipService = read('app/Services/PulseMembershipService.php');
const mail = read('app/Services/BrandedMailService.php');
const automation = read('app/Services/PulseAutomationService.php');
const webRoutes = read('routes/web.php');
const apiRoutes = read('routes/api.php');
const planCard = read('resources/views/pulse/partials/membership-plan-card.blade.php');
const pointsView = read('resources/views/pulse/points/index.blade.php');
const home = read('resources/views/home.blade.php');
const scannerView = read('resources/views/pulse/scanner.blade.php');
const scannerBottom = read('resources/views/pulse/partials/scanner-bottom.blade.php');
const settingsView = read('resources/views/pulse/settings.blade.php');
const adminPoints = read('resources/views/admin/pulse/points.blade.php');
const adminPlan = read('resources/views/admin/pulse/partials/plan-fields.blade.php');
const reconcile = read('database/migrations/2026_09_04_000100_reconcile_abs_v15_0_1_pp_commerce.php');
const repair = read('ABS_V15_0_1_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql');
const repairDb = read('database/ABS_V15_0_1_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql');
const openapi = read('docs/openapi.yaml');
const catalog = read('database/migrations/2026_08_25_000810_ensure_canonical_strategy_catalog.php');

must(version.includes('ABS V15.0.1'), 'VERSION identifies ABS V15.0.1');
must(buildVersion.includes('ABS V15.0.1'), 'BUILD_VERSION identifies ABS V15.0.1');
must(bootstrap.includes("'build' => '15.0.1'"), 'mobile bootstrap identifies build 15.0.1');
must(!bootstrap.includes("'pulse_strategies'"), 'mobile bootstrap does not advertise member strategy controls');

must(planCard.includes('$plan->price_points'), 'public Pulse cards price plans in PP');
must(planCard.includes('PP / {{ $plan->access_days }} days'), 'public Pulse cards label PP access duration');
must(planCard.includes('USDT is used only to buy Pulse Points'), 'public Pulse cards explain USDT→PP boundary');
mustNot(planCard, 'monthly_price', 'public Pulse cards do not use monthly USDT price');
mustNot(planCard, 'Subscribe to Pulse', 'public Pulse cards do not use legacy subscribe CTA');
must(home.includes('PP'), 'homepage presents Pulse plan commerce in PP');
mustNot(home, 'monthly_price', 'homepage does not render legacy plan monthly price');

must(pointsView.includes('Buy Pulse Points with USDT'), 'member PP center contains USDT→PP purchase flow');
must(pointsView.includes('Activate Pulse with PP'), 'member PP center contains PP plan activation');
must(adminPoints.includes('USDT → PP receiving settings'), 'Admin has dedicated USDT→PP receiving controls');
must(adminPlan.includes('Plan price in PP'), 'Admin plan editor controls plan PP price');
must(adminPlan.includes('Best Signal cost (PP)'), 'Admin plan editor controls Best Signal PP cost');
must(reconcile.includes("'membership_requests_enabled'"), 'V15.0.1 reconciliation disables legacy membership requests');
must(reconcile.includes("'promotion_codes_enabled'"), 'V15.0.1 reconciliation disables legacy direct-checkout promotions');
must(reconcile.includes("'request_enabled'"), 'V15.0.1 reconciliation disables legacy plan request flag');
must(reconcile.includes("'requires_payment'"), 'V15.0.1 reconciliation disables legacy plan payment flag');

must(membership.includes('Direct USDT Pulse plan checkout is disabled'), 'web legacy membership controller refuses direct USDT plan checkout');
must(api.includes('Direct USDT Pulse plan checkout is disabled in ABS V15'), 'mobile API refuses direct USDT plan checkout');
must(api.includes("'commerce_model' => 'pulse_points'"), 'mobile API declares Pulse Points commerce model');
must(membershipService.includes("'commerce_model' => 'pulse_points'"), 'membership service declares Pulse Points commerce model');
must(mail.includes('adminNewPointPurchase') && mail.includes('New Pulse Points purchase request'), 'Admin event email now covers PP purchase verification');

const webPointsIndex = webRoutes.indexOf("Route::get('/pulse/points'");
const webProtectedPulse = webRoutes.indexOf("Route::middleware('pulse.access')->prefix('pulse')");
must(webPointsIndex >= 0 && webProtectedPulse >= 0 && webPointsIndex < webProtectedPulse, 'web PP center is accessible before Pulse plan middleware');
const apiPointsIndex = apiRoutes.indexOf("Route::get('/pulse/points'");
const apiProtectedPulse = apiRoutes.indexOf("Route::middleware(['pulse.access', 'pulse.capability:mobile_api'])->prefix('pulse')");
must(apiPointsIndex >= 0 && apiProtectedPulse >= 0 && apiPointsIndex < apiProtectedPulse, 'mobile PP center is accessible before Pulse plan middleware');

must(scanner.includes("$scanTimeframes = $timeframe === 'all' ? ['15m', '4h']"), 'Best Signal scanner evaluates 15M + 4H');
must(scanner.includes('$this->pairAccess->allowedPairs($user)'), 'scanner uses Admin/package market universe');
must(scanner.includes('usort($qualifiedCandidates'), 'scanner ranks qualified candidates');
must(scanner.includes('$winner = $qualifiedCandidates[0]'), 'scanner exposes only highest-ranked candidate');
must(scanner.includes("'best-signal:run:'.$run->id"), 'Best Signal debit uses per-run idempotency key');
must(scanner.includes("'points_charged' => 0"), 'failed/no-unlock scanner state records 0 PP charged');
must(scanner.includes("? ['symbol' => $symbol, 'timeframe' => $scanTimeframe, 'status' => 'evaluated']"), 'member scanner summary is sanitized');
must(scanner.includes("Cache::lock('pulse:best-signal:user:'"), 'scanner has per-user concurrency lock');
must(scannerController.includes("'best_signal' => $winner ? ["), 'web JSON scanner response is sanitized to Best Signal fields');
mustNot(scannerController, "'run' => $run", 'web JSON scanner does not expose raw scanner run model');

must(points.includes('lockForUpdate()'), 'PP wallet mutations lock the wallet row');
must(points.includes("where('idempotency_key', $idempotencyKey)"), 'PP ledger enforces idempotent retries');
must(gamification.includes("reward_type', 'daily_checkin'") && gamification.includes('pulse_streak_days'), 'daily check-in and streak progression are implemented');
must(gamification.includes('PulseMission') && gamification.includes('PulseAchievement'), 'missions and achievements are implemented');
must(gamification.includes('hash_hmac') && gamification.includes('hash_equals'), 'rewarded-ad PP requires server-side HMAC verification');
must(services.includes("'secret' => env('PULSE_REWARDED_AD_SECRET')"), 'rewarded-ad secret stays server-side');
must(webRoutes.includes("/pulse/points/rewarded-ad") && apiRoutes.includes("/pulse/points/rewarded-ad"), 'verified rewarded-ad completion endpoint exists on web and mobile API');
must(webPoints.includes('if (! $entry->wasRecentlyCreated) return'), 'web PP plan activation does not extend twice on retry');
must(api.includes('if(!$entry->wasRecentlyCreated) return'), 'mobile PP plan activation does not extend twice on retry');

must(automation.includes("$this->scanner->run($user, null, 'all')"), 'automatic trading calls Admin-controlled Best Signal scan');
must(automation.includes('$scannerRun->bestSignal'), 'automatic trading consumes only Best Signal');
const automationSelectionBlock = automation.slice(automation.indexOf('// V15 automation consumes'), automation.indexOf('$created = 0;'));
mustNot(automationSelectionBlock, 'selected_pairs', 'automatic signal selection ignores legacy selected pairs');
mustNot(automationSelectionBlock, 'minimum_signal_score', 'automatic signal selection ignores legacy user threshold');

must(scannerView.includes('Find Best Signal'), 'scanner UI is one-click Find Best Signal');
must(scannerBottom.includes('Best Signal'), 'scanner supporting UI explains Best Signal');
mustNot(scannerView + scannerBottom + settingsView, 'Use in Scanner', 'member UI has no strategy use-in-scanner control');
mustNot(scannerView + scannerBottom + settingsView, 'score ≥', 'member UI has no user technical threshold control');

must(openapi.includes('version: 15.0.1'), 'OpenAPI identifies V15.0.1');
must(!/^  \/pulse\/strategies:/m.test(openapi), 'OpenAPI has no member strategy endpoint');
must(openapi.replace(/\s+/g, ' ').includes('USDT is accepted only for Admin-verified PP pack purchases'), 'OpenAPI documents USDT→PP boundary');

const strategySlugs = [...catalog.matchAll(/\['[^']+',\s*'([^']+)'/g)].map(m => m[1]);
must(strategySlugs.length === 15, 'canonical strategy catalog still contains exactly 15 strategies');

must(repair === repairDb, 'root and database V15.0.1 phpMyAdmin repair SQL copies are identical');
must(!/DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM/i.test(repair), 'V15.0.1 repair SQL contains no destructive table/data operations');
must(repair.includes('point_purchases_enabled'), 'V15.0.1 repair SQL reconciles PP purchase switch');
must(repair.includes('membership_requests_enabled'), 'V15.0.1 repair SQL reconciles legacy membership switch');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.1 finalized architecture contract: PASS');
