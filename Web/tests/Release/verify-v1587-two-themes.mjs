import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const base = process.cwd();
const read = file => fs.readFileSync(path.join(base,file),'utf8');
assert.equal(read('VERSION.txt').trim(),'15.8.7-theme-hotfix-candidate');
const css=read('public/assets/css/abs-theme-v1587.css');
const js=read('public/assets/js/abs-theme-v1587.js');
assert.ok(css.includes(':root[data-abs-theme="dark"]'));
assert.ok(css.includes(':root[data-abs-theme="light"]'));
for(const sel of ['.pp-card','.pp-metric','.pp-performance-stats','.pp-standing-list','.pp-table','.pp-donut:before','.pulse-global-header','.pulse-sidebar','.abs-global-disclaimer','.final-site-footer'])
  assert.ok(css.includes(sel),'Missing theme coverage for '+sel);
for(const part of ['localStorage.setItem(key,next)','aria-pressed','Switch to','window.addEventListener(\'storage\''])
  assert.ok(js.includes(part),'Missing theme JS behavior '+part);
for (const file of ['resources/views/admin/layout.blade.php','resources/views/pulse/layout.blade.php','resources/views/layouts/app.blade.php']){
  const page=read(file);
  for (const req of ['abs-theme-v1587.css','abs-theme-v1587.js','data-abs-theme-toggle']) assert.ok(page.includes(req),file+' missing '+req);
  assert.ok(page.indexOf('abs-theme-v1587.css')>page.indexOf("@stack('head')"),file+': theme override must load after page CSS');
  assert.ok(!page.includes('abs-theme-v1580.js'),file+': do not register two competing theme controllers');
}
console.log('ABS V15.8.7 two-theme structural regression: PASS');
