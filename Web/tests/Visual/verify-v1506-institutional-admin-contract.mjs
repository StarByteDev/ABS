import fs from 'node:fs';

const read = file => fs.readFileSync(file, 'utf8');
const must = (condition, label) => {
    if (!condition) { console.error(`FAIL: ${label}`); process.exitCode = 1; }
    else console.log(`PASS: ${label}`);
};

const css = read('public/assets/css/admin-institutional-v1506.css');
const layout = read('resources/views/admin/layout.blade.php');
const dashboard = read('resources/views/admin/dashboard.blade.php');
const charts = read('public/assets/js/admin-analytics-v1505.js');
const controller = read('app/Http/Controllers/Admin/AdminDashboardController.php');

must(layout.includes('admin-institutional-v1506.css'), 'institutional stylesheet loads after the V15.0.5 foundation');
must(layout.includes('admin-product-wordmark') && layout.includes('admin-global-search'), 'selected terminal utility bar is present');
must(layout.includes('Pulse Commercial') && layout.includes('Trading Operations') && layout.includes('Risk & Compliance') && layout.includes('System Administration'), 'compact sidebar uses the approved navigation groups');
must(layout.includes('Packages & Plans') && layout.includes('Revenue & Billing') && layout.includes('Signals Live') && layout.includes('Operational Oversight'), 'approved navigation destinations are preserved');
must(css.includes('--a-bg:#07131f') && css.includes('--a-gold:#e0b348'), 'selected deep-navy and restrained-gold palette is exact');
must(css.includes('grid-template-columns:272px minmax(0,1fr)!important') && css.includes('grid-template-columns:minmax(0,2.15fr) minmax(365px,1fr)'), 'desktop shell and primary analytics split match the approved composition');
must(css.includes('.institutional-kpis') && css.includes('repeat(8,minmax(0,1fr))'), 'desktop dashboard uses the selected eight-card KPI row');
must(css.includes('.institutional-main-grid') && css.includes('.institutional-bottom-grid'), 'terminal analytics hierarchy has all selected rows');
must(css.includes('@media(max-width:900px)') && css.includes('@media(max-width:650px)'), 'institutional shell remains responsive');
must(css.includes('.institutional-date { width:235px!important; height:38px!important; min-height:38px!important; display:flex!important') && css.includes('.institutional-toolbar form { display:flex!important'), 'date range and timeframe controls remain one horizontal toolbar');
must(css.includes('.outcome-row b { display:flex;') && css.includes('.outcome-row b small { display:inline!important;'), 'funnel counts and percentages have explicit separation');
must(dashboard.includes('Signal Performance') && dashboard.includes('Trade Outcome Funnel'), 'primary performance and funnel panels are present');
must(dashboard.includes('Win Rate &amp; PnL Overview') && dashboard.includes('Strategy Confidence') && dashboard.includes('System Status'), 'secondary terminal panels match the approved labels');
must(dashboard.includes('Key Alerts') && dashboard.includes('Top Markets by Signal Activity') && dashboard.includes('Recent Activity'), 'selected bottom operational row is present');
must(dashboard.includes('name="from"') && dashboard.includes('name="to"') && dashboard.includes('name="timeframe"'), 'date and 15M/4H/All filters are functional controls');
must(dashboard.includes('$strategyConfidence') && dashboard.includes('$topMarkets') && dashboard.includes('$recentActivity'), 'dashboard modules use controller data rather than mock values');
must(dashboard.includes("'render'=>'bar'") && dashboard.includes('terminal-chart-legend'), 'signal chart combines approved blue bars with outcome lines and a legend');
must(dashboard.includes("'centerValue'=>$rate") && charts.includes('data.centerValue ?? compact(total)'), 'donut center displays decisive win rate rather than outcome volume');
must(charts.includes("item.render === 'bar'") && charts.includes('percentage = total > 0'), 'chart renderer supports mixed bars and percentage-bearing donut legend');
must(dashboard.includes('strategy-confidence-head') && dashboard.includes("$row['signals']") && dashboard.includes("$row['win_rate']"), 'strategy panel contains the approved four-column evidence table');
must((controller.match(/\['label' => '(Signal Engine|Execution Router|Risk Monitor|User Services|Market Data Feeds|Notifications|Database)'/g) || []).length === 7, 'system status is backed by all seven approved operational controls');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.6 Institutional Terminal visual contract: PASS');
