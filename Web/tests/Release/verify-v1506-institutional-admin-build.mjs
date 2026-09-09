import fs from 'node:fs';

const read = file => fs.readFileSync(file, 'utf8');
const must = (condition, label) => {
    if (!condition) { console.error(`FAIL: ${label}`); process.exitCode = 1; }
    else console.log(`PASS: ${label}`);
};

const controller = read('app/Http/Controllers/Admin/AdminDashboardController.php');
const layout = read('resources/views/admin/layout.blade.php');
const dashboard = read('resources/views/admin/dashboard.blade.php');
const engine = read('app/Services/PulseScannerService.php');
const css = read('public/assets/css/admin-institutional-v1506.css');
const charts = read('public/assets/js/admin-analytics-v1505.js');

must(read('VERSION.txt').includes('15.0.6'), 'release version identifies V15.0.6');
must(layout.includes('admin-institutional-v1506.css'), 'all Admin routes inherit the institutional design system');
must(controller.includes("in_array($request->query('timeframe'), ['15m', '4h']") && controller.includes("whereBetween('generated_at', [$from, $to])"), 'executive filters constrain live dashboard evidence');
must(controller.includes('PulseStrategyLearningState') && controller.includes('topMarkets') && controller.includes('recentActivity'), 'terminal intelligence panels are backed by live data');
must(dashboard.includes('Trade Outcome Funnel') && dashboard.includes('System Status') && dashboard.includes('Key Alerts'), 'selected dashboard composition is complete');
must(layout.includes('admin-nav-section') && layout.includes('All Systems Operational'), 'approved compact navigation and status footer ship in the shared Admin shell');
must(css.includes('grid-template-columns:272px minmax(0,1fr)!important') && css.includes('grid-template-columns:minmax(0,2.15fr) minmax(365px,1fr)'), 'corrected desktop geometry ships in the release');
must(css.includes('.institutional-date { width:235px!important; height:38px!important; min-height:38px!important; display:flex!important'), 'date controls cannot fall back to the previous vertical layout');
must(dashboard.includes("'render'=>'bar'") && charts.includes("item.render === 'bar'"), 'mixed signal bars and outcome lines ship together');
must(dashboard.includes("'centerValue'=>$rate") && charts.includes('data.centerValue ?? compact(total)'), 'win-rate center metric is wired end to end');
must(!dashboard.includes('2,418') && !dashboard.includes('18,642') && !dashboard.includes('128.4K'), 'reference-image sample values were not hard-coded');
must(engine.includes('function analyze(') && engine.includes('function signalBreakdown(') && engine.includes('function confidenceLabel('), 'protected Pulse scoring functions remain present');
must(!controller.includes('scanner_runs_per_day') && !controller.includes('signals_per_day'), 'removed daily quota fields remain absent');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.6 Institutional Admin release contract: PASS');
