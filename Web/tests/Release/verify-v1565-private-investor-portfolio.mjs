import fs from 'node:fs';
import crypto from 'node:crypto';

const read = (p) => fs.readFileSync(p, 'utf8');
const assert = (cond, msg) => { if (!cond) throw new Error(msg); };

const build = read('BUILD_VERSION.txt');
const web = read('routes/web.php');
const api = read('routes/api.php');
const user = read('app/Models/User.php');
const adminPortfolio = read('app/Http/Controllers/Admin/AdminPortfolioController.php');
const privatePortal = read('app/Http/Controllers/PrivatePortalController.php');
const privateApi = read('app/Http/Controllers/Api/V1/PrivatePortalController.php');
const adminUsers = read('app/Http/Controllers/Admin/AdminUserController.php');
const support = read('app/Services/PulseSupportService.php');
const migration = read('database/migrations/2026_09_25_001000_add_private_investor_management.php');
const schemaRepair = read('app/Support/AbsSchemaRepair.php');
const pulseLayout = read('resources/views/pulse/layout.blade.php');
const adminLayout = read('resources/views/admin/layout.blade.php');
const overview = read('resources/views/private/index.blade.php');
const requests = read('resources/views/private/requests.blade.php');
const adminOverview = read('resources/views/admin/private-investors/overview.blade.php');
const openapi = read('docs/openapi.yaml');
const appController = read('app/Http/Controllers/Api/V1/AppController.php');
const accountController = read('app/Http/Controllers/Api/V1/AccountController.php');

assert(build.includes('V15.6.5'), 'BUILD_VERSION is not V15.6.5');
assert(user.includes("['private_member', 'private_investor']"), 'legacy/new Private Investor compatibility missing');
assert(user.includes('function portfolioRequests'), 'Private Investor request relation missing');
assert(user.includes('function isPrivateInvestor'), 'Private Investor role helper missing');

assert(web.includes("prefix('private')->name('private.')"), 'Private Investor member route group missing');
for (const route of ["name('index')", "name('transactions')", "name('statements')", "name('requests')", "name('requests.store')"]) {
  assert(web.includes(route), `Private Investor member route missing: ${route}`);
}
assert(web.includes("prefix('private-investors')->name('private-investors.')"), 'Admin Private Investor route group missing');
for (const route of ["name('overview')", "name('investors')", "name('accounts.store')", "name('requests.update')"]) {
  assert(web.includes(route), `Admin Private Investor route missing: ${route}`);
}

for (const path of ["'/account'", "'/transactions'", "'/statements'", "'/requests'"]) {
  assert(api.includes(path), `Private Investor API route missing: ${path}`);
}

assert(overview.includes('Portfolio Overview') && overview.includes('Total Investment') && overview.includes('Portfolio Performance'), 'Investor overview KPIs/chart missing');
assert(requests.includes('Add Investment') && requests.includes('Withdrawal') && requests.includes('Portfolio Review'), 'Investor request workflow missing');
assert(adminOverview.includes('Private Investor Overview') && adminOverview.includes('Portfolio Performance'), 'Admin Private Investor overview missing');
assert(adminLayout.includes('Private Investors') && adminLayout.includes('Investor Accounts') && adminLayout.includes('Requests'), 'Admin Private Investor navigation missing');
assert(pulseLayout.includes('Investor Portfolio'), 'member Investor Portfolio navigation missing');

assert(adminPortfolio.includes('admin.private_investor_transaction_recorded'), 'portfolio transaction audit missing');
assert(adminPortfolio.includes('admin.private_investor_statement_published'), 'statement audit missing');
assert(adminPortfolio.includes('admin.private_investor_request_updated'), 'request audit missing');
assert(privatePortal.includes("Rule::in(['add_investment','withdrawal','portfolio_review'])"), 'web investor request validation missing');
assert(privateApi.includes("Rule::in(['add_investment','withdrawal','portfolio_review'])"), 'mobile investor request validation missing');
assert(privatePortal.includes('requested withdrawal is higher than the latest reported portfolio value'), 'withdrawal guard missing');

assert(adminUsers.includes("'role' => ['required', 'in:user,private_member,private_investor,admin']") || adminUsers.includes("'role' => ['required','in:user,private_member,private_investor,admin']"), 'Admin role assignment does not support private_investor');
assert(adminUsers.includes("'account_name' => 'Private Investor Portfolio'"), 'automatic investor portfolio creation missing');
assert(adminUsers.includes("slug = 'pulse-professional'"), 'Private Investor Pulse Professional default access missing');

assert(support.includes("'portfolio' => 'Investor Portfolio'"), 'Investor portfolio support category missing');
assert(support.includes("$user->isPrivateInvestor()"), 'contextual investor support missing');
assert(web.includes("name('support.start')"), 'Admin proactive support start route missing');

assert(migration.includes("Schema::create('portfolio_requests'"), 'portfolio_requests migration missing');
assert(migration.includes('ALTER TABLE `users` MODIFY `role` VARCHAR(40)'), 'legacy role widening missing');
assert(!/Schema::drop|->truncate\(|->delete\(|\bDELETE\s+FROM\b|\bUPDATE\s+[`A-Za-z0-9_]+\s+SET\b/i.test(migration.split('public function down')[0]), 'Private Investor migration contains destructive/data-replacing operation');
assert(schemaRepair.includes("'portfolio_requests' =>"), 'Database Fix portfolio_requests repair missing');
assert(schemaRepair.includes("'users' => ['role' => \"VARCHAR(40)"), 'Database Fix role widening missing');

assert(appController.includes("'build' => '15.6.5'"), 'mobile bootstrap build not updated');
assert(appController.includes("'private_investor_portfolio'"), 'mobile bootstrap Private Investor module missing');
assert(accountController.includes("'private_investor_enabled'"), 'mobile account Private Investor flag missing');
assert(openapi.includes('version: 15.6.5'), 'OpenAPI version not updated');
assert(openapi.includes('/private/requests:'), 'OpenAPI investor request route missing');

const releaseService = read('app/Services/ApplicationReleaseService.php');
assert(releaseService.includes("'database_preserved_on_restore' => true"), 'database-preserving rollback contract missing');
assert(releaseService.includes('enforceSingleRestorePoint'), 'single previous-build rollback retention missing');
assert(!releaseService.includes("runArtisanOrFail('db:seed'"), 'production patch flow must not seed live database');
assert(releaseService.includes('destructiveMigrationReasons'), 'destructive migration guard missing');

const scannerHash = crypto.createHash('sha256').update(fs.readFileSync('app/Services/PulseScannerService.php')).digest('hex');
assert(scannerHash === '81251d2dd99829127e251c5b217b4d499773ec9f4f4f1de58183cb40012cb5ad', 'PulseScannerService changed unexpectedly');
const masterLogoHash = crypto.createHash('sha256').update(fs.readFileSync('public/assets/brand/abs-logo-master.png')).digest('hex');
assert(masterLogoHash === 'f2c53ad570c0be3ddcc3683b5cddbfad3c18dbe2530dab1084b391f424b3cc05', 'ABS master logo changed unexpectedly');

const customerSurface = [overview, requests, read('resources/views/private/statements.blade.php'), read('resources/views/private/transactions.blade.php'), read('resources/views/private/statement.blade.php')].join('\n');
assert(!/CEO\s+view|AI Explain|publisher-supplied|inside the platform|generated by AI|development instruction/i.test(customerSurface), 'internal/development wording leaked into Private Investor surfaces');

console.log('PASS: ABS V15.6.5 Private Investor portfolio management contract');
console.log('PulseScannerService SHA-256:', scannerHash);
console.log('ABS master logo SHA-256:', masterLogoHash);
