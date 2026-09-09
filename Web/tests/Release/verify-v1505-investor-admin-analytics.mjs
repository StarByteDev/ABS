import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => {
    if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
    else console.log(`PASS: ${message}`);
};
const extractMethod = (source, name) => {
    const start = source.indexOf(`function ${name}(`);
    if (start < 0) return '';
    const opening = source.indexOf('{', start);
    let depth = 0, quote = null, lineComment = false, blockComment = false;
    for (let index = opening; index < source.length; index += 1) {
        const char = source[index], next = source[index + 1];
        if (lineComment) { if (char === '\n') lineComment = false; continue; }
        if (blockComment) { if (char === '*' && next === '/') { blockComment = false; index += 1; } continue; }
        if (quote) { if (char === '\\') { index += 1; continue; } if (char === quote) quote = null; continue; }
        if (char === '/' && next === '/') { lineComment = true; index += 1; continue; }
        if (char === '/' && next === '*') { blockComment = true; index += 1; continue; }
        if (char === '"' || char === "'") { quote = char; continue; }
        if (char === '{') depth += 1;
        if (char === '}' && --depth === 0) return source.slice(start, index + 1);
    }
    return '';
};
const sha = value => crypto.createHash('sha256').update(value).digest('hex');

const version = read('VERSION.txt');
const build = read('BUILD_VERSION.txt');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const layout = read('resources/views/admin/layout.blade.php');
const appLayout = read('resources/views/layouts/app.blade.php');
const authLayout = read('resources/views/layouts/auth.blade.php');
const pulseLayout = read('resources/views/pulse/layout.blade.php');
const adminCss = read('public/assets/css/admin-premium-v1505.css');
const adminJs = read('public/assets/js/admin-analytics-v1505.js');
const adminController = read('app/Http/Controllers/Admin/AdminPulseController.php');
const dashboardController = read('app/Http/Controllers/Admin/AdminDashboardController.php');
const enterpriseController = read('app/Http/Controllers/Admin/AdminEnterpriseController.php');
const dashboard = read('resources/views/admin/dashboard.blade.php');
const intelligence = read('resources/views/admin/pulse/intelligence.blade.php');
const signals = read('resources/views/admin/pulse/signals.blade.php');
const trades = read('resources/views/admin/pulse/trades.blade.php');
const emails = read('resources/views/admin/enterprise/emails.blade.php');
const scheduler = read('routes/console.php');
const seeder = read('database/seeders/DatabaseSeeder.php');
const scanner = read('app/Services/PulseScannerService.php');
const paginator = read('resources/views/vendor/pagination/abs-admin.blade.php');

must(version.trim() === 'ABS V15.0.5', 'release version identifies V15.0.5');
must(build.includes('Investor Analytics & Premium Admin'), 'build identifies the investor analytics release');
must(bootstrap.includes("'build' => '15.0.5'"), 'mobile bootstrap identifies V15.0.5');

must(layout.includes('admin-premium-v1505.css') && layout.includes('admin-analytics-v1505.js'), 'Admin loads isolated V15.0.5 assets after the legacy application layer');
must(adminCss.includes('flex-direction:column!important') && adminCss.includes('.enterprise-admin-nav>a span'), 'sidebar navigation is forced into a stable vertical, wrapping-safe layout');
must(adminCss.includes('.admin-chart-grid') && adminCss.includes('.admin-row-control:not([open])'), 'Admin CSS provides charts and collapsed row controls');
must(adminJs.includes('function lineChart') && adminJs.includes('function barChart') && adminJs.includes('function donutChart'), 'local analytics engine implements line, bar and donut reports');
must(!adminJs.includes('fetch(') && !adminJs.includes('import('), 'Admin charts have no external runtime dependency');

for (const [name, source] of [['public', appLayout], ['authentication', authLayout], ['Pulse', pulseLayout], ['Admin', layout]]) {
    must(source.includes("asset('favicon.svg')") && source.includes('apple-touch-icon'), `${name} layout includes SVG and touch favicon coverage`);
}

must(dashboardController.includes('$dashboardTrend') && dashboardController.includes("'dashboardOutcomeMix' =>"), 'Executive controller supplies trend and outcome reporting');
must(dashboard.includes('Pulse activity and resolved outcomes') && dashboard.includes('Validation mix') && dashboard.includes('MANAGEMENT INTERPRETATION'), 'Executive dashboard is chart-led and management-readable');
must((dashboard.match(/data-admin-chart/g) || []).length >= 2, 'Executive dashboard renders multiple analytics charts');

for (const [name, source] of [['Signal Oversight', signals], ['Strategy Intelligence', intelligence], ['Execution & Risk', trades]]) {
    must(source.indexOf('name="from"') >= 0 && source.indexOf('name="to"') >= 0, `${name} exposes from/to date filters`);
    must(source.indexOf('name="from"') < source.indexOf('admin-report-kpis'), `${name} places its date filter before KPI reporting`);
    must((source.match(/data-admin-chart/g) || []).length >= 2, `${name} includes trend and composition charts`);
}

must(adminController.includes("->whereBetween('pulse_signals.generated_at', [$from, $to])"), 'Signal report applies a qualified selected date range');
must(adminController.includes("leftJoin('pulse_signal_validations as report_validation'") && adminController.includes('$signalTrend'), 'Signal trend derives from the same filtered signal query and validation evidence');
must(signals.includes('PURPOSE OF SIGNAL OVERSIGHT') && signals.includes('price precision adapted to the market'), 'Signal page explains its purpose and readable price handling');
must(signals.includes('<details class="admin-row-control">') && signals.includes("links('vendor.pagination.abs-admin')"), 'Signal editing is progressive and pagination is explicit');

must(adminController.includes('$allTimeStrategyRows') && adminController.includes("'all_time_win_rate'") && adminController.includes("'range_confidence_delta'"), 'Strategy backend compares selected-period and all-time evidence');
must(adminController.includes("'confidence_impact' => $reliability === null ? null : ($reliability - 50.0) * 0.25"), 'actual live confidence contribution remains the 25% learned reliability component');
must(intelligence.includes('selected period vs all time') && intelligence.includes('Current live influence') && intelligence.includes('Period vs all time'), 'Strategy report separates recent comparison from actual live influence');
must(intelligence.includes('Ambiguous: TP and SL order cannot be proven'), 'Strategy report explains ambiguous outcome handling');

must(adminController.includes('$tradeTrend') && adminController.includes("'profitable'=>$profitable") && adminController.includes("'protection_rate'"), 'Execution backend provides filtered trend, close and protection measures');
must(trades.includes('Exchange-recorded result') && trades.includes('Practice (testnet)') && trades.includes('Profitable close rate'), 'Execution register uses professional, qualified wording');
must(trades.includes('commission_asset') && trades.includes('Exchange references'), 'Execution register preserves financial context and exchange traceability');

must(enterpriseController.includes("PulseSystemSetting::value('expiry_reminder_days'") && enterpriseController.includes("'regex:/^\\s*\\d{1,2}"), 'Admin validates configurable 0–90-day expiry thresholds');
must(seeder.includes("['expiry_reminder_days', '[7,3,1,0]', 'json'"), 'fresh installations seed the default expiry reminder policy');
must(scheduler.includes('$reminderDays') && scheduler.includes('in_array($daysLeft, $reminderDays, true)'), 'scheduled expiry delivery uses the Admin-configured thresholds');
must(scheduler.includes("data_get($log->metadata, 'access_id')") && scheduler.includes("$log->status === 'sent'"), 'expiry reminder delivery remains idempotent per access');
must(emails.includes('Plan expiry reminder policy') && emails.includes('Upcoming audience') && emails.includes('08:00 UTC'), 'Email Admin exposes policy, audience and schedule');
must(paginator.includes('abs-admin-pagination') && paginator.includes('$paginator->total()'), 'professional pagination reports exact result range and total');

const expectedMethods = {
    analyze: '471140c16db31b8cf8f0f10c4d566a5278a2becb99fe505ea5b54875aae0d06e',
    signalBreakdown: '6b378e48ea044b12c82940fe7f9a7001b87bce5dbb4e92667bcb12007f46ea63',
    confidenceLabel: '7b78dc33510b63469ff5013f063e7360f082870a815ded7a6dd7560bc94e05a8',
};
for (const [method, expected] of Object.entries(expectedMethods)) must(sha(extractMethod(scanner, method)) === expected, `${method} calculation remains byte-identical`);
must(!scanner.includes('scanner_runs_per_day') && !scanner.includes('signals_per_day'), 'no daily scanner or signal quota was reintroduced');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.5 investor analytics and premium Admin contract: PASS');
