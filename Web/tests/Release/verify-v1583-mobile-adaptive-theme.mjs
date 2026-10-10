import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const must=(condition,message)=>{ if(!condition){ console.error(`FAIL: ${message}`); process.exitCode=1; } };

const version=read('VERSION.txt').trim();
const build=read('BUILD_VERSION.txt').trim();
const api=read('routes/api.php');
const app=read('app/Http/Controllers/Api/V1/AppController.php');
const pulse=read('app/Http/Controllers/Api/V1/PulseController.php');
const scanner=read('app/Services/PulseScannerService.php');
const adaptive=read('app/Services/PulseAdaptiveStrategyService.php');
const macro=read('app/Services/PulseMacroRiskService.php');
const learning=read('app/Services/PulseLearningService.php');
const config=read('config/pulse.php');
const migration=read('database/migrations/2026_10_08_000100_ensure_v1583_adaptive_strategy_defaults.php');
const performance=read('resources/views/admin/pulse/workflow/strategy-performance.blade.php');
const theme=read('public/assets/css/abs-theme-v1583.css');
const adminLayout=read('resources/views/admin/layout.blade.php');
const pulseLayout=read('resources/views/pulse/layout.blade.php');
const publicLayout=read('resources/views/layouts/app.blade.php');
const readme=read('README.md');
const changelog=read('CHANGELOG.md');
const tls=read('docs/MOBILE_API_TLS_RECOVERY_V15_8_3.md');

must(version==='15.8.3','VERSION is not 15.8.3');
must(build.includes('V15.8.3'),'BUILD_VERSION is not V15.8.3');
must(readme.startsWith('## ABS V15.8.3'),'README does not start with V15.8.3');
must(changelog.startsWith('# ABS V15.8.3'),'CHANGELOG does not start with V15.8.3');
must(app.includes("'build' => '15.8.3'"),'mobile bootstrap/API build identity not updated');
must(app.includes("'adaptive_strategy_intelligence'"),'mobile bootstrap does not advertise additive adaptive intelligence module');

for(const route of [
  "Route::get('/', [AppController::class, 'health'])",
  "Route::get('/health', [AppController::class, 'health'])",
  "Route::post('/auth/login', [AuthController::class, 'login'])",
  "Route::get('/bootstrap', [AppController::class, 'bootstrap'])",
  "Route::post('/scanner/run', [PulseController::class, 'runScanner'])",
  "Route::get('/signals', [PulseController::class, 'signals'])",
  "Route::get('/trades', [PulseController::class, 'trades'])",
  "Route::get('/reports/strategies', [PulseController::class, 'strategyPerformance'])",
  "Route::get('/account', [PrivatePortalController::class, 'account'])",
]) must(api.includes(route),`critical mobile/API route missing: ${route}`);

must(pulse.includes("'adaptive'=>$adaptiveProfile"),'strategy report did not add adaptive data');
must(pulse.includes("'adaptive_status' => $item['adaptive_status'] ?? null") && pulse.includes("'member_eligible' => $item['member_eligible'] ?? null"),'mobile signal payload does not expose additive adaptive evidence');
must(pulse.includes("'strategy_slug'=>$slug") && pulse.includes("'model_net_r'"),'legacy strategy performance fields were not retained');

must(scanner.includes('PulseAdaptiveStrategyService $adaptive'),'scanner does not inject adaptive strategy service');
must(scanner.includes('bool $adaptive = true'),'scanner analyze does not support adaptive mode');
must(scanner.includes("$user !== null"),'authenticated/member scan adaptive switch missing');
must(scanner.includes("'atr-volatility' => $atrPercent") && scanner.includes("['NEUTRAL', 4"),'ATR filter still appears directional');
must(scanner.includes("'range-compression' => $bbWidth") && scanner.includes("['NEUTRAL', 4"),'range compression still appears directional');
must(scanner.includes("'risk-reward-quality' => $atr > 0 ? ['NEUTRAL'"),'risk/reward filter still appears directional');
must(scanner.includes('minimum_contributors'),'confluence minimum is not enforced');
must(scanner.includes('coreContributors'),'core/entry contributor gate is missing');
must(scanner.includes('strategyCategory('),'safe category fallback missing');
must(scanner.includes('PulseMacroRiskService $macroRisk') && scanner.includes("'macroRisk' => $macroRisk"),'macro-event risk gate is not wired into scanner');
must(macro.includes("where('impact', 'high')") && macro.includes('economic_events'),'macro risk service does not use high-impact economic-calendar evidence');
must(config.includes("'macro_event_gate_enabled'"),'macro event gate config missing');
must(scanner.includes("return 'engine-15.8.3-'"),'strategy bundle identity is not V15.8.3');

must(adaptive.includes("'research_only'"),'persistently weak strategy research-only state missing');
must(adaptive.includes("'throttled'"),'negative-expectancy throttling missing');
must(adaptive.includes('baseRr - 0.50') && adaptive.includes('baseRr + 0.50'),'bounded adaptive R:R guard missing');
must(adaptive.includes('expected_r'),'adaptive expectancy evidence missing');

must(learning.includes("'rr_optimizer'"),'learning state does not persist R:R optimizer evidence');
must(learning.includes('mfe_r') && learning.includes('mae_r'),'R:R optimizer is not based on MFE/MAE evidence');
must(learning.includes("'method'=>'mfe_mae_counterfactual_v1'"),'optimizer method identity missing');
must(config.includes("'optimizer' => [") && config.includes("'rr_candidates'"),'optimizer config missing');

must(migration.includes("'atr-volatility'=>['Market Regime Filter'"),'ATR role migration missing');
must(migration.includes("'risk-reward-quality'=>['Risk Filter'"),'risk filter migration missing');
must(migration.includes("$settings += ["),'migration does not preserve existing custom settings');
must(!/dropTable|truncate\(|->delete\(/.test(migration),'V15.8.3 migration contains destructive database operation');

must(performance.includes('Adaptive R:R') && performance.includes('Adaptive Weight'),'Strategy Performance does not expose adaptive controls/evidence');
must(performance.includes('Research only') || performance.includes('Research Only'),'research-only status is not visible in Strategy Performance');

must(theme.includes('--abs83-bg:#07121d') && theme.includes('--abs83-cyan:#67d7f2'),'premium dark theme tokens missing');
for(const [name,layout] of [['admin',adminLayout],['pulse',pulseLayout],['public',publicLayout]]){
  must(layout.includes('abs-theme-v1583.css'),`${name} layout does not load V15.8.3 theme`);
  must(layout.includes('v=15.8.3'),`${name} layout theme cache version not updated`);
}

must(tls.includes('NET::ERR_CERT_COMMON_NAME_INVALID'),'TLS recovery guide does not address supplied certificate error');
must(tls.includes('Do **not** disable certificate validation'),'TLS recovery guide lacks secure no-bypass rule');

if(process.exitCode){ process.exit(process.exitCode); }
console.log('PASS: ABS V15.8.3 mobile API compatibility, adaptive strategy optimizer and premium theme contract');
