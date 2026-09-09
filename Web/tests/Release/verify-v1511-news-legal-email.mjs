import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const exists = file => fs.existsSync(path.join(root, file));
const must = (condition, message) => { if (!condition) throw new Error(message); };
const walk = dir => fs.readdirSync(dir, {withFileTypes:true}).flatMap(entry => {
  const p = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(p) : [p];
});

must(['ABS V15.1.1','ABS V15.1.2','ABS V15.1.3'].includes(read('VERSION.txt').trim()), 'VERSION.txt must be ABS V15.1.1 or ABS V15.1.2');
must(read('BUILD_VERSION.txt').includes('ABS V15.1.3') || read('BUILD_VERSION.txt').includes('ABS V15.1.2') || (read('BUILD_VERSION.txt').includes('Direct USDT') && read('BUILD_VERSION.txt').includes('ABS News')), 'Build version must describe the active ABS release model.');

for (const file of [
  'app/Services/PulsePublicRewardedSignalService.php',
  'app/Services/PulseMembershipService.php',
  'app/Services/EconomicCalendarService.php',
  'app/Services/MacroImpactInterpreter.php',
  'app/Http/Controllers/NewsController.php',
  'app/Models/EconomicEvent.php',
  'resources/views/news/index.blade.php',
  'resources/views/emails/branded.blade.php',
  'resources/views/partials/market-disclaimer.blade.php',
  'database/migrations/2026_09_09_170500_add_macro_intelligence_to_economic_events.php',
  'ABS_V15_1_1_APPLY_FINAL_DIRECT_USDT_REWARDED_NEWS.sql',
  'docs/MOBILE_API_V15_1_1.md',
]) must(exists(file), `Missing V15.1.1 artifact: ${file}`);

const webRoutes = read('routes/web.php');
for (const fragment of [
  "Route::get('/pulse/free-signal'",
  "Route::post('/pulse/free-signal/ad-session'",
  "Route::post('/pulse/free-signal/claim'",
  "Route::get('/pulse/membership'",
  "Route::put('/economic-calendar/settings'",
  "Route::post('/economic-calendar/sync'",
  "Route::redirect('/economic-calendar', '/news#economic-calendar'",
]) must(webRoutes.includes(fragment), `Missing web route contract: ${fragment}`);

const apiRoutes = read('routes/api.php');
must(apiRoutes.includes("Route::get('/economic-calendar'"), 'Mobile/web API economic calendar route missing.');

const rewardService = read('app/Services/PulsePublicRewardedSignalService.php');
for (const fragment of [
  "public_rewarded_signal_cooldown_minutes', 30",
  "'visibility_mode' => 'until_refresh'",
  'google_ad_manager',
  'resolveAdUnitPath',
  'inRandomOrder()',
  'ensureSignalPool',
]) must(rewardService.includes(fragment), `Rewarded signal contract missing: ${fragment}`);

const rewardedJs = read('public/assets/js/pulse-public-signal-v1513.js');
for (const fragment of ['OutOfPageFormat.REWARDED','rewardedSlotReady','rewardedSlotGranted','rewardedSlotClosed','makeRewardedVisible','12000']) {
  must(rewardedJs.includes(fragment), `Rewarded web client missing: ${fragment}`);
}

const membership = read('app/Services/PulseMembershipService.php');
must(membership.includes("'commerce_model' => 'direct_usdt_admin_verification'"), 'Direct-USDT commerce contract missing.');
const checkout = read('resources/views/pulse/membership/checkout.blade.php');
must(checkout.includes('risk_acknowledgement') && checkout.includes("route('legal.risk')"), 'Package risk acknowledgement missing.');
const membershipController = read('app/Http/Controllers/Pulse/MembershipController.php');
must(membershipController.includes("'risk_acknowledgement' => ['accepted']"), 'Package risk acknowledgement is not server validated.');

const news = read('resources/views/news/index.blade.php');
for (const fragment of ['Market-moving events','Previous','Forecast','Actual','Possible crypto effect','CPI','PPI','FOMC']) {
  must(news.includes(fragment), `ABS News macro UX missing: ${fragment}`);
}
const calendarService = read('app/Services/EconomicCalendarService.php');
for (const fragment of ['Financial Modeling Prep','economic-calendar','previous','estimate','actual','Crypt::encryptString']) {
  must(calendarService.includes(fragment), `Economic calendar service missing: ${fragment}`);
}
const interpreter = read('app/Services/MacroImpactInterpreter.php');
for (const fragment of ['cpi','ppi','fomc','nonfarm','retail sales','durable goods','housing starts','crypto_impact_summary']) {
  must(interpreter.toLowerCase().includes(fragment), `Macro interpreter coverage missing: ${fragment}`);
}
const consoleRoutes = read('routes/console.php');
must(consoleRoutes.includes("Artisan::command('abs:sync-economic-calendar'"), 'Economic calendar sync command missing.');
must(consoleRoutes.includes("Schedule::command('abs:sync-economic-calendar')->everyFifteenMinutes()"), 'Economic calendar scheduler missing.');

const email = read('resources/views/emails/branded.blade.php');
must(email.includes('max-width:620px') && email.includes('align="center"') && email.includes('DIGITAL MARKET INTELLIGENCE'), 'Premium centered email shell missing.');
must(!email.includes('align="left"') && !email.includes('align="right"'), 'Email content should remain center-aligned.');
must(email.includes('informational and educational only') && email.includes('not financial advice'), 'Email market disclaimer missing.');

const publicDisclaimer = read('resources/views/partials/market-disclaimer.blade.php');
for (const fragment of ['informational and educational purposes only','Nothing on ABS is personalized financial','Digital assets and derivatives are high risk','may be delayed, revised or inaccurate']) {
  must(publicDisclaimer.includes(fragment), `Global disclaimer missing: ${fragment}`);
}
const footer = read('resources/views/partials/footer.blade.php');
must(footer.includes("@include('partials.market-disclaimer')"), 'Public footer does not include the global disclaimer.');
const pulseLayout = read('resources/views/pulse/layout.blade.php');
must(pulseLayout.includes("partials.market-disclaimer"), 'Pulse layout does not include the global disclaimer.');
const authLayout = read('resources/views/layouts/auth.blade.php');
must(authLayout.includes('auth-global-risk'), 'Auth layout risk notice missing.');
const registration = read('resources/views/auth/register.blade.php');
must(registration.includes("route('legal.risk')") && registration.includes("route('legal.disclaimer')"), 'Registration legal acknowledgement missing.');

const legal = read('app/Http/Controllers/LegalController.php');
for (const fragment of ['Economic Calendar & Forecasts','Rewarded Free Signal','Macro & News Event Risk','No Professional Relationship','Nothing in these Terms excludes liability that cannot legally be excluded']) {
  must(legal.includes(fragment), `Legal documents missing: ${fragment}`);
}

const appController = read('app/Http/Controllers/Api/V1/AppController.php');
must((appController.includes("'build' => '15.1.1'") || appController.includes("'build' => '15.1.2'") || appController.includes("'build' => '15.1.3'")) && appController.includes('market_risk_notice'), 'Mobile bootstrap release/risk metadata missing.');
const contentController = read('app/Http/Controllers/Api/V1/ContentController.php');
must(contentController.includes('crypto_relevant') && contentController.includes('crypto_impact_note'), 'Economic calendar mobile API context missing.');

const settingsController = read('app/Http/Controllers/Admin/AdminEnterpriseController.php');
must(settingsController.includes("->where('type', '!=', 'encrypted')") && settingsController.includes("'economic_calendar_%'"), 'Generic settings must hide economic API credentials/runtime state.');

const activeRoots = ['app','routes','resources/views','config'];
const forbiddenPatterns = [
  /Pulse Sparks/i, /Pulse Points/i, /Spark Wallet/i, /points wallet/i, /Buy Points/i,
  /price_points/, /best_signal_cost_points/, /points_charged/, /points_cost/, /allow_points_activation/,
  /rewarded_ad_points/, /daily_checkin_points/,
];
for (const base of activeRoots) {
  for (const file of walk(path.join(root,base)).filter(file => !/\.(png|jpe?g|webp|gif|ico)$/i.test(file))) {
    const src = fs.readFileSync(file,'utf8');
    for (const pattern of forbiddenPatterns) must(!pattern.test(src), `Retired points economy reference ${pattern} in ${path.relative(root,file)}`);
  }
}

const scanner = read('app/Services/PulseScannerService.php');
must(!scanner.includes('$pointsCharged'), 'Dead points economy variable remains in scanner.');

console.log('ABS V15.1.1 direct-USDT + rewarded free signal + ABS News + legal/email contract: PASS');
