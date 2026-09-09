import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => {
    if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
    else console.log(`PASS: ${message}`);
};

const version = read('VERSION.txt');
const build = read('BUILD_VERSION.txt');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const layout = read('resources/views/admin/layout.blade.php');
const pulseController = read('app/Http/Controllers/Admin/AdminPulseController.php');
const signalView = read('resources/views/admin/pulse/signals.blade.php');
const tradeView = read('resources/views/admin/pulse/trades.blade.php');
const auditView = read('resources/views/admin/pulse/logs.blade.php');
const dashboardController = read('app/Http/Controllers/Admin/AdminDashboardController.php');
const dashboardView = read('resources/views/admin/dashboard.blade.php');
const userController = read('app/Http/Controllers/Admin/AdminUserController.php');
const userView = read('resources/views/admin/users.blade.php');
const enterpriseController = read('app/Http/Controllers/Admin/AdminEnterpriseController.php');
const newsController = read('app/Http/Controllers/Admin/AdminContentController.php');
const contentIndex = read('resources/views/admin/enterprise/content-index.blade.php');
const contentForm = read('resources/views/admin/enterprise/content-form.blade.php');
const newsIndex = read('resources/views/admin/content/index.blade.php');
const supportView = read('resources/views/admin/enterprise/contacts.blade.php');
const newsletterView = read('resources/views/admin/enterprise/newsletters.blade.php');
const strategyView = read('resources/views/admin/pulse/strategies.blade.php');
const settingsView = read('resources/views/admin/pulse/settings.blade.php');
const css = read('public/assets/css/abs-app.css');
const js = read('public/assets/js/abs-app.js');

must(version.trim() === 'ABS V15.0.4', 'release version identifies V15.0.4');
must(build.includes('Premium Admin Reporting & Signal Oversight'), 'build identifies premium Admin reporting release');
must(bootstrap.includes("'build' => '15.0.4'"), 'mobile bootstrap reports V15.0.4');

must(layout.includes('Enterprise Control Center') && layout.includes('Users & Access Levels'), 'Admin navigation uses clear enterprise terminology');
must(layout.includes('Signal Oversight') && layout.includes('Strategy Intelligence') && layout.includes('Trade & Protection'), 'trading operations are separated by purpose');
must(layout.includes('Market News CMS') && layout.includes('Research CMS') && layout.includes('Learning CMS') && layout.includes('Economic Calendar'), 'complete CMS navigation remains visible');
must(layout.includes("@yield('description'") && layout.includes('data-admin-menu'), 'shared shell provides page context and responsive navigation');

for (const field of ['name="from"','name="to"','name="user"','name="symbol"','name="timeframe"','name="direction"','name="status"','name="outcome"']) {
    must(signalView.includes(field), `Signal Oversight includes ${field.replace('name=', '')} filter`);
}
must(pulseController.includes("->whereBetween('generated_at', [$from, $to])"), 'Signal report applies the selected date range');
must(pulseController.includes("where('outcome', 'tp')") && pulseController.includes("where('outcome', 'sl')"), 'Signal report calculates TP and SL outcomes');
must(pulseController.includes("'entry_rate'") && pulseController.includes("'win_rate'") && pulseController.includes("'average_confidence'"), 'Signal report calculates entry, decisive result and confidence KPIs');
must(signalView.includes('Decisive win rate') && signalView.includes('Same-minute TP + SL remains ambiguous'), 'Signal UI explains industry-specific result rules');
must(signalView.includes('Sparks used') && signalView.includes('Validation result') && signalView.includes('Technical'), 'Signal table joins commerce, validation and confidence evidence');

must(pulseController.includes("whereBetween('created_at',[$from,$to])") && pulseController.includes("'protection_review'"), 'Trade report supports dates and protection-risk aggregation');
must(tradeView.includes('Profitable close rate') && tradeView.includes('Realized P&amp;L') && tradeView.includes('Practice + live'), 'Trade UI reports execution and financial outcomes clearly');
must(auditView.includes('Audit event register') && auditView.includes('name="from"') && auditView.includes('name="user"'), 'Audit Trail supports date and actor review');

must(dashboardController.includes("'userLevels'") && dashboardController.includes("'tradingReport'") && dashboardController.includes("'commerceReport'") && dashboardController.includes("'contentReport'"), 'Executive backend separates user, trading, commerce and CMS reporting');
must(dashboardController.includes("'controlStatus'") && dashboardController.includes('PulseMarketDataService'), 'Executive dashboard receives operational health controls');
must(dashboardView.includes('User levels & service reach') && dashboardView.includes('Publishing readiness') && dashboardView.includes('Package adoption & renewal exposure'), 'Executive dashboard presents decision-ready reporting domains');
must(userController.includes("'Standard users'") && userController.includes("'Private members'") && userController.includes("'Administrators'"), 'user-level counts are explicit');
must(userView.includes('Standard User') && userView.includes('Private Member') && userView.includes('Administrator'), 'user directory explains access levels');

must(enterpriseController.includes("'updated30'") && enterpriseController.includes("'filterOptions'"), 'multi-channel CMS provides reporting summaries and classifications');
must(newsController.includes("'published'") && newsController.includes("'featured'") && newsController.includes("'updated30'"), 'News CMS reports editorial pipeline health');
must(contentIndex.includes('Editorial register') && contentIndex.includes('Editorial filters'), 'Research, learning, event and product CMS share a reporting register');
must(contentForm.includes('Publishing checklist') && contentForm.includes('Customer-safe language'), 'generic CMS editor includes professional publishing guidance');
must(newsIndex.includes('Homepage headlines') && newsIndex.includes('Publication status'), 'News CMS exposes placement and publication controls');
must(supportView.includes('Urgent attention') && supportView.includes('Support queue'), 'support CMS reports priority workload');
must(newsletterView.includes('Active reach') && newsletterView.includes('Audience filters'), 'newsletter CMS reports audience reach and consent state');
must(strategyView.includes('Strategy catalog') && strategyView.includes('Strategy Results') && strategyView.includes('<details'), 'strategy controls are scannable with progressive-disclosure editors');
must(settingsView.includes('PULSE GOVERNANCE') && settingsView.includes('Audit coverage'), 'Pulse safety settings explain authority and traceability');

for (const selector of ['.admin-report-kpis','.admin-control-status','.admin-insight-band','.admin-signal-table','.signal-report-filters','.cms-editor-guidance','.admin-mobile-menu']) {
    must(css.includes(selector), `premium Admin stylesheet contains ${selector}`);
}
must(css.includes('@media(max-width:900px)') && css.includes('admin-nav-open'), 'Admin shell has responsive navigation behavior');
must(js.includes('data-admin-menu') && js.includes("event.key === 'Escape'"), 'responsive Admin navigation is keyboard-dismissible');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.4 premium Admin reporting contract: PASS');
