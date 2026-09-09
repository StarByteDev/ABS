import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => {
    if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
    else console.log(`PASS: ${message}`);
};

const points = read('resources/views/pulse/points/index.blade.php');
const css = read('public/assets/css/pulse-premium.css');
const layout = read('resources/views/pulse/layout.blade.php');
const card = read('resources/views/pulse/partials/membership-plan-card.blade.php');
const adminPlan = read('resources/views/admin/pulse/partials/plan-fields.blade.php');
const js = read('public/assets/js/pulse-premium.js');

must(points.includes('ABS PULSE REWARDS'), 'Pulse Points hero uses premium rewards positioning');
must(points.includes('pp-wallet-hero'), 'premium wallet hero component present');
must(points.includes('pp-wallet-balance-card'), 'premium PP balance card present');
must(points.includes('pp-momentum-grid'), 'daily momentum uses premium card grid');
must(points.includes('pp-pack-grid') && points.includes('pp-pack-card'), 'PP packs use selectable premium cards');
must(points.includes('pp-payment-panel'), 'USDT verification flow uses guided premium payment panel');
must(points.includes('Activate Pulse with PP'), 'PP plan activation remains explicit');
must(points.includes('pp-rewards-grid'), 'missions and achievements use premium rewards layout');
must(points.includes('Points activity'), 'immutable wallet history remains available');
must(points.includes('No daily scan or signal quotas'), 'member receives clear no-daily-quota assurance');

for (const selector of ['.pp-wallet-hero', '.pp-wallet-balance-card', '.pp-momentum-grid', '.pp-pack-card', '.pp-payment-panel', '.pp-plan-wallet-card', '.pp-rewards-grid', '.pp-ledger-table']) {
    must(css.includes(selector), `premium stylesheet contains ${selector}`);
}
must(css.includes('@media(max-width:') || css.includes('@media (max-width:'), 'Pulse Points design includes responsive breakpoints');

must(!layout.includes('data-usage-scans'), 'signed-in layout has no scans-left counter');
must(!layout.includes('data-usage-signals'), 'signed-in layout has no signals-left counter');
must(!js.includes('data-usage-scans') && !js.includes('data-usage-signals'), 'browser JS no longer updates legacy quota counters');
must(card.includes('Best Signal searches') && card.includes('No daily quota'), 'plan card communicates unlimited daily searches subject to PP');
must(!card.includes('Daily market scans') && !card.includes('Daily Best Signals'), 'plan cards contain no legacy daily scan/signal allowances');
must(!adminPlan.includes('name="scanner_runs_per_day"') && !adminPlan.includes('name="signals_per_day"'), 'Admin plan editor has no daily scan/signal quota fields');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.2 premium Pulse Points visual/content contract: PASS');
