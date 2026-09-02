import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const checks = [];
const check = (name, ok) => checks.push({name, ok: !!ok});

const layout = read('resources/views/pulse/layout.blade.php');
const strategies = read('resources/views/pulse/strategies.blade.php');
const signals = read('resources/views/pulse/signals/index.blade.php');
const css = read('public/assets/css/pulse-premium.css');
const executionController = read('app/Http/Controllers/Pulse/ExecutionController.php');
const signalController = read('app/Http/Controllers/Pulse/SignalController.php');
const pageData = read('app/Services/PulsePageDataService.php');

check('Standalone Trade Execution navigation removed', !layout.includes("'label' => 'Trade Execution'"));
check('Legacy execution URL redirects to Signals', executionController.includes("redirect()->route('pulse.signals.index'") && executionController.includes("'open_trade'"));
check('Execution failure returns to same Open Trade drawer', signalController.includes("'open_trade' => $signal->id") && !signalController.includes("redirect()->route('pulse.execution'"));
check('Signal page no longer links to standalone execution page', !signals.includes("route('pulse.execution'"));
check('Open Trade drawer uses one confirmation action', signals.includes('Confirm &amp; Open Trade') && !signals.includes('data-trade-advanced') && !signals.includes('data-trade-confirm-check'));
check('Drawer removes Qualified Strategies clutter', !signals.includes('<h3>Qualified Strategies</h3>'));
check('Drawer removes Potential Reward/Risk Reward clutter', !signals.includes('<small>Potential Reward</small>') && !signals.includes('<small>Risk / Reward</small>'));
check('Drawer routes incomplete setup to Plans, Binance or Settings', pageData.includes("$setupTarget = ! $manualAllowed ? 'plans' : (! $connectionReady ? 'binance' : 'settings');"));
check('Strategies use simplified catalog', strategies.includes('pp-strategy-catalog-simplified'));
check('Strategies combine Controls & Health', strategies.includes('Strategy Controls &amp; Health'));
check('Old overlapping strategy-bottom section removed from production view', !strategies.includes('class="pp-strategy-bottom"'));
check('Strategy catalog has auto-height override', css.includes('.pp-strategy-catalog-simplified{height:auto!important'));
check('Strategy insights use responsive normal-flow grid', css.includes('.pp-strategy-insights{display:grid') && css.includes('align-items:start'));
check('Signals summary reduced to four metrics', signals.includes('pp-metrics four pp-metrics-simple'));
check('Signal queue removes R:R column', !signals.includes('<th>R:R</th>'));
check('Execution drawer hidden states are forced hidden', css.includes('.pp-trade-overlay [hidden]{display:none!important}'));


const market = read('app/Services/PulseMarketDataService.php');
const scanner = read('app/Services/PulseScannerService.php');
const validation = read('app/Services/PulseSignalValidationService.php');
const learning = read('app/Services/PulseLearningService.php');
const dashboardData = read('app/Services/PulsePageDataService.php');
const consoleRoutes = read('routes/console.php');
const apiRoutes = read('routes/api.php');
const openapi = read('docs/openapi.yaml');

check('V14.8.18 dashboard runtime repair remains', dashboardData.includes('$scannerCapabilities =') && dashboardData.includes('$scannerConnectionReady ='));
check('Central market-price architecture remains', market.includes('syncCentral()') && market.includes('pulse_market_prices'));
check('Scanner still reads central market data', scanner.includes('$this->market->latestPrices') && scanner.includes('$this->market->scannerRows'));
check('Signal engine learning identity remains 14.8.17', scanner.includes('engine-14.8.17-'));
check('Entry-minute validation exclusion remains', validation.includes('Entry candle is intentionally excluded from TP/SL validation'));
check('Ambiguous same-minute TP+SL handling remains', validation.includes("'same_minute_tp_sl'") && validation.includes("'ambiguous'"));
check('Evidence-protected learning remains', learning.includes('prior_samples') && learning.includes('daily_decay') && learning.includes('context_minimum_samples'));
check('Central market scheduler remains every minute', consoleRoutes.includes("Schedule::command('abs:pulse-market-data')->everyMinute()"));
check('Signal validator remains every minute', consoleRoutes.includes("Schedule::command('abs:pulse-validate-signals')->everyMinute()"));
check('Learning remains daily', consoleRoutes.includes("Schedule::command('abs:pulse-learning')->dailyAt('00:20')"));
for (const route of ['/reports/signals','/reports/strategies','/reports/learning','/market-data/health','/market-data/prices','/signals/{signal}/validation']) {
  check(`Mobile API retained ${route}`, apiRoutes.includes(`'${route}'`) && openapi.includes(`  /pulse${route}:`));
}
check('OpenAPI metadata updated to 14.8.19', openapi.includes('version: 14.8.19'));

const failed = checks.filter(c => !c.ok);
for (const c of checks) console.log(`${c.ok ? 'PASS' : 'FAIL'}  ${c.name}`);
console.log(`\nABS V14.8.19 simplified UX contract: ${checks.length - failed.length}/${checks.length} checks passed.`);
if (failed.length) process.exit(1);
