import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root=process.cwd();
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const exists=f=>fs.existsSync(path.join(root,f));
const must=(c,m)=>{if(!c) throw new Error(m)};
const hash=f=>crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex');

must(read('VERSION.txt').trim()==='15.2.0','VERSION must be 15.2.0');
for(const f of [
  'app/Services/AdCmsService.php',
  'app/Services/PulseRuntimeCadenceService.php',
  'app/Services/PulseStrategyResearchService.php',
  'app/Http/Controllers/Admin/AdminAdsController.php',
  'app/Http/Controllers/Admin/AdminStrategyDashboardController.php',
  'resources/views/admin/ads/index.blade.php',
  'resources/views/admin/pulse/strategy-dashboard.blade.php',
  'public/assets/css/admin-ads-v1520.css',
  'public/assets/css/admin-strategy-dashboard-v1520.css',
  'public/assets/js/admin-strategy-dashboard-v1520.js',
  'public/assets/css/pulse-public-signal-v1520.css',
  'public/assets/js/pulse-public-signal-v1520.js',
]) must(exists(f),`Missing V15.2.0 artifact: ${f}`);

const free=read('app/Services/PulsePublicRewardedSignalService.php');
for(const fragment of [
  'randomProfessionalQualifiedSignal',
  'randomSystemQualifiedSignal',
  'fresh_professional_engine_scan',
  'btcMarketOutlookSnapshot',
  "'presentation' => 'btc_outlook'",
  "'is_qualified_signal' => false",
  "foreach (['15m', '4h'] as $timeframe)",
]) must(free.includes(fragment),`Free Signal upgrade missing: ${fragment}`);
must(free.includes("$this->scanner->run(null, null, 'all')"),'Free Signal must reuse market-wide Professional scanner behavior');
must(free.includes('$this->scanner->analyze($pair, $timeframe)'),'BTC fallback must use the same strategy analyzer');

const publicView=read('resources/views/pulse/public-signal.blade.php');
for(const fragment of ['adCms','v1520.css','v1520.js','same Pulse Professional signal pool','BTC Market Outlook']) must(publicView.includes(fragment),`Free Signal page missing: ${fragment}`);
const publicJs=read('public/assets/js/pulse-public-signal-v1520.js');
for(const fragment of ['btc_outlook','BTC MARKET OUTLOOK','BTC Watch Levels','not a qualified Pulse signal']) must(publicJs.includes(fragment),`BTC outlook UX missing: ${fragment}`);

const routes=read('routes/web.php');
for(const fragment of ['AdminAdsController','AdminStrategyDashboardController',"/strategy-dashboard","/ads"]) must(routes.includes(fragment),`Admin route missing: ${fragment}`);
const ads=read('app/Services/AdCmsService.php');
for(const fragment of ['free_signal_top','free_signal_inline','free_signal_footer','ads_google_head_code']) must(ads.includes(fragment),`Ads CMS placement missing: ${fragment}`);
const adsView=read('resources/views/admin/ads/index.blade.php');
must(adsView.includes('Save Ads CMS') && adsView.includes('Rewarded Signal Ads'),'Ads CMS UX incomplete');

const strategy=read('resources/views/admin/pulse/strategy-dashboard.blade.php');
for(const fragment of ['Research Signals','Paper Entries Observed','Actual Realized P&amp;L','15-STRATEGY ATTRIBUTION','ACTUAL EXCHANGE EXECUTION','AUTOMATION READINESS','Run Research Now']) must(strategy.includes(fragment),`Strategy Dashboard UX missing: ${fragment}`);
const controller=read('app/Http/Controllers/Admin/AdminStrategyDashboardController.php');
for(const fragment of ['outcomeSummary','strategyRows','dailyTrend','actualExecution','pulse_background_interval_seconds','strategy_research_enabled']) must(controller.includes(fragment),`Strategy Dashboard backend missing: ${fragment}`);

const runtime=read('app/Services/PulseRuntimeCadenceService.php');
must(runtime.includes('[30, 60, 120, 300]'),'Runtime cadence must support 30s/60s/2m/5m');
must(runtime.includes('hostgator_shared') && runtime.includes('max(60, $configured)'),'HostGator must clamp sub-minute cadence to at least 60 seconds');
const consolePhp=read('routes/console.php');
for(const fragment of ['abs:pulse-strategy-scan','everyThirtySeconds','PulseStrategyResearchService','PulseRuntimeCadenceService']) must(consolePhp.includes(fragment),`Background engine scheduling missing: ${fragment}`);
must(consolePhp.includes("Schedule::command('abs:pulse-strategy-scan')->everyMinute()"),'HostGator strategy research must retain one-minute scheduler compatibility');

const validation=read('app/Services/PulseSignalValidationService.php');
must(validation.includes("whereDoesntHave('validation')") && validation.includes("whereNull('resolved_at')"),'Validation queue must skip already-resolved rows so new research signals are not starved');
const launcher=read('RUN-ABS-LARAGON.bat');
must(launcher.includes('php artisan schedule:work') && launcher.includes('php artisan serve'),'Local launcher must run scheduler and web server');

const layout=read('resources/views/admin/layout.blade.php');
for(const fragment of ['Users Dashboard','Strategy Dashboard','Ads CMS']) must(layout.includes(fragment),`Admin navigation missing: ${fragment}`);

must(hash('app/Services/PulseScannerService.php')==='54763eebcab6af64ef2299d3abaa999ec137731ed7db7b0cb4945e01446decab','Core PulseScannerService changed unexpectedly');
must(hash('public/assets/brand/abs-logo-512.png')==='e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8','Production ABS logo changed unexpectedly');
console.log('ABS V15.2.0 Free Signal + Ads CMS + Strategy Dashboard contract: PASS');
