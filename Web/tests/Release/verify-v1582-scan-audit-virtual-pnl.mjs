import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const must=(ok,msg)=>{if(!ok){console.error('FAIL:',msg);process.exit(1);}};

const version=read('VERSION.txt').trim();
const build=read('BUILD_VERSION.txt');
const readme=read('README.md');
const changelog=read('CHANGELOG.md');
const controller=read('app/Http/Controllers/Admin/AdminStrategyDashboardController.php');
const workflow=read('app/Http/Controllers/Admin/AdminStrategyWorkflowController.php');
const analytics=read('app/Services/PulseStrategyAnalyticsService.php');
const perfView=read('resources/views/admin/pulse/workflow/strategy-performance.blade.php');
const resultsView=read('resources/views/admin/pulse/workflow/trade-results.blade.php');
const dashView=read('resources/views/admin/pulse/strategy-dashboard.blade.php');
const migration=read('database/migrations/2026_10_04_001000_optimize_pulse_scan_audit_sorting.php');
const schema=read('app/Support/PulseSchemaRepair.php');
const app=read('app/Http/Controllers/Api/V1/AppController.php');

must(version==='15.8.2','VERSION is not 15.8.2');
must(build.includes('V15.8.2'),'BUILD_VERSION is not V15.8.2');
must(app.includes("'build' => '15.8.2'"),'API build identity is not 15.8.2');
must(readme.startsWith('## ABS V15.8.2'),'README does not start with V15.8.2');
must(changelog.startsWith('# ABS V15.8.2'),'CHANGELOG does not start with V15.8.2');

must(controller.includes("->select('pulse_scanner_runs.id')"),'scan audit does not paginate IDs first');
must(controller.includes("->orderByDesc('pulse_scanner_runs.id')"),'scan audit still depends on started_at filesort ordering');
must(controller.includes('$runs->setCollection'),'scan audit does not rehydrate paginator models');
must(controller.includes('->chunkById(100'),'scan audit does not chunk JSON-heavy aggregate evidence');
must(migration.includes("['started_at', 'id']"),'scanner-run composite performance index missing');
must(schema.includes('pulse_scanner_runs_started_at_id_idx'),'Schema Repair scan-audit index missing');

must(analytics.includes('VIRTUAL_NOTIONAL_USD = 100.0'),'fixed $100 virtual notional missing');
must(analytics.includes('virtualPnlForValidation'),'virtual P/L calculator missing');
must(analytics.includes('virtualSummaryFromValidations'),'overall virtual summary missing');
must(analytics.includes("$direction === 'SHORT'"),'SHORT virtual P/L direction handling missing');

for (const token of ['NET VIRTUAL P/L','VIRTUAL ROI','$100 virtual P/L','TP1 / TP2 / TP3']) {
  must(perfView.includes(token),`Strategy Intelligence missing ${token}`);
}
must(resultsView.includes('name="strategy"'),'Trade Results strategy filter missing');
must(resultsView.includes('$100 P/L'),'Trade Results per-trade $100 column missing');
must(workflow.includes('virtual_net_pnl_usd'),'Workflow strategy virtual P/L attribution missing');
must(dashView.includes('$100 virtual net P/L'),'Strategy dashboard virtual money summary missing');

console.log('PASS: ABS V15.8.2 scan-audit shared-hosting fix and $100 virtual strategy performance reporting');
