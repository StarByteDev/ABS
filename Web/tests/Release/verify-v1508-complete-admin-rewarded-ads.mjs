import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (rel) => fs.readFileSync(path.join(root, rel), 'utf8');
const exists = (rel) => fs.existsSync(path.join(root, rel));
const failures = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };

for (const rel of [
  'public/assets/css/admin-executive-v1508.css',
  'public/assets/js/pulse-rewarded-ads-v1508.js',
  'app/Services/PulseRewardedAdService.php',
  'app/Models/PulseRewardedAdReceipt.php',
  'app/Http/Controllers/Pulse/RewardedAdController.php',
  'app/Http/Controllers/Admin/AdminDashboardReportController.php',
  'database/migrations/2026_09_06_000800_add_abs_v15_0_8_rewarded_ads.php',
  'ABS_V15_0_8_APPLY_REWARDED_ADS.sql',
  'docs/ABS_V15_0_8_REWARDED_ADS.md',
]) assert(exists(rel), `Missing V15.0.8 release file: ${rel}`);

const layout = read('resources/views/admin/layout.blade.php');
assert(layout.includes('admin-executive-v1508.css'), 'Admin layout does not load V15.0.8 CSS last.');
assert(layout.includes('assets/brand/abs-logo-512.png'), 'Admin layout does not use the production ABS logo.');
assert(layout.includes('ABS</span> Pulse'), 'Admin product strip does not present ABS Pulse.');
assert(layout.includes("View::hasSection('hide-heading')"), 'Admin layout cannot suppress the legacy dashboard heading.');

const dashboard = read('resources/views/admin/dashboard.blade.php');
assert(dashboard.includes("@section('hide-heading','1')"), 'Dashboard does not use approved no-heading composition.');
assert((dashboard.match(/Strategy Performance/g) || []).length === 1, 'Dashboard must contain exactly one Strategy Performance module.');
for (const label of ['Pulse Intelligence','Signal Oversight','Quick Actions','Latest Trades','Member Growth','Plan Expiry Alerts','Sparks Commerce','Best Signal Summary']) {
  assert(dashboard.includes(label), `Dashboard missing approved module: ${label}`);
}
assert(dashboard.includes('admin.dashboard.export'), 'Dashboard Generate Report action is not wired.');
assert(dashboard.includes('Signals Today'), 'Dashboard is missing Signals Today KPI.');

const webRoutes = read('routes/web.php');
assert(webRoutes.includes("name('dashboard.export')"), 'Executive report route missing.');
assert(webRoutes.includes("name('pulse.points.rewarded-ad.session')"), 'Web rewarded-ad session route missing.');
assert(webRoutes.includes("name('pulse.points.rewarded-ad.claim')"), 'Web rewarded-ad claim route missing.');

const apiRoutes = read('routes/api.php');
assert(apiRoutes.includes('/pulse/rewarded-ad/admob/ssv'), 'AdMob SSV callback route missing.');
assert(apiRoutes.includes('/pulse/sparks/rewarded-ad/config'), 'Mobile rewarded-ad config route missing.');

const pointsView = read('resources/views/pulse/points/index.blade.php');
assert(pointsView.includes('WATCH & EARN'), 'Member Pulse Sparks page does not expose rewarded ads.');
assert(pointsView.includes('data-rewarded-ad-consent'), 'Member rewarded ad does not require explicit opt-in.');
assert(pointsView.includes('pulse-rewarded-ads-v1508.js'), 'Member rewarded-ad JavaScript is not loaded.');

const rewardJs = read('public/assets/js/pulse-rewarded-ads-v1508.js');
for (const token of ['OutOfPageFormat.REWARDED','rewardedSlotReady','rewardedSlotGranted','rewardedSlotClosed','makeRewardedVisible','removeEventListener']) {
  assert(rewardJs.includes(token), `Web rewarded-ad integration missing ${token}.`);
}

const rewardService = read('app/Services/PulseRewardedAdService.php');
for (const token of ['openssl_verify','admob_ssv_ecdsa','PulseRewardedAdReceipt','ca-app-pub-3940256099942544/5224354917','ca-app-pub-3940256099942544/1712485313']) {
  assert(rewardService.includes(token), `Rewarded-ad service missing ${token}.`);
}
assert(rewardService.includes('base64UrlDecode'), 'Rewarded-ad signature decoder is not URL-safe/padding-safe.');
assert(rewardService.includes('if (! $knownReceipt) $this->assertEligible'), 'Rewarded-ad provider retries are not idempotent across cooldown/daily-cap checks.');

const adminPoints = read('resources/views/admin/pulse/points.blade.php');
for (const label of ['Rewarded Ads Control Center','Web test mode','Mobile test mode','AdMob SSV callback URL','Latest rewarded-ad receipts']) {
  assert(adminPoints.includes(label), `Admin rewarded-ad controls missing: ${label}`);
}

const migration = read('database/migrations/2026_09_06_000800_add_abs_v15_0_8_rewarded_ads.php');
assert(migration.includes('pulse_rewarded_ad_receipts'), 'Reward receipt migration table missing.');
assert(migration.includes('rewarded_ads_daily_limit'), 'Reward daily-limit setting missing.');
assert(migration.includes('rewarded_admob_ssv_enabled'), 'SSV setting missing.');

const repair = read('app/Support/PulseSchemaRepair.php');
assert(repair.includes('pulse_rewarded_ad_receipts'), 'Protected schema repair does not know rewarded-ad receipts.');
assert(repair.includes('2026_09_06_000800_add_abs_v15_0_8_rewarded_ads.php'), 'Protected schema repair does not run V15.0.8 migration.');

const config = read('config/services.php');
assert(config.includes('https://www.gstatic.com/admob/reward/verifier-keys.json'), 'AdMob official verifier key endpoint missing.');

if (failures.length) {
  console.error(`V15.0.8 release audit failed (${failures.length}):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log('V15.0.8 release audit passed: approved Admin layout, functional report route, web rewarded ads, mobile SSV backend, Admin controls and shared-hosting migration are present.');
