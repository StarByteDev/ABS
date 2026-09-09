import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const must = (condition, message) => {
    if (!condition) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
    else console.log(`PASS: ${message}`);
};

const page = read('resources/views/pulse/points/index.blade.php');
const layout = read('resources/views/pulse/layout.blade.php');
const css = read('public/assets/css/pulse-premium.css');
const admin = read('resources/views/admin/pulse/points.blade.php');
const intelligence = read('resources/views/admin/pulse/intelligence.blade.php');
const adminCss = read('public/assets/css/abs-app.css');

must(page.includes("@section('title','Pulse Sparks')") && page.includes('<h1>Pulse Sparks</h1>'), 'member economy is named Pulse Sparks');
must(page.includes('pp-sparks-nav') && page.includes('#spark-packages') && page.includes('#buy-sparks'), 'long page has compact task navigation');
must(page.includes('pp-wallet-balance-card') && page.includes('Available Sparks'), 'premium wallet balance is prominent');
must(page.includes('pp-plan-wallet-grid') && page.includes('Activate Pulse with Sparks'), 'activation packages appear before top-up flow');
must(page.includes('No daily scan or signal limits') && page.includes('Zero Sparks charged when no signal qualifies'), 'no-quota and no-result charging rules are clear');
must(page.includes('pp-flow-step') && page.includes('pp-payment-panel'), 'USDT purchase uses a guided two-step flow');
must(page.includes('Spark activity') && page.includes('gift or unlock'), 'wallet ledger includes Admin gifts in its explanation');

for (const selector of ['.pp-sparks-page .pp-wallet-hero', '.pp-sparks-nav', '.pp-sparks-page .pp-plan-wallet-grid', '.pp-plan-badge', '.pp-flow-step', '.pp-package-footnote']) {
    must(css.includes(selector), `premium Spark stylesheet contains ${selector}`);
}
must(css.includes('grid-template-columns:repeat(4,minmax(0,1fr))'), 'desktop package comparison uses four aligned columns');
must(css.includes('@media(max-width:1380px)') && css.includes('@media(max-width:640px)'), 'package layout has tablet and mobile breakpoints');
must(layout.includes("'label' => 'Pulse Sparks'") && layout.includes("number_format($pulsePointsBalance).' Sparks'"), 'Pulse shell consistently displays Sparks');

must(admin.includes('Gift Sparks to a registered user') && admin.includes('spark-user-emails'), 'Admin gift flow supports registered-user lookup');
must(admin.includes('USDT verification queue') && admin.includes('Pulse Sparks bundles'), 'Admin commerce tasks are clearly grouped');
must(adminCss.includes('.admin-spark-gift') && adminCss.includes('.admin-inline-review'), 'Admin Spark workflow has dedicated responsive styling');
must(intelligence.includes('strategy-health-banner') && intelligence.includes('strategy-results-table') && intelligence.includes('strategy-score-explainer'), 'strategy intelligence has health, results and formula surfaces');
must(adminCss.includes('.strategy-results-table') && adminCss.includes('.strategy-impact.positive') && adminCss.includes('.strategy-impact.negative'), 'confidence impact is visually scannable');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.3 Pulse Sparks premium UX contract: PASS');
