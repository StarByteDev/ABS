import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const exists = rel => fs.existsSync(path.join(root, rel));
const failures = [];
const must = (ok, msg) => { if (!ok) failures.push(msg); };

must(read('VERSION.txt').includes('15.0.9'), 'release identity is V15.0.9');
must(exists('public/assets/css/admin-executive-v1509.css'), 'V15.0.9 final Admin stylesheet exists');

const layout = read('resources/views/admin/layout.blade.php');
must(layout.includes('admin-executive-v1509.css'), 'Admin layout loads V15.0.9 stylesheet');
must(layout.indexOf('admin-executive-v1509.css') > layout.indexOf('admin-executive-v1508.css'), 'V15.0.9 stylesheet loads after V15.0.8');
must(layout.includes('abs-admin-v1509'), 'V15.0.9 body class is active');
must((layout.match(/@include\('partials\.logo'\)/g) || []).length === 1, 'corporate logo partial is rendered once');
const strip = layout.slice(layout.indexOf('<section class="abs-exec-product-strip"'), layout.indexOf('</section>', layout.indexOf('<section class="abs-exec-product-strip"')));
must(!strip.includes('<img'), 'ABS Pulse product strip does not duplicate the corporate logo');
must(strip.includes('ABS</span> Pulse') && strip.includes('A PRODUCT OF ALPHA BLOCK SOLUTIONS'), 'text-led ABS Pulse product identity remains clear');

const css = read('public/assets/css/admin-executive-v1509.css');
for (const token of [
  '.enterprise-table tbody td',
  'background:#041725!important',
  '.enterprise-pagination',
  '.admin-reference-list>summary',
  '.enterprise-quick-links',
  '.abs-exec-donut .admin-chart-legend{display:none!important}',
]) must(css.includes(token), `V15.0.9 CSS missing consistency rule: ${token}`);
must(!/\.enterprise-table tbody td[^}]*background\s*:\s*#fff/i.test(css), 'V15.0.9 table override does not reintroduce white rows');

const dash = read('resources/views/admin/dashboard.blade.php');
must(!dash.includes("['name'=>'Signals','color'=>'#20c7ef','render'=>'bar'"), 'Pulse Intelligence primary series is no longer rendered as bars');
must(dash.includes("'fill'=>true"), 'Pulse Intelligence primary series has an area/line treatment');
must(dash.includes('(int) ceil(now()->diffInDays'), 'plan expiry days are rounded to whole days');
must((dash.match(/Strategy Performance/g) || []).length === 1, 'Strategy Performance remains a single dashboard module');

const analytics = read('public/assets/js/admin-analytics-v1505.js');
must(analytics.includes('const labelStep = Math.max(1, Math.ceil(labels.length / 6));'), 'bar charts thin long x-axis label sets');

const adminViews = [];
const walk = dir => {
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, item.name);
    if (item.isDirectory()) walk(full);
    else if (item.name.endsWith('.blade.php')) adminViews.push(full);
  }
};
walk(path.join(root, 'resources/views/admin'));
for (const full of adminViews) {
  if (full.endsWith(path.join('admin','layout.blade.php')) || full.includes(`${path.sep}partials${path.sep}`)) continue;
  const rel = path.relative(root, full);
  const src = fs.readFileSync(full, 'utf8');
  must(src.includes("@extends('admin.layout')"), `${rel} uses shared premium Admin shell`);
}

// Rewarded ads from V15.0.8 must remain intact.
for (const rel of [
  'public/assets/js/pulse-rewarded-ads-v1508.js',
  'app/Services/PulseRewardedAdService.php',
  'app/Http/Controllers/Pulse/RewardedAdController.php',
  'database/migrations/2026_09_06_000800_add_abs_v15_0_8_rewarded_ads.php',
]) must(exists(rel), `rewarded-ad dependency preserved: ${rel}`);

if (failures.length) {
  console.error(`V15.0.9 Admin consistency audit failed (${failures.length}):`);
  failures.forEach(x => console.error(`- ${x}`));
  process.exit(1);
}
console.log(`V15.0.9 Admin consistency audit passed across ${adminViews.length} Admin Blade views.`);
