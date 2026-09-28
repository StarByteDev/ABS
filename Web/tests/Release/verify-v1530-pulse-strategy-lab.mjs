import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root=process.cwd();
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const exists=f=>fs.existsSync(path.join(root,f));
const must=(c,m)=>{if(!c) throw new Error(m)};
const hash=f=>crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex');

must(read('VERSION.txt').trim()==='15.3.0','VERSION must be 15.3.0');
for(const f of [
  'app/Services/PulseStrategyCycleService.php',
  'app/Services/PulseRuntimeCadenceService.php',
  'public/assets/css/admin-strategy-lab-v1530.css',
  'public/assets/js/admin-strategy-lab-v1530.js',
  'resources/views/admin/pulse/strategy-dashboard.blade.php',
  'resources/views/admin/pulse/partials/strategy-lab-nav.blade.php',
  'docs/ABS_V15_3_0_PULSE_STRATEGY_LAB.md',
  '.env.example',
  'ABS_V15_3_0_NO_DATABASE_CHANGES.txt',
]) must(exists(f),`Missing V15.3.0 artifact: ${f}`);

const layout=read('resources/views/admin/layout.blade.php');
for(const fragment of ['Pulse Strategy Lab','Engine Setup','Audit &amp; History','abs-admin-v1530','v15.3.0'])
  must(layout.includes(fragment),`Streamlined Admin navigation/branding missing: ${fragment}`);

const dashboard=read('resources/views/admin/pulse/strategy-dashboard.blade.php');
for(const fragment of [
  'ENGINE CONTROL','Production Cron','ABS Scheduler','Manual Only','5 seconds','30 seconds','Run Full Cycle',
  'Paper model return','Wins · TP','Losses · SL','Which strategies are winning?','Win / Loss','Model return','Reliability',
  'CYCLE REPORT','PAPER TRADE REGISTER','FUTURE LIVE EXECUTION'
]) must(dashboard.includes(fragment),`Strategy Lab UX missing: ${fragment}`);

const cadence=read('app/Services/PulseRuntimeCadenceService.php');
for(const fragment of [
  'ALLOWED_SECONDS = [5, 30, 60, 120, 300]',
  "ALLOWED_MODES = ['cron', 'internal', 'manual']",
  "max(60, $configured)",
  "return $this->mode() !== 'manual'"
]) must(cadence.includes(fragment),`Runtime cadence contract missing: ${fragment}`);

const cycle=read('app/Services/PulseStrategyCycleService.php');
for(const fragment of [
  'syncCentral()','runIfDue($force)','validation->process(500)','markValidationRun()',
  'without enabling or placing','prices refreshed, strategies evaluated and open paper outcomes checked'
]) must(cycle.includes(fragment),`Full-cycle service missing: ${fragment}`);

const research=read('app/Services/PulseStrategyResearchService.php');
for(const fragment of ['reuseEquivalentOpenSignal','same open setup was reused','deduplicated','reused_signal_id','discarded_duplicate_signal_id','cycle_mode','market_run_id'])
  must(research.includes(fragment),`Duplicate-safe research trace missing: ${fragment}`);

const consolePhp=read('routes/console.php');
for(const fragment of ['V15.3.0','abs:pulse-strategy-cycle','everyFiveSeconds()','marketDataDue()','scheduledEnabled()'])
  must(consolePhp.includes(fragment),`V15.3.0 scheduler contract missing: ${fragment}`);
const schedulerSection=consolePhp.slice(consolePhp.indexOf('// V15.3.0 Strategy Lab'));
must(!schedulerSection.includes("Schedule::command('abs:pulse-market-data')"),'Strategy Lab scheduler must use the ordered full-cycle command, not a separate market-data task');
must(!schedulerSection.includes("Schedule::command('abs:pulse-strategy-scan')"),'Strategy Lab scheduler must not split scanning away from the full cycle');
must(!schedulerSection.includes("Schedule::command('abs:pulse-validate-signals')"),'Strategy Lab scheduler must not split reconciliation away from the full cycle');

const controller=read('app/Http/Controllers/Admin/AdminStrategyDashboardController.php');
for(const fragment of ['PulseStrategyCycleService','strategyProfitability','model_return_pct','pulse_background_mode','pulse_background_interval_seconds','admin.strategy_lab_full_cycle','Market Data','Strategy Scan','Paper Trades','Results','Strategy Evidence'])
  must(controller.includes(fragment),`Strategy Lab controller missing: ${fragment}`);

const market=read('resources/views/admin/market-data.blade.php');
for(const fragment of ['STAGE 01 · MARKET DATA','Are prices ready for the next scan?','Mode','Effective interval','Price refresh history','Refresh Prices Now'])
  must(market.includes(fragment),`Focused Market Data report missing: ${fragment}`);

const css=read('public/assets/css/admin-strategy-lab-v1530.css');
for(const fragment of ['--lab-gold','--lab-cyan','enterprise-plan-editor>summary','lab-stage-flow','lab-stage-report-grid'])
  must(css.includes(fragment),`ABS/Pulse Strategy Lab styling missing: ${fragment}`);


const envExample=read('.env.example');
for(const fragment of ['PULSE_SCHEDULER_PROFILE=standard','PULSE_SIGNAL_VALIDATION_RETENTION_DAYS=90','PULSE_DISABLE_LIVE_TRADING=true','PULSE_ALLOW_AUTOMATIC_TRADING=false']) must(envExample.includes(fragment),`Safe local environment default missing: ${fragment}`);

const pulseConfig=read('config/pulse.php');
must(pulseConfig.includes("PULSE_SIGNAL_VALIDATION_RETENTION_DAYS', 90"),'Detailed paper-validation retention must default to 90 days');

const seed=read('database/seeders/DatabaseSeeder.php');
for(const fragment of ['strategy_research_enabled','pulse_background_mode','pulse_background_interval_seconds'])
  must(seed.includes(fragment),`Strategy Lab setting default missing: ${fragment}`);

must(hash('app/Services/PulseScannerService.php')==='54763eebcab6af64ef2299d3abaa999ec137731ed7db7b0cb4945e01446decab','Core PulseScannerService changed unexpectedly');
must(hash('public/assets/brand/abs-logo-512.png')==='e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8','Production ABS logo changed unexpectedly');
console.log('ABS V15.3.0 Pulse Strategy Lab & Validation Engine contract: PASS');
