import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root,file),'utf8');
const exists = file => fs.existsSync(path.join(root,file));
const must = (condition, message) => { if (!condition) throw new Error(message); };

const version = read('VERSION.txt').trim();
must(['ABS V15.1.0','ABS V15.1.1','ABS V15.1.2','ABS V15.1.3'].includes(version), `Unexpected version: ${version}`);

const webRoutes = read('routes/web.php');
for (const fragment of [
  "Route::get('/pulse/free-signal'",
  "Route::post('/pulse/free-signal/ad-session'",
  "Route::post('/pulse/free-signal/claim'",
  "Route::get('/pulse/free-signal/status'",
  "Route::get('/pulse/membership'",
  "Route::post('/pulse/membership/request'",
  "Route::get('/rewarded-signals'",
  "Route::put('/rewarded-signals'",
]) must(webRoutes.includes(fragment), `Missing route: ${fragment}`);

for (const file of [
  'app/Services/PulsePublicRewardedSignalService.php',
  'app/Http/Controllers/Pulse/PublicRewardedSignalController.php',
  'app/Http/Controllers/Admin/AdminRewardedSignalController.php',
  'app/Models/PulsePublicSignalUnlock.php',
  'resources/views/pulse/public-signal.blade.php',
  'resources/views/admin/pulse/rewarded-signals.blade.php',
  'public/assets/js/pulse-public-signal-v1513.js',
  'public/assets/css/pulse-public-signal-v1513.css',
  'public/assets/css/admin-rewarded-signal-v1510.css',
  'database/migrations/2026_09_09_151000_restore_direct_usdt_and_add_public_rewarded_signals.php',
  'ABS_V15_1_0_APPLY_DIRECT_USDT_PUBLIC_REWARDED_SIGNALS.sql',
]) must(exists(file), `Missing V15.1 file: ${file}`);

const rewardService = read('app/Services/PulsePublicRewardedSignalService.php');
for (const fragment of [
  "public_rewarded_signal_cooldown_minutes', 30",
  "'visibility_mode' => 'until_refresh'",
  "public_rewarded_signal_min_claim_seconds', 5",
  'resolveAdUnitPath',
  'google_ad_manager',
  'ensureSignalPool',
  'inRandomOrder()',
  "'min' => now()->addSeconds",
]) must(rewardService.includes(fragment), `Reward service contract missing: ${fragment}`);

const publicJs = read('public/assets/js/pulse-public-signal-v1513.js');
for (const fragment of ['OutOfPageFormat.REWARDED','rewardedSlotReady','rewardedSlotGranted','rewardedSlotClosed','rewardedSlotVideoCompleted','makeRewardedVisible','session.claim_token']) {
  must(publicJs.includes(fragment), `Rewarded web JS missing: ${fragment}`);
}

const membership = read('app/Services/PulseMembershipService.php');
must(membership.includes("'commerce_model' => 'direct_usdt_admin_verification'"), 'Direct USDT commerce model missing.');
must(membership.includes("usdt_wallet_address"), 'USDT wallet setting missing.');

const scanner = read('app/Services/PulseScannerService.php');
for (const forbidden of ['points_charged','points_cost','PulsePoint','best_signal_cost_points']) {
  must(!scanner.includes(forbidden), `Scanner still contains retired economy term: ${forbidden}`);
}

const activeRoots = ['app','routes','resources/views','config'];
const forbiddenPatterns = [
  /Pulse Sparks/i, /Pulse Points/i, /pulse_point_/i, /PulsePoint/, /price_points/, /best_signal_cost_points/,
  /points_charged/, /points_cost/, /allow_points_activation/, /rewarded_ad_points/, /daily_checkin_points/,
];
const walk = dir => fs.readdirSync(dir,{withFileTypes:true}).flatMap(e => {
  const p=path.join(dir,e.name); return e.isDirectory()?walk(p):[p];
});
for (const base of activeRoots) {
  for (const file of walk(path.join(root,base)).filter(f=>!f.endsWith('.png')&&!f.endsWith('.jpg'))) {
    const src=fs.readFileSync(file,'utf8');
    for (const re of forbiddenPatterns) must(!re.test(src), `Retired economy reference ${re} in ${path.relative(root,file)}`);
  }
}

for (const retired of [
  'app/Services/PulsePointService.php','app/Services/PulseGamificationService.php','app/Services/PulseRewardedAdService.php',
  'app/Http/Controllers/Pulse/PointsController.php','app/Http/Controllers/Pulse/RewardedAdController.php',
  'app/Http/Controllers/Admin/AdminPulsePointsController.php','resources/views/pulse/points/index.blade.php',
]) must(!exists(retired), `Retired runtime file still exists: ${retired}`);

const adminView=read('resources/views/admin/pulse/rewarded-signals.blade.php');
must(adminView.includes('Rewarded ad unit / copied GPT code'), 'Admin ad-unit/code input missing.');
must(adminView.includes('Desktop Web') && adminView.includes('Mobile Web'), 'Admin device support guidance missing.');

const header=read('resources/views/partials/header.blade.php');
must(header.includes("route('pulse.free-signal')"), 'Public Free Signal navigation link missing.');
const home=read('resources/views/home.blade.php');
must(home.includes('Free Signal · Watch Ad'), 'Homepage Free Signal CTA missing.');

console.log('ABS V15.1.0 direct-USDT + public rewarded-signal contract: PASS');
