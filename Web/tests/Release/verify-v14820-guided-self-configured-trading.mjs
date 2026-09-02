import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const checks = [];
const check = (name, ok) => checks.push({name, ok: !!ok});

const trade = read('app/Services/PulseTradeService.php');
const pageData = read('app/Services/PulsePageDataService.php');
const signals = read('resources/views/pulse/signals/index.blade.php');
const settings = read('resources/views/pulse/settings.blade.php');
const binanceController = read('app/Http/Controllers/Pulse/BinanceController.php');
const binanceView = read('resources/views/pulse/binance.blade.php');
const signalController = read('app/Http/Controllers/Pulse/SignalController.php');
const api = read('app/Http/Controllers/Api/V1/PulseController.php');
const appController = read('app/Http/Controllers/Api/V1/AppController.php');
const openapi = read('docs/openapi.yaml');
const css = read('public/assets/css/pulse-premium.css');

check('Explicit trade auto-enables managed manual setup for signal-only account', trade.includes("if (! $automatic && $executionMode === 'signal_only')") && trade.includes("'execution_mode' => 'manual'") && trade.includes("trade.managed_setup_enabled"));
check('Automatic mode is not overwritten by manual signal trade', trade.includes('automatic mode is left untouched') && !trade.includes("if (! $automatic && $executionMode !== 'manual')"));
check('Signal readiness no longer requires manual Settings mode', !pageData.includes("&& strtolower((string) ($settings->execution_mode ?? 'signal_only')) === 'manual'") && pageData.includes('$managedSetup = $executionReady'));
check('Legacy Enable Trading redirect removed from Open Trade JS', !signals.includes("setupTarget === 'settings' ? 'Enable Trading'") && !signals.includes('>Enable Trading<'));
check('Managed setup message is present in drawer', signals.includes('data-managed-setup-note') && signals.includes('No Settings step is required'));
check('Managed setup is passed from action to drawer', signals.includes('data-managed-setup=') && signals.includes("trigger.dataset.managedSetup === '1'"));
check('Directed setup states exist', pageData.includes("$setupTarget = 'plans'") && pageData.includes("$setupTarget = 'binance'") && pageData.includes("$setupTarget = 'risk'") && pageData.includes("$setupTarget = 'environment'") && pageData.includes("$setupTarget = 'unavailable'"));
check('Open Trade labels directed next step clearly', signals.includes('Connect Binance') && signals.includes('Review Trading Pause') && signals.includes('Choose Trading Environment') && signals.includes('Trading Temporarily Unavailable'));
check('Binance account snapshot refreshes automatically when stale', trade.includes('refreshExecutionSnapshot') && trade.includes('now()->subMinutes(5)') && trade.includes("'available_balance'"));
check('Successful trade returns to Signals instead of trade-detail page', signalController.includes("redirect()->route('pulse.signals.index', ['selected' => $signal->id])") && !signalController.includes("return redirect()->route('pulse.trades.show', $trade)"));
check('Pulse Settings renamed to Preferences and explains managed execution', settings.includes("@section('heading','Pulse Preferences')") && settings.includes('ABS manages the normal signal-trading defaults for you'));
check('Advanced execution preferences are collapsed by default', settings.includes('<details class="pulse-managed-advanced full">') && settings.includes('Advanced execution preferences'));
check('Mobile readiness supports managed setup', api.includes("'managed_setup' =>") && api.includes("'next_step' =>") && !api.includes("'ready' => $manualAllowed && $executionSystemEnabled && $environmentAllowed && $connectionReady && $settings->execution_mode === 'manual'"));
check('Mobile API build metadata updated', appController.includes("'build' => '14.8.20'"));
check('OpenAPI metadata updated', openapi.includes('version: 14.8.20') && openapi.includes('V14.8.20'));
check('Managed setup styling packaged', css.includes('.pp-managed-setup-note') && css.includes('.pulse-managed-advanced'));
check('First Binance connection is saved and verified in one flow', binanceController.includes('binance.connection_auto_verified') && binanceController.includes('$binance->testConnection($connection)') && binanceView.includes('Save & Verify Connection'));
check('First verified Binance connection can activate automatically', binanceController.includes('Pulse selected it automatically') && binanceView.includes('First verified connection becomes active'));

// Architecture/regression preservation.
const market = read('app/Services/PulseMarketDataService.php');
const scanner = read('app/Services/PulseScannerService.php');
const validation = read('app/Services/PulseSignalValidationService.php');
const learning = read('app/Services/PulseLearningService.php');
const consoleRoutes = read('routes/console.php');
check('Central price architecture preserved', market.includes('syncCentral()') && scanner.includes('$this->market->latestPrices'));
check('Immutable validation safeguards preserved', validation.includes('Entry candle is intentionally excluded from TP/SL validation') && validation.includes("'same_minute_tp_sl'") && validation.includes("'ambiguous'"));
check('Evidence-protected learning preserved', learning.includes('prior_samples') && learning.includes('daily_decay') && learning.includes('context_minimum_samples'));
check('Central scheduler preserved', consoleRoutes.includes("Schedule::command('abs:pulse-market-data')->everyMinute()") && consoleRoutes.includes("Schedule::command('abs:pulse-validate-signals')->everyMinute()"));

const failed = checks.filter(c => !c.ok);
for (const c of checks) console.log(`${c.ok ? 'PASS' : 'FAIL'}  ${c.name}`);
console.log(`\nABS V14.8.20 guided self-configured trading contract: ${checks.length - failed.length}/${checks.length} checks passed.`);
if (failed.length) process.exit(1);
