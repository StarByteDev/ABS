import fs from 'node:fs';

const read = file => fs.readFileSync(file, 'utf8');
const must = (condition, label) => {
    if (!condition) { console.error(`FAIL: ${label}`); process.exitCode = 1; }
    else console.log(`PASS: ${label}`);
};

const css = read('public/assets/css/admin-premium-v1505.css');
const js = read('public/assets/js/admin-analytics-v1505.js');
const layout = read('resources/views/admin/layout.blade.php');
const dashboard = read('resources/views/admin/dashboard.blade.php');
const intelligence = read('resources/views/admin/pulse/intelligence.blade.php');
const signals = read('resources/views/admin/pulse/signals.blade.php');
const trades = read('resources/views/admin/pulse/trades.blade.php');

must(css.includes('--a-navy:#071321') && css.includes('--a-gold:#d5a83c'), 'Admin palette uses premium navy, blue and gold');
must(css.includes('grid-template-columns:284px minmax(0,1fr)') && css.includes('flex-direction:column!important'), 'desktop shell reserves a stable sidebar and vertical navigation');
must(css.includes('@media(max-width:900px)') && css.includes('admin-nav-open'), 'mobile shell uses an accessible navigation drawer');
must(css.includes('.admin-chart-card') && css.includes('.admin-donut-layout') && css.includes('.chart-axis-label'), 'analytics charts have complete card, axis and composition styling');
must(css.includes('.admin-purpose-card') && css.includes('.admin-purpose-list'), 'non-expert explanations have a consistent visual component');
must(css.includes('.abs-admin-pagination') && css.includes('.abs-pagination-links'), 'pagination is styled without Bootstrap/Tailwind dependency');
must(css.includes('.admin-row-control:not([open])>*:not(summary)') && css.includes('position:absolute!important'), 'row correction forms remain collapsed and do not expand every table row');
must(layout.includes('Enterprise Control Center') && layout.includes('CONTENT STUDIO') && layout.includes('SYSTEM CONTROL'), 'complete Admin navigation remains grouped for demonstrations');
must((dashboard.match(/admin-chart-card/g) || []).length >= 2 && dashboard.includes('EXECUTIVE BRIEFING'), 'executive view leads with multiple reports and a briefing');
must(signals.includes('Date range and report filters') && signals.includes('Lifecycle outcome mix'), 'Signal Oversight has visible dates and visual outcome hierarchy');
must(intelligence.includes('Report period and strategy filters') && intelligence.includes('Strategy performance: selected period vs all time'), 'Intelligence has clear date and comparison hierarchy');
must(trades.includes('Date range and execution filters') && trades.includes('Execution register'), 'Trades uses clear reporting and register hierarchy');
must(js.includes('tabindex') && js.includes('<title>') === false && js.includes("svgNode('title')"), 'chart values are keyboard discoverable with SVG titles');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.5 investor Admin visual contract: PASS');
