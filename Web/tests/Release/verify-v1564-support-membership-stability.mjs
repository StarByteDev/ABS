import fs from 'node:fs';
import crypto from 'node:crypto';

const read = (p) => fs.readFileSync(p, 'utf8');
const assert = (cond, msg) => { if (!cond) throw new Error(msg); };

const build = read('BUILD_VERSION.txt');
const web = read('routes/web.php');
const apiRoutes = read('routes/api.php');
const supportService = read('app/Services/PulseSupportService.php');
const supportApi = read('app/Http/Controllers/Api/V1/SupportController.php');
const adminSupport = read('app/Http/Controllers/Admin/AdminSupportController.php');
const userSupport = read('app/Http/Controllers/Pulse/SupportController.php');
const membership = read('resources/views/pulse/membership/index.blade.php');
const migration = read('database/migrations/2026_09_25_000900_add_pulse_support_conversations.php');
const schemaRepair = read('app/Support/AbsSchemaRepair.php');
const pulseLayout = read('resources/views/pulse/layout.blade.php');
const adminLayout = read('resources/views/admin/layout.blade.php');
const appController = read('app/Http/Controllers/Api/V1/AppController.php');
const openapi = read('docs/openapi.yaml');

assert(build.includes('V15.6.4'), 'BUILD_VERSION is not V15.6.4');
assert(membership.includes('\\Illuminate\\Support\\Str::limit'), 'Membership Str::limit namespace is not fixed');
assert(!membership.includes('\\\\Illuminate\\\\Support\\Str::limit'), 'Escaped Membership namespace regression remains');

for (const route of ["name('pulse.support.index')", "name('support.index')", "name('support.show')", "name('support.presence')"]) {
  assert(web.includes(route), `web route missing: ${route}`);
}
for (const route of ["/support'", "/support/messages", "/support/conversations/{conversation}"]) {
  assert(apiRoutes.includes(route), `mobile support API route missing: ${route}`);
}

assert(supportService.includes("'online' => 'Live Support Available'"), 'live support presence missing');
assert(supportService.includes('Pulse Assistant'), 'Pulse Assistant fallback missing');
assert(supportService.includes('touchAdminPresence'), 'Admin support heartbeat missing');
assert(supportService.includes("'payment' => 'Payment & Activation'"), 'support topic catalog missing');
assert(supportService.includes('PulseMembershipRequest::query()'), 'contextual payment support missing');
assert(supportService.includes("'free_signal'"), 'Free Signal support guidance missing');
assert(supportService.includes("'mobile'"), 'mobile support guidance missing');

assert(userSupport.includes("'web'"), 'web support channel missing');
assert(supportApi.includes("'mobile'"), 'mobile support channel missing');
assert(adminSupport.includes('waiting_support') && adminSupport.includes('waiting_customer'), 'Admin support lifecycle missing');
assert(pulseLayout.includes("'route' => 'pulse.support.index'"), 'Pulse Support navigation missing');
assert(adminLayout.includes('Support &amp; Service') && adminLayout.includes('Support Inbox'), 'Admin Support navigation missing');

assert(migration.includes("Schema::create('support_conversations'"), 'support_conversations migration missing');
assert(migration.includes("Schema::create('support_messages'"), 'support_messages migration missing');
assert(!/Schema::drop|->delete\(|->truncate\(|\bDELETE\s+FROM\b|\bUPDATE\s+[`A-Za-z0-9_]+\s+SET\b/i.test(migration.split('public function down')[0]), 'support migration contains destructive/data-replacing operation');
assert(schemaRepair.includes("'support_conversations' =>") && schemaRepair.includes("'support_messages' =>"), 'Database Fix support schema repair missing');

assert(appController.includes("'build' => '15.6.4'"), 'mobile bootstrap build not updated');
assert(appController.includes("'pulse_support'"), 'mobile bootstrap support module missing');
assert(openapi.includes('version: 15.6.4'), 'OpenAPI version not updated');
assert(openapi.includes('/support/messages:'), 'OpenAPI support message route missing');

const releaseService = read('app/Services/ApplicationReleaseService.php');
assert(releaseService.includes("'database_preserved_on_restore' => true"), 'database-preserving rollback contract missing');
assert(releaseService.includes('enforceSingleRestorePoint'), 'single previous-build rollback retention missing');
assert(!releaseService.includes("runArtisanOrFail('db:seed'"), 'production patch flow must not seed live database');

const scannerHash = crypto.createHash('sha256').update(fs.readFileSync('app/Services/PulseScannerService.php')).digest('hex');
assert(scannerHash === '81251d2dd99829127e251c5b217b4d499773ec9f4f4f1de58183cb40012cb5ad', 'PulseScannerService changed unexpectedly');
const masterLogoHash = crypto.createHash('sha256').update(fs.readFileSync('public/assets/brand/abs-logo-master.png')).digest('hex');
assert(masterLogoHash === 'f2c53ad570c0be3ddcc3683b5cddbfad3c18dbe2530dab1084b391f424b3cc05', 'ABS master logo changed unexpectedly');

const customerSurface = [read('resources/views/pulse/support/index.blade.php'), membership].join('\n');
assert(!/CEO\s+view|AI Explain|publisher-supplied|inside the platform|generated by AI/i.test(customerSurface), 'internal/development wording leaked into customer-facing support surfaces');

console.log('PASS: ABS V15.6.4 Pulse Support + Membership stability contract');
console.log('PulseScannerService SHA-256:', scannerHash);
console.log('ABS master logo SHA-256:', masterLogoHash);
