import fs from 'node:fs';

const read = (file) => fs.readFileSync(file, 'utf8');
const must = (condition, message) => {
  if (!condition) {
    console.error('FAIL:', message);
    process.exitCode = 1;
  } else {
    console.log('PASS:', message);
  }
};

const webAuth = read('app/Http/Controllers/AuthController.php');
const apiAuth = read('app/Http/Controllers/Api/V1/AuthController.php');
const webMembership = read('app/Http/Controllers/Pulse/MembershipController.php');
const apiPulse = read('app/Http/Controllers/Api/V1/PulseController.php');
const mail = read('app/Services/BrandedMailService.php');
const admin = read('app/Http/Controllers/Admin/AdminEnterpriseController.php');
const adminView = read('resources/views/admin/enterprise/emails.blade.php');
const bootstrap = read('app/Http/Controllers/Api/V1/AppController.php');
const apiRoutes = read('routes/api.php');
const sql = read('database/ABS_V14_9_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql');
const migration = read('database/migrations/2026_08_30_000860_add_admin_event_notification_settings.php');
const recovery = read('app/Http/Controllers/RecoveryController.php');
const pulseConfig = read('config/pulse.php');
const consoleRoutes = read('routes/console.php');

must(webAuth.includes("adminNewRegistration($user, 'web')"), 'website registration sends administrator event alert');
must(apiAuth.includes("adminNewRegistration($user, 'mobile_api')"), 'mobile registration sends administrator event alert');
must(webMembership.includes("adminNewSubscription($membershipRequest, 'web')"), 'website package subscription sends administrator alert');
must(apiPulse.includes("adminNewSubscription($item, 'mobile_api')"), 'mobile package subscription sends administrator alert');
must(mail.includes("'admin_notification_email'"), 'administrator notification recipient is configuration driven');
must(mail.includes("'i@armansabir.com'"), 'default administrator notification recipient is packaged');
must(mail.includes('admin_new_user_registration'), 'registration administrator event is delivery logged');
must(mail.includes('admin_new_package_subscription'), 'subscription administrator event is delivery logged');
must(admin.includes('admin_notify_new_registration') && admin.includes('admin_notify_new_subscription'), 'admin controller exposes independent event switches');
must(adminView.includes('Administrator event notifications') && adminView.includes('admin_notification_email'), 'Admin Email Communications UI exposes recipient and event controls');
must(migration.includes('admin_notification_email') && migration.includes('admin_notify_new_registration') && migration.includes('admin_notify_new_subscription'), 'migration seeds notification defaults without schema changes');
must(apiRoutes.includes("'/recovery/repair'"), 'protected browser recovery repair endpoint remains available');
must(recovery.includes("Artisan::call('abs:repair'"), 'browser recovery still invokes non-destructive abs:repair');
const repairMethod = recovery.slice(recovery.indexOf('public function repair'), recovery.indexOf('public function initialize'));
must(!repairMethod.includes("'--fresh' => true") && !repairMethod.includes("'--seed' => true"), 'browser recovery repair does not fresh-wipe or seed business data');
must(recovery.includes('hash_equals($expected, $provided)'), 'recovery key retains constant-time comparison');
must(pulseConfig.includes("$defaultRefreshSeconds = 60"), 'central ABS market refresh default remains 60 seconds');
must(pulseConfig.includes("'cron_minutes' => max(1, (int) env('PULSE_CRON_MINUTES', 1))"), 'shared-hosting scheduler remains one-minute capable');
must(consoleRoutes.includes('HostGator shared cron cadence is 1 minute'), 'production doctor checks one-minute HostGator cadence');

const requiredMobileRouteFragments = [
  "'/auth/register'", "'/auth/login'", "'/bootstrap'", "'/dashboard'", "'/market/overview'", "'/news'", "'/research'", "'/learning'", "'/economic-calendar'",
  "'/notifications'", "'/devices'", "'/pulse/plans'", "'/pulse/membership'", "prefix('pulse')", "'/strategies'",
  "'/execution/readiness'", "'/positions'", "'/scanner/run'", "'/signals'", "'/trades'", "'/orders'",
  "'/reports'", "'/market-data/prices'", "'/binance/connections'", "prefix('private')", "'/account'"
];
for (const fragment of requiredMobileRouteFragments) must(apiRoutes.includes(fragment), `mobile API route contract fragment present: ${fragment}`);
must(bootstrap.includes("'build' => '14.9.2'"), 'mobile bootstrap identifies V14.9.2');
must(bootstrap.includes("'mobile_api_ready' => true"), 'mobile bootstrap explicitly declares readiness');
must(bootstrap.includes("'market_data_source' => 'ABS central database"), 'mobile bootstrap declares ABS central market source');
must(bootstrap.includes("'market_refresh_seconds'"), 'mobile bootstrap publishes market refresh target');

must(sql.includes('CREATE TABLE IF NOT EXISTS'), 'phpMyAdmin SQL creates missing tables only');
must(sql.includes('information_schema.COLUMNS'), 'phpMyAdmin SQL checks columns before repair');
must(sql.includes("COLUMN_NAME='deleted_at'"), 'phpMyAdmin SQL covers users.deleted_at hotfix');
must(sql.includes('admin_notification_email'), 'phpMyAdmin SQL adds administrator notification defaults when absent');
must(!/\bDROP\s+TABLE\b/i.test(sql), 'phpMyAdmin SQL contains no DROP TABLE');
must(!/\bTRUNCATE\b/i.test(sql), 'phpMyAdmin SQL contains no TRUNCATE');
must(!/\bDELETE\s+FROM\b/i.test(sql), 'phpMyAdmin SQL contains no DELETE FROM');
must(!/\bUPDATE\b/i.test(sql), 'phpMyAdmin SQL contains no UPDATE');
must(!/ALTER\s+TABLE[^;]+\b(MODIFY|CHANGE|DROP)\b/i.test(sql), 'phpMyAdmin SQL only adds missing columns and never modifies/drops existing columns');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V14.9.2 mobile/web backend release contract verified.');
