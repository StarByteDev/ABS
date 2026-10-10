import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const root=process.cwd();
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const check=(f,terms)=>{const v=read(f);for(const term of terms)assert.ok(v.includes(term),`${f} missing ${term}`)};
assert.ok(['15.8.6-candidate','15.8.7-theme-hotfix-candidate'].includes(read('VERSION.txt').trim()));
check('app/Services/PulseSignalValidationService.php',['rebuildDate(Carbon::parse($date), false)','refreshLearningStates()']);
check('app/Services/PulseAdaptiveStrategyService.php',['recent_loss_throttle','observed_expectancy_r','recent_expectancy_r','member_eligible']);
check('app/Services/PulseScannerService.php',["(! $adaptive || (bool) ($profile['member_eligible'] ?? true))",'queueForAlert($signalAlert)']);
check('app/Services/PulseTradeService.php',['queueForAlert($alert)']);
check('app/Services/PulseResearchReportService.php',['overall','strategies','net_pnl_usd','virtualPnlForValidation']);
check('app/Services/PulseMobilePushDispatcher.php',['insertOrIgnore','push_token','attempts','notification_preferences']);
check('database/migrations/2026_10_09_000101_create_pulse_push_deliveries.php',['pulse_delivery_once','function down()']);
check('routes/console.php',['abs:send-pulse-push']);
check('routes/api.php',['/reports/strategy-health','/push/test','/push/status','auth:sanctum']);
check('routes/web.php',['/strategy-performance/export']);
check('resources/views/admin/pulse/workflow/strategy-performance.blade.php',['Export P/L CSV','Recent 14D expectancy']);
check('resources/views/admin/pulse/settings.blade.php',['send_push']);
for(const file of ['resources/views/admin/layout.blade.php','resources/views/layouts/app.blade.php','resources/views/pulse/layout.blade.php']) {
  check(file,['abs-theme-v1586.css','abs-theme-v1587.js',"@stack('head')"]);
  const src=read(file); assert.ok(src.indexOf('abs-theme-v1586.css') > src.indexOf("@stack('head')"),`${file}: theme loaded before page CSS`);
}
check('public/assets/css/abs-theme-v1586.css',['.wf-table tbody tr','.pulse-global-header','.admin-sidebar','[data-abs-theme="light"]']);
console.log('ABS V15.8.6 static safety contracts: PASS');
