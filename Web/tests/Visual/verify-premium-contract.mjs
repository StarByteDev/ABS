import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';

const root = process.cwd();
const previewDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'abs-premium-contract-'));
const generator = path.join(root, 'tests/Visual/generate-premium-previews.mjs');
const cssPath = path.join(root, 'public/assets/css/pulse-premium.css');
const logoPath = path.join(root, 'public/assets/brand/abs-logo-512.png');
const layoutPath = path.join(root, 'resources/views/pulse/layout.blade.php');
const expectedLogoSha256 = 'e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8';

const failures = [];
const checks = [];

function assertContract(condition, description) {
    checks.push(description);
    if (!condition) failures.push(description);
}

function normalizeCss(source) {
    return source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, '');
}

function visibleText(html) {
    return html
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<svg[\s\S]*?<\/svg>/gi, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&ge;/g, '≥')
        .replace(/\s+/g, ' ')
        .trim();
}

function pngDimensions(file) {
    const buffer = fs.readFileSync(file);
    const signature = buffer.subarray(0, 8).toString('hex');
    if (signature !== '89504e470d0a1a0a' || buffer.subarray(12, 16).toString('ascii') !== 'IHDR') {
        return null;
    }
    return {width: buffer.readUInt32BE(16), height: buffer.readUInt32BE(20)};
}

function hexHue(hex) {
    const value = hex.length === 4
        ? hex.slice(1).split('').map(character => character + character).join('')
        : hex.slice(1);
    if (value.length !== 6) return null;
    const [r, g, b] = [0, 2, 4].map(offset => Number.parseInt(value.slice(offset, offset + 2), 16) / 255);
    const maximum = Math.max(r, g, b);
    const minimum = Math.min(r, g, b);
    const delta = maximum - minimum;
    if (delta === 0) return {hue: 0, saturation: 0};
    let hue;
    if (maximum === r) hue = 60 * (((g - b) / delta) % 6);
    else if (maximum === g) hue = 60 * (((b - r) / delta) + 2);
    else hue = 60 * (((r - g) / delta) + 4);
    if (hue < 0) hue += 360;
    const lightness = (maximum + minimum) / 2;
    const saturation = delta / (1 - Math.abs(2 * lightness - 1));
    return {hue, saturation: Number.isFinite(saturation) ? saturation : 0};
}

const generation = spawnSync(process.execPath, [generator, previewDirectory], {
    cwd: root,
    encoding: 'utf8',
});
assertContract(generation.status === 0, 'Preview fixture generator completes successfully');

const css = fs.readFileSync(cssPath, 'utf8');
const compactCss = normalizeCss(css);
const geometryContracts = [
    ['header', '--pulse-premium-header:57px'],
    ['sidebar', '--pulse-premium-sidebar:264px'],
    ['content inset', '.pulse-content{width:100%;max-width:none;margin:0;padding:14px 37px 28px 32px}'],
    ['page heading', '.pp-page-head{min-height:76px'],
    ['summary metric', '.pp-metric{min-height:90px'],
    ['dashboard primary', '.pp-dashboard-main{display:grid;grid-template-columns:1.56fr 1.24fr;gap:10px;height:274px'],
    ['dashboard secondary', '.pp-dashboard-secondary{display:grid;grid-template-columns:.96fr 1.14fr 1.36fr;gap:10px;height:197px'],
    ['dashboard positions', '.pp-positions{height:157px}'],
    ['scanner filters', '.pp-scanner-filters{height:148px'],
    ['scanner results', '.pp-scanner-results{height:323px'],
    ['scanner support', '.pp-scanner-bottom{display:grid;grid-template-columns:1.08fr .99fr 1.07fr;gap:14px;height:175px}'],
    ['signal filters', '.pp-signals-filters{height:98px'],
    ['signal queue', '.pp-signals-queue{height:246px'],
    ['signal review', '.pp-signal-selected{height:254px'],
    ['strategy cards', '.pp-strategy-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;height:279px'],
    ['strategy summary', '.pp-strategy-bottom{display:grid;grid-template-columns:1.77fr .96fr .65fr;gap:10px;height:291px}'],
    ['execution body', '.pp-execution-grid{display:grid;grid-template-columns:1.58fr 1.05fr;gap:10px;height:604px}'],
];
for (const [name, fragment] of geometryContracts) {
    assertContract(compactCss.includes(normalizeCss(fragment)), `Approved 1676×939 ${name} geometry is preserved`);
}

const logoSha256 = crypto.createHash('sha256').update(fs.readFileSync(logoPath)).digest('hex');
assertContract(logoSha256 === expectedLogoSha256, 'Approved ABS logo checksum is unchanged');

const purpleColours = [...new Set(css.match(/#[0-9a-f]{3,6}\b/gi) || [])]
    .filter(colour => {
        const hsl = hexHue(colour);
        return hsl && hsl.saturation > 0.25 && hsl.hue >= 255 && hsl.hue <= 315;
    });
assertContract(purpleColours.length === 0, 'Premium page palette contains no purple colours');

const pageContracts = {
    dashboard: [
        'Pulse Dashboard', 'Your 30-Day Summary', 'Net P&L', '+$1,842.60', 'Executed Trades', '42',
        'Win Rate', '66.7%', 'Signals Actioned', '31 / 48', 'Pulse Account Standing',
        'Trade Outcome Summary', 'Signal Activity', 'Risk & Exposure', 'Open Positions',
    ],
    scanner: [
        'Market Scanner', 'Pairs Monitored', '48', 'Setups Identified', '12', 'High-Conviction',
        'Scan Coverage', '100%', 'Scanner Filters', 'Scanner Results', 'Market Conditions',
        'Scan Quality', 'Saved Scanner View', 'Primary USDT Scan',
    ],
    signals: [
        'Pulse Signals', 'Active Signals', '8', 'High-Conviction', '4', 'Long Setups', '5',
        'Short Setups', '3', 'User Engagement', '64.6%', 'Signal Filters', 'Active Signal Queue',
        'Selected Signal — BTC/USDT', 'Strategy Evidence', 'Risk Snapshot', 'Decision',
    ],
    strategies: [
        'Pulse Strategies', 'Active Strategies', '3', 'Signals Generated', '48', 'Signals Actioned',
        '31', 'Average Setup Score', '81', 'Realized P&L', '+$1,566.46', 'Configured Strategies',
        'Momentum', 'Breakout', 'Reversal', '30-Day Strategy Contribution', 'Global Strategy Controls',
        'Strategy Health',
    ],
    execution: [
        'Trade Execution', 'Pending Tickets', '1', 'Orders Today', '7', 'Fill Rate', '85.7%',
        'Open Positions', '3 / 3', 'Execution Status', 'ON HOLD', 'Position limit reached',
        'Selected Signal', 'Order Ticket', 'Order Calculation', 'Recent Testnet Orders',
        'Pre-Trade Validation', 'Account & Exposure', 'Execution Decision',
    ],
};

const navigation = [
    'Dashboard', 'Market Scanner', 'Signals', 'Strategies', 'Open Positions',
    'Trade History', 'Risk Controls', 'Alerts & Watchlists', 'Reports & P&L',
    'Binance Connection', 'Settings',
];

const layoutSource = fs.readFileSync(layoutPath, 'utf8');
for (const label of navigation) {
    assertContract(layoutSource.includes(`'label' => '${label}'`), `Production sidebar includes approved link: ${label}`);
}
assertContract(layoutSource.includes('data-pulse-sidebar-toggle'), 'Production layout includes the sidebar show/hide control');
assertContract(layoutSource.includes('abs.pulse.sidebar.collapsed.v1'), 'Production layout restores the remembered sidebar state');

const productionContracts = {
    dashboard: ['Pulse Dashboard', 'Your {{ $page[\'period_label\'] }} Summary', 'Performance Overview', 'Pulse Account Standing', 'Trade Outcome Summary', 'Signal Activity', 'Risk &amp; Exposure', 'Open Positions'],
    scanner: ['Market Scanner', 'Scanner Filters', 'Scanner Results', 'Market Conditions', 'Scan Quality', 'Saved Scanner View'],
    signals: ['Pulse Signals', 'Signal Filters', 'Active Signal Queue', 'Strategy Evidence', 'Risk Snapshot', 'Signal Status'],
    strategies: ['Pulse Strategies', 'Strategy Catalog', 'Strategy Performance', 'Strategy Controls &amp; Health'],
};

const productionViews = {
    dashboard: 'resources/views/pulse/dashboard.blade.php',
    scanner: 'resources/views/pulse/scanner.blade.php',
    signals: 'resources/views/pulse/signals/index.blade.php',
    strategies: 'resources/views/pulse/strategies.blade.php',
};

for (const [page, labels] of Object.entries(productionContracts)) {
    const source = fs.readFileSync(path.join(root, productionViews[page]), 'utf8');
    assertContract(source.includes(`class="pp-page pp-${page}`), `${page} Blade template uses its approved production page class`);
    for (const label of labels) assertContract(source.includes(label), `${page} Blade template includes approved section: ${label.replace(/<[^>]+>/g, '')}`);
}

for (const [page, requiredText] of Object.entries(pageContracts)) {
    const file = path.join(previewDirectory, `${page}.html`);
    assertContract(fs.existsSync(file), `${page} self-contained preview is generated`);
    if (!fs.existsSync(file)) continue;
    const html = fs.readFileSync(file, 'utf8');
    const text = visibleText(html);
    assertContract(html.includes('<style>') && html.includes('<script>'), `${page} preview embeds its production styles and behaviour`);
    assertContract(html.includes('data:image/png;base64,'), `${page} preview embeds the approved ABS logo`);
    assertContract(!/\b(?:file|https?):\/\//i.test(html), `${page} preview has no external asset dependency`);
    assertContract(html.includes(`class="pp-page pp-${page}"`), `${page} uses its approved production page class`);
    for (const label of navigation) assertContract(text.includes(label), `${page} includes sidebar link: ${label}`);
    for (const label of requiredText) assertContract(text.includes(label), `${page} includes approved content: ${label}`);
    assertContract(!/\bworkspace\b/i.test(text), `${page} has no customer-visible “workspace” wording`);
    assertContract(!/\baccuracy\b/i.test(text), `${page} uses score terminology rather than accuracy`);
    assertContract(!/\bguaranteed?\s+(?:profit|return)s?\b/i.test(text), `${page} contains no guaranteed-performance claim`);
    assertContract(text.includes('BINANCE TESTNET'), `${page} fixture uses the approved Binance Testnet state`);
    if (page === 'execution') assertContract(html.includes('PULSE-BTC-240826'), 'execution includes the approved client reference');
}

const references = process.argv.slice(2);
if (references.length > 0) {
    assertContract(references.length === 5, 'Exactly five approved reference images were supplied');
    for (const reference of references) {
        assertContract(fs.existsSync(reference), `Approved reference exists: ${path.basename(reference)}`);
        if (!fs.existsSync(reference)) continue;
        const dimensions = pngDimensions(reference);
        assertContract(dimensions?.width === 1676 && dimensions?.height === 939, `${path.basename(reference)} is 1676×939`);
    }
}

fs.rmSync(previewDirectory, {recursive: true, force: true});

if (failures.length > 0) {
    console.error(`Premium visual contract: FAIL (${failures.length} of ${checks.length} checks failed)`);
    for (const failure of failures) console.error(`- ${failure}`);
    process.exit(1);
}

console.log(`Premium visual contract: PASS (${checks.length} checks)`);
console.log('Approved desktop canvas: 1676×939');
console.log('Production pages verified: Dashboard, Market Scanner, Signals, Strategies; legacy execution fixture retained only for regression coverage');
