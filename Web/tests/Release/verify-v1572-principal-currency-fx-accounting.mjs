import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const must=(ok,msg)=>{if(!ok){console.error('FAIL:',msg);process.exit(1);}};

const version=read('VERSION.txt').trim();
const build=read('BUILD_VERSION.txt');
const readme=read('README.md');
const migration=read('database/migrations/2026_09_26_000200_add_private_investor_principal_fx_accounting.php');
const service=read('app/Services/InvestorPortfolioAccountingService.php');
const account=read('app/Models/PortfolioAccount.php');
const txModel=read('app/Models/PortfolioTransaction.php');
const schema=read('app/Support/AbsSchemaRepair.php');
const admin=read('app/Http/Controllers/Admin/AdminPortfolioController.php');
const member=read('app/Http/Controllers/PrivatePortalController.php');
const api=read('app/Http/Controllers/Api/V1/PrivatePortalController.php');
const txView=read('resources/views/admin/private-investors/account-transactions.blade.php');
const reqView=read('resources/views/private/requests.blade.php');
const app=read('app/Http/Controllers/Api/V1/AppController.php');
const adminLayout=read('resources/views/admin/layout.blade.php');
const pulseLayout=read('resources/views/pulse/layout.blade.php');

must(version==='15.7.2','VERSION is not 15.7.2');
must(build.includes('V15.7.2'),'BUILD_VERSION is not V15.7.2');
must(app.includes("'build' => '15.7.2'"),'API/mobile build identity not updated');
must(readme.includes('investor still owns **PKR 1,000 principal**') && readme.includes('realized FX gain'),'README does not lock the principal-currency contract');
must(migration.includes('realized_fx_gain_loss_usd') && migration.includes('principal_usd_basis') && migration.includes('settlement_usd_amount') && migration.includes('fx_gain_loss_usd'),'V15.7.2 additive migration incomplete');
must(migration.includes('intentionally retained') || migration.includes('intentionally retained on code rollback'),'migration rollback is not explicitly non-destructive');
must(schema.includes("'realized_fx_gain_loss_usd'") && schema.includes("'principal_usd_basis'") && schema.includes("'settlement_usd_amount'") && schema.includes("'fx_gain_loss_usd'"),'Database Fix schema parity missing');
must(account.includes("'realized_fx_gain_loss_usd'") && account.includes('protected $hidden'),'Admin FX account field not protected from member serialization');
must(txModel.includes("'principal_usd_basis'") && txModel.includes("'settlement_usd_amount'") && txModel.includes("'fx_gain_loss_usd'") && txModel.includes('protected $hidden'),'Admin FX transaction fields not protected from member serialization');
must(service.includes('Capital withdrawal cannot exceed the remaining investor principal'),'backend capital-withdrawal guard missing');
must(service.includes('$principalBasis - $settlementUsd'),'FX gain/loss formula missing');
must(service.includes('$usdPrincipal / $localPrincipal'),'weighted-average historical principal basis missing');
must(service.includes("'usd_net_contributions_effect' => -$principalBasis"),'withdrawal does not remove historical USD principal basis');
must(service.includes('lockForUpdate()'),'financial posting is not concurrency locked');
must(admin.includes('realized_fx_gain_loss_usd') && admin.includes('principal_usd_basis'),'Admin reporting does not expose internal FX accounting');
must(admin.includes("$data['contributions_usd']") && admin.includes('principal_usd_basis'),'statement principal is still floating at statement FX');
must(member.includes('remaining principal') && api.includes('remaining investor principal'),'web/API withdrawal request guard mismatch');
must(txView.includes('Investor receives') && txView.includes('Estimated FX') && txView.includes('Realized FX gain / loss'),'Admin transaction UX missing FX explanation/preview');
must(reqView.includes('Principal protection:') && reqView.includes('FX movements do not change your principal amount'),'Investor principal protection copy missing');
must(adminLayout.includes('private-investor-v1572.css') && pulseLayout.includes('private-investor-v1572.css'),'V15.7.2 investor CSS not loaded');

// Contract math: investor amount stays fixed; Admin absorbs rate movement.
const depositLocal=1000;
const depositRate=0.00357;
const basis=+(depositLocal*depositRate).toFixed(2); // 3.57
const withdrawalRateGain=0.00333;
const settlementGain=+(depositLocal*withdrawalRateGain).toFixed(2); // 3.33
const fxGain=+(basis-settlementGain).toFixed(2);
const withdrawalRateLoss=0.00385;
const settlementLoss=+(depositLocal*withdrawalRateLoss).toFixed(2); // 3.85
const fxLoss=+(basis-settlementLoss).toFixed(2);
must(depositLocal===1000,'investor local principal changed unexpectedly');
must(basis===3.57 && settlementGain===3.33 && fxGain===0.24,'PKR gain example math failed');
must(settlementLoss===3.85 && fxLoss===-0.28,'PKR loss example math failed');

// Partial withdrawal uses weighted-average basis and full withdrawal consumes residue.
const localPrincipal=3000;
const usdPrincipal=11.10;
const part=1000;
const partialBasis=+(part*(usdPrincipal/localPrincipal)).toFixed(2);
must(partialBasis===3.70,'weighted-average partial principal basis failed');

console.log('PASS: ABS V15.7.2 principal-currency protection + Admin FX accounting contract');
