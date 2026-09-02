import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const exists = file => fs.existsSync(path.join(root, file));
const checks = [];
const check = (name, ok) => checks.push({name, ok: !!ok});

const build = read('BUILD_VERSION.txt');
const routes = read('routes/api.php');
const controller = read('app/Http/Controllers/RecoveryController.php');
const keySupport = read('app/Support/RecoveryKey.php');
const setup = read('resources/views/errors/setup-required.blade.php');
const recovery = read('resources/views/errors/recovery.blade.php');
const middleware = read('app/Http/Middleware/EnsureApplicationInstalled.php');
const consoleRoutes = read('routes/console.php');
const appController = read('app/Http/Controllers/Api/V1/AppController.php');
const openapi = read('docs/openapi.yaml');
const readme = read('README.md');

const repairMethod = controller.slice(controller.indexOf('public function repair'), controller.indexOf('public function initialize'));

check('Build identity is V14.8.22 self-repair release', build.includes('ABS V14.8.22') && build.includes('Database Self-Repair'));
check('Recovery repair POST route exists', routes.includes("Route::post('/recovery/repair'") && routes.includes("[RecoveryController::class, 'repair']"));
check('Recovery repair route is rate limited', routes.includes("Route::post('/recovery/repair', [RecoveryController::class, 'repair'])->middleware('throttle:5,1')"));
check('Browser repair validates ABS recovery key', repairMethod.includes('$this->assertRecoveryKey($request)'));
check('Browser repair runs normal non-destructive abs:repair', repairMethod.includes("Artisan::call('abs:repair')"));
check('Browser repair does not seed or fresh-wipe', !repairMethod.includes("'--seed'") && !repairMethod.includes("'--fresh'"));
check('Browser repair verifies schema again after repair', repairMethod.includes('$after = AbsSchemaRepair::diagnose()') && repairMethod.includes("$after['ready']"));
check('Recovery key uses constant-time comparison', controller.includes('hash_equals($expected, $provided)'));
check('Recovery key supports direct private .env fallback', keySupport.includes("base_path('.env')") && keySupport.includes("ABS_RECOVERY_KEY=") && keySupport.includes("config('app.recovery_key')"));
check('Recovery key is not injected into setup view', !setup.includes('value="{{') && !setup.includes('RecoveryKey::value'));
check('Setup page exposes one protected fix action', setup.includes('Fix Missing Tables & Columns') && setup.includes('action="/api/recovery/repair"'));
check('Setup page explains non-destructive behavior', setup.includes('NON-DESTRUCTIVE REPAIR') && setup.includes('does not drop tables'));
check('Setup page no longer instructs Terminal repair commands', !setup.includes('php artisan abs:repair') && !setup.includes('VS Code terminal') && !setup.includes('cleanup-legacy-migrations.php'));
check('Detailed missing tables/columns remain available', setup.includes('View detected missing database items') && setup.includes("$diagnosis['missing_tables']") && setup.includes("$diagnosis['missing_columns']"));
check('Advanced recovery page also offers repair first', recovery.includes('Recommended — Fix missing database structure') && recovery.includes('action="/api/recovery/repair"'));
check('Installation middleware exposes repair availability without command text', middleware.includes("'repair_available' => RecoveryKey::enabled()") && !middleware.includes("'repair_command'"));
check('HostGator shared scheduler profile is preserved', consoleRoutes.includes("Schedule::command('abs:pulse-market-data')->everyFifteenMinutes()") && consoleRoutes.includes("Schedule::command('abs:pulse-validate-signals')->everyFifteenMinutes()"));
check('Mobile bootstrap build is 14.8.22', appController.includes("'build' => '14.8.22'"));
check('OpenAPI identifies V14.8.22', openapi.includes('version: 14.8.22') && openapi.includes('V14.8.22'));
check('Complete phpMyAdmin repair SQL fallback is packaged', exists('database/ABS_V14_8_22_COMPLETE_DATABASE_SCHEMA_CREATE_REPAIR.sql'));
check('V14.8.22 recovery guide is packaged', exists('docs/ABS_V14_8_22_DATABASE_SELF_REPAIR.md'));
check('README keeps cumulative revision history', readme.includes('Cumulative ABS Revision History') && readme.includes('ABS V14.8.22') && readme.includes('ABS V14.8.21') && readme.includes('ABS V14.8.20') && readme.includes('ABS V14.8.17'));

const failed = checks.filter(c => !c.ok);
for (const c of checks) console.log(`${c.ok ? 'PASS' : 'FAIL'}  ${c.name}`);
console.log(`\nABS V14.8.22 database self-repair contract: ${checks.length - failed.length}/${checks.length} checks passed.`);
if (failed.length) process.exit(1);
