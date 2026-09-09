import fs from 'node:fs';

const read = file => fs.readFileSync(file,'utf8');
const css=read('public/assets/css/abs-app.css');
const layout=read('resources/views/admin/layout.blade.php');
const signals=read('resources/views/admin/pulse/signals.blade.php');
const dashboard=read('resources/views/admin/dashboard.blade.php');
const cms=read('resources/views/admin/enterprise/content-index.blade.php');
const must=(condition,label)=>{if(!condition){console.error(`FAIL: ${label}`);process.exitCode=1;}else console.log(`PASS: ${label}`);};

must(css.includes('--admin-navy:#07111f') && css.includes('--admin-gold:#d3a52f'), 'Admin uses the ABS navy, blue and gold visual system');
must(layout.includes('admin-heading-block') && layout.includes('admin-environment-chip') && layout.includes('admin-account-avatar'), 'shared header has context, environment and administrator identity');
must(layout.includes('nav-glyph') && layout.includes('CONTENT STUDIO') && layout.includes('SYSTEM CONTROL'), 'navigation has scannable groups and visual anchors');
must(signals.includes('signal-kpi-grid') && signals.includes('admin-insight-band') && signals.includes('signal-report-table'), 'Signal Oversight has KPI, explanation and detailed report hierarchy');
must(signals.indexOf('admin-report-kpis') < signals.indexOf('signal-filter-surface') && signals.indexOf('signal-filter-surface') < signals.indexOf('signal-report-table'), 'Signal page follows summary, filters, results reading order');
must(dashboard.includes('admin-control-status') && dashboard.includes('admin-report-columns') && dashboard.includes('renewal-priority-list'), 'Executive dashboard prioritizes control health and reporting domains');
must(cms.includes('cms-kpis') && cms.includes('cms-filter-grid') && cms.includes('cms-record-table'), 'CMS uses consistent reporting components');
must(css.includes('grid-template-columns:repeat(4,minmax(0,1fr))') && css.includes('@media(max-width:620px)'), 'report grids have desktop and mobile layouts');
must(css.includes('.admin-body .enterprise-surface.no-pad{padding:0!important}') && css.includes('.enterprise-table tbody tr:hover'), 'tables use compact premium surfaces and hover feedback');
must(css.includes('.admin-row-control form{position:absolute') && css.includes('.admin-empty-report'), 'row actions and empty states avoid oversized table controls');

if(process.exitCode)process.exit(process.exitCode);
console.log('ABS V15.0.4 Admin demo visual contract: PASS');
