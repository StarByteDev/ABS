import fs from 'node:fs';
import crypto from 'node:crypto';

const read = (p) => fs.readFileSync(p, 'utf8');
const assert = (cond, msg) => { if (!cond) throw new Error(msg); };

const build = read('BUILD_VERSION.txt');
const checkout = read('resources/views/pulse/membership/checkout.blade.php');
const account = read('resources/views/pulse/membership/index.blade.php');
const memberController = read('app/Http/Controllers/Pulse/MembershipController.php');
const membership = read('app/Services/PulseMembershipService.php');
const mail = read('app/Services/BrandedMailService.php');
const adminLayout = read('resources/views/admin/layout.blade.php');
const adminEmails = read('resources/views/admin/enterprise/emails.blade.php');
const api = read('app/Http/Controllers/Api/V1/PulseController.php');
const rewardApi = read('app/Http/Controllers/Api/V1/PublicRewardedSignalController.php');
const rewardService = read('app/Services/PulsePublicRewardedSignalService.php');
const rewardJs = read('public/assets/js/pulse-public-signal-v1520.js');
const openapi = read('docs/openapi.yaml');
const noDb = read('ABS_V15_6_3_NO_DATABASE_CHANGES.txt');

assert(build.includes('V15.6.3'), 'BUILD_VERSION is not V15.6.3');
assert(fs.readdirSync('database/migrations').every((f) => !f.includes('1563') && !f.includes('15_6_3')), 'V15.6.3 unexpectedly adds a database migration');
assert(noDb.includes('NO DATABASE CHANGES'), 'No-database-change marker missing');

assert(checkout.includes('PAYMENT SUMMARY'), 'premium checkout payment summary missing');
assert(checkout.includes('data-copy-wallet'), 'wallet copy control missing');
assert(checkout.includes('<b>Transfer</b>') && checkout.includes('Keep the TXID') && checkout.includes('Submit for verification'), 'payment steps missing');
assert(checkout.includes('membership-plan-includes') && checkout.includes('$planHighlights'), 'package benefits missing from checkout');
assert(account.includes('Payment verification') || account.includes('verification'), 'membership status presentation missing');

assert(memberController.includes("notifyAdministrators($membershipRequest, 'web')"), 'web payment Admin alert missing');
assert(memberController.includes('adminNewSubscription') && memberController.includes('planRequestReceived'), 'payment email calls missing');
assert(membership.includes('function planHighlights') && membership.includes('function requestStatusPayload'), 'shared web/mobile membership presentation missing');
assert(membership.includes('function notifyAdministrators'), 'persistent Admin payment alert missing');
assert(api.includes("notifyAdministrators($item, 'mobile_api')"), 'mobile payment Admin alert missing');
assert(api.includes('requestStatusPayload') && api.includes('benefits'), 'mobile membership status/benefits parity missing');

assert(mail.includes("'status' => 'not_delivered'"), 'non-delivery mail status missing');
assert(mail.includes('function deliveryHealth'), 'mail delivery health missing');
assert(mail.includes("return 'smtp'") && mail.includes("return 'sendmail'"), 'production SMTP/sendmail selection missing');
assert(adminEmails.includes('Email delivery status') && adminEmails.includes('SETUP REQUIRED'), 'Admin mail health UI missing');
assert(adminLayout.includes('$pendingPaymentCount') && adminLayout.includes('abs-nav-count'), 'pending payment Admin badge missing');

assert(rewardService.includes("$weights = ['15m' => 0.35, '4h' => 0.65]"), 'BTC 4H outlook weighting missing');
for (const field of ['outlook_horizon_hours','watch_zone_low','watch_zone_high','expected_range_low','expected_range_high']) {
  assert(rewardService.includes(`'${field}'`), `BTC outlook field missing: ${field}`);
}
assert(rewardService.includes("'presentation' => 'btc_outlook'"), 'BTC outlook presentation missing');
assert(rewardService.includes("'is_qualified_signal' => false"), 'BTC outlook qualification guard missing');
assert(rewardApi.includes("'presentation_schema_version' => 3"), 'mobile presentation schema v3 missing');
assert(rewardApi.includes("'btc_4h_outlook'"), 'mobile BTC 4H fallback mode missing');
assert(rewardJs.includes('BTC 4-HOUR OUTLOOK') && rewardJs.includes('4H EXPECTED RANGE'), 'web BTC outlook presentation missing');

assert(openapi.includes('version: 15.6.3'), 'OpenAPI version not updated');
assert(openapi.includes('BTC 4-Hour Outlook'), 'OpenAPI fallback contract not updated');

const releaseService = read('app/Services/ApplicationReleaseService.php');
assert(releaseService.includes("'database_preserved_on_restore' => true"), 'V15.6.2 database-preserving rollback contract missing');
assert(releaseService.includes('enforceSingleRestorePoint'), 'single previous-build rollback retention missing');
assert(!releaseService.includes("runArtisanOrFail('db:seed'"), 'production patch flow must not seed live database');
assert(releaseService.includes('destructiveMigrationReasons'), 'destructive migration guard missing');

const scannerHash = crypto.createHash('sha256').update(fs.readFileSync('app/Services/PulseScannerService.php')).digest('hex');
assert(scannerHash === '81251d2dd99829127e251c5b217b4d499773ec9f4f4f1de58183cb40012cb5ad', 'PulseScannerService changed unexpectedly');
const masterLogoHash = crypto.createHash('sha256').update(fs.readFileSync('public/assets/brand/abs-logo-master.png')).digest('hex');
assert(masterLogoHash === 'f2c53ad570c0be3ddcc3683b5cddbfad3c18dbe2530dab1084b391f424b3cc05', 'ABS master logo changed unexpectedly');

const customerSurface = [checkout, account, read('resources/views/pulse/public-signal.blade.php'), rewardJs, api].join('\n');
assert(!/CEO\s+view|AI Explain|publisher-supplied|inside the platform/i.test(customerSurface), 'internal/development wording leaked into customer-facing surfaces');

console.log('PASS: ABS V15.6.3 payments + alerts + mobile Free Signal parity contract');
console.log('PulseScannerService SHA-256:', scannerHash);
console.log('ABS master logo SHA-256:', masterLogoHash);
