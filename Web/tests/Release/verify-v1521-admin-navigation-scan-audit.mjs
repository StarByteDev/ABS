import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
const root=process.cwd();
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const exists=f=>fs.existsSync(path.join(root,f));
const must=(c,m)=>{if(!c) throw new Error(m)};
const hash=f=>crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex');

must(read('VERSION.txt').trim()==='15.2.1','VERSION must be 15.2.1');
for(const f of [
  'public/assets/css/admin-navigation-v1521.css',
  'public/assets/js/admin-navigation-v1521.js',
  'public/assets/css/admin-scan-audit-v1521.css',
  'resources/views/admin/pulse/scan-audit.blade.php',
  'resources/views/admin/pulse/scan-audit-show.blade.php',
  'docs/ABS_V15_2_1_ADMIN_NAVIGATION_SCAN_AUDIT.md',
]) must(exists(f),`Missing V15.2.1 artifact: ${f}`);

const layout=read('resources/views/admin/layout.blade.php');
for(const fragment of [
  'Overview &amp; Members','Strategy Engine &amp; Evaluation','Revenue &amp; Advertising',
  'Content &amp; Communications','Reporting &amp; System','data-sidebar-collapse','data-sidebar-hide',
  'data-sidebar-show','data-nav-group-toggle','Scan Audit','Signals &amp; Outcomes','Trades &amp; Execution','Audit Trail'
]) must(layout.includes(fragment),`Categorized sidebar missing: ${fragment}`);

const navJs=read('public/assets/js/admin-navigation-v1521.js');
for(const fragment of ['expanded','collapsed','hidden','abs.admin.sidebar.state','abs.admin.nav.group.','data-admin-menu'])
  must(navJs.includes(fragment),`Sidebar behavior missing: ${fragment}`);

const routes=read('routes/web.php');
for(const fragment of ["/scan-audit","/scan-audit/{scannerRun}","scanAudit","scanAuditShow"])
  must(routes.includes(fragment),`Scan Audit route missing: ${fragment}`);

const controller=read('app/Http/Controllers/Admin/AdminStrategyDashboardController.php');
for(const fragment of [
  'public function scanAudit(','public function scanAuditShow(','scanAuditRow(',
  'qualified_candidates','market_timeframes_evaluated','market_timeframes_unavailable',
  "where('outcome','tp')","where('outcome','sl')","expired_no_entry","expired_after_entry",
  "where('close_reason','take_profit')","where('close_reason','stop_loss')","realized_pnl"
]) must(controller.includes(fragment),`Scan Audit backend missing: ${fragment}`);

const audit=read('resources/views/admin/pulse/scan-audit.blade.php');
for(const fragment of [
  'Scans performed','Pairs scanned','Qualified candidates','Signals published','Research TP','Research SL',
  'Backend trades','Actual realized P&amp;L','CHRONOLOGICAL REGISTER','Published signal','Research outcome','Backend execution','Open trace'
]) must(audit.includes(fragment),`Scan Audit register UX missing: ${fragment}`);

const detail=read('resources/views/admin/pulse/scan-audit-show.blade.php');
for(const fragment of [
  'SCAN','MARKETS','QUALIFIED','SIGNAL','ENTRY','RESULT','EXECUTION',
  'MARKET-BY-MARKET EVALUATION','BACKEND EXCHANGE EXECUTION','INDEPENDENT MARKET VALIDATION','RAW TRACE'
]) must(detail.includes(fragment),`Scan detail trace missing: ${fragment}`);

const strategy=read('resources/views/admin/pulse/strategy-dashboard.blade.php');
must(strategy.includes("route('admin.pulse.scan-audit')"),'Strategy Dashboard must link to Scan Audit');

must(hash('app/Services/PulseScannerService.php')==='54763eebcab6af64ef2299d3abaa999ec137731ed7db7b0cb4945e01446decab','Core PulseScannerService changed unexpectedly');
must(hash('public/assets/brand/abs-logo-512.png')==='e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8','Production ABS logo changed unexpectedly');
console.log('ABS V15.2.1 Admin Navigation + Scan Audit contract: PASS');
