import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const must=(ok,msg)=>{if(!ok){console.error('FAIL:',msg);process.exit(1);}};

const version=read('VERSION.txt').trim();
const build=read('BUILD_VERSION.txt');
const readme=read('README.md');
const changelog=read('CHANGELOG.md');
const migration=read('database/migrations/2026_09_27_000100_add_private_investor_automatic_settlements.php');
const settlement=read('app/Services/InvestorSettlementService.php');
const accounting=read('app/Services/InvestorPortfolioAccountingService.php');
const performance=read('app/Services/InvestorPerformanceService.php');
const admin=read('app/Http/Controllers/Admin/AdminPortfolioController.php');
const web=read('app/Http/Controllers/PrivatePortalController.php');
const api=read('app/Http/Controllers/Api/V1/PrivatePortalController.php');
const schema=read('app/Support/AbsSchemaRepair.php');
const consoleRoutes=read('routes/console.php');
const overview=read('resources/views/admin/private-investors/overview.blade.php');
const reports=read('resources/views/admin/private-investors/reports.blade.php');
const txView=read('resources/views/private/transactions.blade.php');
const statementView=read('resources/views/private/statements.blade.php');
const setupView=read('resources/views/admin/private-investors/investment-setup.blade.php');
const app=read('app/Http/Controllers/Api/V1/AppController.php');
const openapi=read('docs/openapi.yaml');

must(version==='15.7.4','VERSION is not 15.7.4');
must(build.includes('V15.7.4'),'BUILD_VERSION is not V15.7.4');
must(app.includes("'build' => '15.7.4'"),'API/mobile build identity not updated');
must(openapi.includes('version: 15.7.4'),'OpenAPI version not updated');
must(readme.startsWith('## ABS V15.7.4'),'README does not start with V15.7.4 release notes');
must(changelog.startsWith('# ABS V15.7.4'),'CHANGELOG does not start with V15.7.4');

must(migration.includes("'auto_payout'") && migration.includes("'payout_day'"),'agreement payout fields missing');
must(migration.includes("'entry_source'") && migration.includes("'performance_month'"),'transaction settlement metadata missing');
must(migration.includes("'profit_paid'") && migration.includes("'payment_date'") && migration.includes("'auto_generated'"),'statement payout metadata missing');
must(migration.includes("where('type', 'profit')->update") && migration.includes("'current_value_effect' => 0"),'historical paid-profit capital correction missing');

must(settlement.includes('class InvestorSettlementService'),'settlement service missing');
must(settlement.includes('lockForUpdate()'),'settlement is not concurrency locked');
must(settlement.includes("where('type','profit')") && settlement.includes('performance_month'),'idempotent performance-month payout lookup missing');
must(settlement.includes("'entry_source'=>'automatic_monthly_payout'"),'automatic payout source marker missing');
must(settlement.includes('updateOrCreate') && settlement.includes("'statement_month'"),'automatic statement reconciliation missing');
must(settlement.includes("'profit_paid'=>$profitPaid") && settlement.includes("'payment_date'=>$paymentDate"),'statement paid-profit/payment fields missing');

must(accounting.includes("'profit' => ['current_value_effect' => 0"),'profit payout still compounds into capital');
must(accounting.includes("'withdrawal' => ['current_value_effect' => -$amount, 'net_contributions_effect' => -$amount"),'capital withdrawal accounting missing');
must(accounting.includes('$principalBasis - $settlementUsd'),'V15.7.2 Admin FX gain/loss invariant regressed');

must(consoleRoutes.includes('InvestorSettlementService') && consoleRoutes.includes('$settlements->processAll(now(), true)'),'scheduled monthly settlement hook missing');
must(admin.includes('$settlements->processAll(now(), false)'),'Admin consolidated reporting does not reconcile due settlements');
must(web.includes('$settlements->processAccount($account, now(), false)'),'Investor web views do not reconcile due settlements');
must(api.includes('$settlements->processAccount($account, now(), false)'),'Investor mobile API does not reconcile due settlements');

must(api.includes("'capital_withdrawal'") && api.includes("'profit_paid'"),'mobile/API does not separate principal withdrawals and profit paid');
must(overview.includes('Total Investor Funds') && overview.includes("$summary['net_contributions']"),'Admin total investor funds USD KPI missing');
must(overview.includes('investor principal') && overview.includes('usd_principal'),'Admin local-currency plus USD allocation display missing');
must(reports.includes('net_contributions_usd') && reports.includes('$a->currency'),'Admin investor/local + consolidated USD reporting missing');
must(txView.includes('Profit Paid') && txView.includes('Capital Withdrawal'),'Investor transaction labels do not separate payout from principal withdrawal');
must(statementView.includes('Profit Paid') || statementView.includes('PROFIT PAID'),'Investor statements do not expose profit paid');
must(setupView.includes('Profit payout timing') && setupView.includes('Month end'),'Admin payout timing control missing');

for (const token of ['auto_payout','payout_day','entry_source','performance_month','profit_paid','profit_paid_usd','payment_date','auto_generated']) {
  must(schema.includes(token),`Schema Repair missing ${token}`);
}

must(performance.includes('syncAgreementMonth'),'performance month synchronization missing');

console.log('PASS: ABS V15.7.4 automatic profit payout, statements, withdrawal separation, Admin USD consolidation, mobile/API parity');
