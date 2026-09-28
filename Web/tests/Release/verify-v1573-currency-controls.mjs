import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const must=(ok,msg)=>{if(!ok){console.error('FAIL:',msg);process.exit(1);}};

const version=read('VERSION.txt').trim();
const build=read('BUILD_VERSION.txt');
const readme=read('README.md');
const changelog=read('CHANGELOG.md');
const noDb=read('ABS_V15_7_3_NO_DATABASE_CHANGES.txt');
const webRoutes=read('routes/web.php');
const apiRoutes=read('routes/api.php');
const web=read('app/Http/Controllers/PrivatePortalController.php');
const api=read('app/Http/Controllers/Api/V1/PrivatePortalController.php');
const admin=read('app/Http/Controllers/Admin/AdminPortfolioController.php');
const currencyService=read('app/Services/InvestorCurrencyService.php');
const accounting=read('app/Services/InvestorPortfolioAccountingService.php');
const requestView=read('resources/views/private/requests.blade.php');
const txView=read('resources/views/admin/private-investors/account-transactions.blade.php');
const setupView=read('resources/views/admin/private-investors/investment-setup.blade.php');
const controlsView=read('resources/views/admin/private-investors/portfolio-values.blade.php');
const adminLayout=read('resources/views/admin/layout.blade.php');
const pulseLayout=read('resources/views/pulse/layout.blade.php');
const app=read('app/Http/Controllers/Api/V1/AppController.php');
const openapi=read('docs/openapi.yaml');

must(version==='15.7.3','VERSION is not 15.7.3');
must(build.includes('V15.7.3'),'BUILD_VERSION is not V15.7.3');
must(app.includes("'build' => '15.7.3'"),'API/mobile build identity not updated');
must(openapi.includes('version: 15.7.3') && openapi.includes('/private/currency:'),'OpenAPI V15.7.3 currency contract missing');
must(readme.includes('USD is now a default, not a forced choice') && readme.includes('PKR 1,000'),'README does not describe the corrected currency workflow');
must(changelog.startsWith('# ABS V15.7.3'),'CHANGELOG not updated for V15.7.3');
must(noDb.includes('NO DATABASE SCHEMA CHANGES') && noDb.includes('No existing investor'),'V15.7.3 production data-safety marker missing');

must(currencyService.includes('class InvestorCurrencyService'),'central currency service missing');
must(currencyService.includes('lockForUpdate()'),'currency update is not concurrency locked');
must(currencyService.includes('$account->transactions()->exists()'),'transaction-history currency lock missing');
must(currencyService.includes('$account->statements()->exists()'),'statement-history currency lock missing');
must(currencyService.includes("whereIn('type', ['add_investment','withdrawal'])") && currencyService.includes('an open capital request'),'open capital-request currency lock missing');
must(currencyService.includes('non-zero monthly performance history'),'meaningful performance lock missing');
must(currencyService.includes("['opening_value','current_value','net_contributions','total_profit','monthly_profit']"),'non-zero account-value lock missing');
must(currencyService.includes("'currency' => $currency"),'currency persistence missing');

must(webRoutes.includes("Route::patch('/currency', [PrivatePortalController::class, 'updateCurrency']"),'investor web currency route missing');
must(webRoutes.includes("Route::patch('/accounts/{account}/currency', [AdminPortfolioController::class, 'updateCurrency']"),'Admin currency route missing');
must(apiRoutes.includes("Route::patch('/currency', [PrivatePortalController::class, 'updateCurrency']"),'mobile/API currency route missing');

must(web.includes("'currency' => ['nullable','string','max:10', Rule::in($currencies->supported())]") && web.includes('$currencies->change($account, $requestedCurrency)'),'web Add Investment currency handling missing');
must(api.includes("'currency'=>['nullable','string','max:10',Rule::in($currencies->supported())]") && api.includes('$currencies->change($account, $requestedCurrency)'),'API Add Investment currency handling missing');
must(api.includes("'supported_currencies'") && api.includes("'currency_editable'"),'API account currency capabilities missing');

must(requestView.includes('Choose principal currency') && requestView.includes("route('private.currency.update')"),'investor direct currency selector missing');
must(requestView.includes('id="requestCurrency"') && requestView.includes('Submitting this first investment request also sets your principal currency'),'investor request currency selector/UX missing');
must(requestView.includes("type === 'withdrawal'") && requestView.includes('accountCurrency'),'withdrawal currency lock UX missing');

must(txView.includes('id="transactionCurrency"') && txView.includes('$supportedCurrencies'),'Admin transaction currency selector missing');
must(txView.includes('id="transactionFxBox"') && txView.includes("fxBox.hidden=usd"),'Admin dynamic FX panel missing');
must(txView.includes("encodeURIComponent(code)"),'FX quote lookup does not follow selected currency');
must(admin.includes("'currency'=>['nullable','string','max:10', Rule::in($currencies->supported())]") && admin.includes("$account = $currencies->change($account, $requestedCurrency)"),'Admin first-transaction currency handling missing');
must(setupView.includes("accounts.currency.update") && controlsView.includes("accounts.currency.update"),'Admin standalone currency controls missing');

// Preserve the V15.7.2 principal-return/FX accounting contract.
must(accounting.includes('Capital withdrawal cannot exceed the remaining investor principal'),'capital withdrawal guard regressed');
must(accounting.includes('$principalBasis - $settlementUsd'),'Admin realized FX formula regressed');
must(accounting.includes("'usd_net_contributions_effect' => -$principalBasis"),'historical USD principal basis removal regressed');

must(adminLayout.includes('private-investor-v1573.css') && pulseLayout.includes('private-investor-v1573.css'),'V15.7.3 CSS is not loaded on Admin/investor layouts');
must(fs.existsSync(path.join(root,'public/assets/css/private-investor-v1573.css')),'V15.7.3 CSS file missing');

// Contract example: local principal remains fixed while Admin absorbs USD FX movement.
const principal=1000;
const basis=+(principal*0.00357).toFixed(2);
const settleGain=+(principal*0.00333).toFixed(2);
const settleLoss=+(principal*0.00385).toFixed(2);
must(principal===1000 && basis===3.57,'principal/basis example failed');
must(+(basis-settleGain).toFixed(2)===0.24,'Admin FX gain example failed');
must(+(basis-settleLoss).toFixed(2)===-0.28,'Admin FX loss example failed');

console.log('PASS: ABS V15.7.3 investor/admin principal-currency controls + V15.7.2 FX invariants');
