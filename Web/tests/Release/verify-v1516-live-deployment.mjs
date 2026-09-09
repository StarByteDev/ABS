import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root=process.cwd();
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const exists=f=>fs.existsSync(path.join(root,f));
const must=(c,m)=>{if(!c) throw new Error(m)};
const version=read('VERSION.txt').trim();
must(version==='15.1.6' || version==='ABS V15.1.6','VERSION must identify V15.1.6');
for(const f of [
 'public/favicon.ico','public/favicon-32x32.png','public/favicon-16x16.png','public/apple-touch-icon.png',
 'app/Services/PulseStrategyAnalyticsService.php','database/migrations/2026_09_09_204800_add_pulse_strategy_profitability_metrics.php',
 'docs/MOBILE_API_V15_1_6.md','ABS_V15_1_6_APPLY_LIVE_INTELLIGENCE_ANALYTICS.sql','public/assets/css/admin-executive-v1516.css'
]) must(exists(f),`Missing V15.1.6 artifact: ${f}`);
const api=read('routes/api.php');
for(const fragment of [
 "/pulse/free-signal/status","/pulse/free-signal/session","/pulse/free-signal/claim",
 "/reports/strategies","/reports/simulation","/market-data/health","/market-data/prices"
]) must(api.includes(fragment),`Mobile API route missing: ${fragment}`);
const consolePhp=read('routes/console.php');
for(const fragment of ['abs:pulse-market-data','abs:pulse-validate-signals','abs:pulse-sync','abs:pulse-learning','abs:pulse-execution-check','abs:pulse-analytics-backfill']) must(consolePhp.includes(fragment),`Operational command missing: ${fragment}`);
must(consolePhp.includes("whereIn('status', ['pending','open','closing','protection_failed'])") && consolePhp.includes('liveTradeUsers'), 'Cron sync must include users with live trades even after package expiry');
must(consolePhp.includes("$results['trade_sync'] = $this->call('abs:pulse-sync')"), 'Production acceptance must exercise trade reconciliation');
must(!read('app/Services/PulseTradeService.php').includes('public function sync(User $user): array\n    {\n        $this->access->assertActive($user);'), 'Trade reconciliation must not stop solely because package access expired');
const learning=read('app/Services/PulseLearningService.php');
for(const fragment of ['model_trades','model_net_r','model_gross_profit_r','model_gross_loss_r','model_return_pct']) must(learning.includes(fragment),`Profitability learning metric missing: ${fragment}`);
const scanner=read('app/Services/PulseScannerService.php');
must(scanner.includes('($technicalScore * 0.75) + ($reliabilityScore * 0.25)') || scanner.includes('0.75') && scanner.includes('0.25'),'75/25 confidence blend must remain present');
const intelligence=read('resources/views/admin/pulse/intelligence.blade.php');
for(const fragment of ['ROBOT READINESS','What-if performance path','Actual Binance','Strategy profitability','75% technical score + 25% learned reliability']) must(intelligence.includes(fragment),`Admin Intelligence missing: ${fragment}`);
const market=read('resources/views/admin/market-data.blade.php');
for(const fragment of ['Scheduler','validation','execution','stale']) must(market.toLowerCase().includes(fragment.toLowerCase()),`Market-data health UI missing: ${fragment}`);
const trade=read('app/Services/PulseTradeService.php');
for(const fragment of ['protectionExitEvidence','take_profit','stop_loss','last_synced_at']) must(trade.includes(fragment),`Trade reconciliation guard missing: ${fragment}`);

const freeService=read('app/Services/PulsePublicRewardedSignalService.php');
for(const fragment of ["presentation' => 'market_watch'","is_qualified_signal' => false",'ENTRY WATCH']) must(freeService.includes(fragment),`V15.1.5 Entry Watch fallback regression: ${fragment}`);
const membership=read('app/Services/PulseMembershipService.php');
must(membership.includes("'commerce_model' => 'direct_usdt_admin_verification'"),'Direct-USDT Admin verification model must remain active');
const publicSignal=read('resources/views/pulse/public-signal.blade.php');
must(publicSignal.includes('data-signal-teaser') && publicSignal.includes('data-signal-reveal'),'Free Signal teaser/reveal UX must remain present');
must(!publicSignal.includes('30-second view'),'Retired 30-second reveal copy must not return');

const appController=read('app/Http/Controllers/Api/V1/AppController.php');
for(const fragment of ['15.1.6','strategy_profitability','what_if_simulation','market_feed_health','free_signal_api_status']) must(appController.includes(fragment),`Bootstrap capability missing: ${fragment}`);
const repair=read('app/Support/PulseSchemaRepair.php');
for(const fragment of ['model_trades','model_net_r','model_return_pct']) must(repair.includes(fragment),`Schema self-repair missing: ${fragment}`);
const layouts=['resources/views/admin/layout.blade.php','resources/views/layouts/app.blade.php','resources/views/layouts/auth.blade.php','resources/views/pulse/layout.blade.php'];
for(const f of layouts) must(read(f).includes('favicon.ico'),`Real favicon reference missing: ${f}`);
const hash=f=>crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex');
must(hash('app/Services/PulseScannerService.php')==='54763eebcab6af64ef2299d3abaa999ec137731ed7db7b0cb4945e01446decab','Core PulseScannerService changed unexpectedly');
must(hash('public/assets/brand/abs-logo-512.png')==='e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8','Production ABS logo changed unexpectedly');
console.log('ABS V15.1.6 live deployment + mobile API + strategy intelligence contract: PASS');
