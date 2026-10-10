import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const must = (ok,msg) => { if (!ok) throw new Error(msg); };

const version = read('VERSION.txt').trim();
const build = read('BUILD_VERSION.txt');
const readme = read('README.md');
const changelog = read('CHANGELOG.md');
const service = read('app/Services/PulseAnalyticsBackfillService.php');
const learning = read('app/Services/PulseLearningService.php');
const workflow = read('app/Http/Controllers/Admin/AdminStrategyWorkflowController.php');
const web = read('routes/web.php');
const consoleRoutes = read('routes/console.php');
const view = read('resources/views/admin/pulse/workflow/strategy-performance.blade.php');
const app = read('app/Http/Controllers/Api/V1/AppController.php');
const openapi = read('docs/openapi.yaml');
const noDb = read('ABS_V15_8_4_NO_DATABASE_CHANGES.txt');

must(version === '15.8.4','VERSION is not 15.8.4');
must(build.includes('V15.8.4'),'BUILD_VERSION is not V15.8.4');
must(readme.startsWith('## ABS V15.8.4'),'README does not start with V15.8.4');
must(changelog.startsWith('# ABS V15.8.4'),'CHANGELOG does not start with V15.8.4');
must(app.includes("'build' => '15.8.4'"),'API/mobile build identity is not 15.8.4');
must(openapi.includes('version: 15.8.4'),'OpenAPI version is not 15.8.4');

must(service.includes("SETTING_KEY = 'pulse_strategy_analytics_backfill_job'"),'backfill state key missing');
must(service.includes('in_array($days, [7, 30, 90], true)'),'7/30/90-day backfill choices missing');
must(service.includes('processPendingChunk(int $maxDates = 3, int $maxSeconds = 20)'),'bounded shared-hosting chunk processor missing');
must(service.includes('rebuildDate((string) $date, false)'),'backfill does not defer expensive learning refresh');
must(service.includes('$this->learning->refreshLearningStates();'),'backfill does not refresh learning at completion');
must(service.includes("$state['status'] = 'cancelled'"),'safe backfill cancellation missing');

must(learning.includes('rebuildDate(Carbon|string $date, bool $refreshStates = true)'),'learning rebuild refresh deferral signature missing');
must(learning.includes('if ($refreshStates)'),'learning refresh deferral guard missing');

must(web.includes("'/strategy-performance/backfill'"),'Admin backfill POST route missing');
must(web.includes("'/strategy-performance/backfill/cancel'"),'Admin backfill cancel route missing');
must(web.includes("'/strategy-performance/backfill/status'"),'Admin backfill status route missing');
must(workflow.includes('queueStrategyAnalyticsBackfill'),'Admin backfill controller action missing');
must(workflow.includes('strategyAnalyticsBackfillStatus'),'Admin backfill status action missing');
must(view.includes('Queue Analytics Backfill'),'Admin backfill button missing');
must(view.includes('No SSH or cron changes required'),'shared-hosting guidance missing from UI');
must(view.includes('analytics-backfill-days'),'history window control missing');

must(consoleRoutes.includes("Artisan::command('abs:pulse-maintenance', function (PulseAnalyticsBackfillService $backfill)"),'existing maintenance command is not wired to queued backfill');
must(consoleRoutes.includes('$backfill->processPendingChunk(3, 20)'),'maintenance command does not use bounded backfill work');
must(consoleRoutes.includes("Schedule::command('abs:pulse-maintenance')->everyFiveMinutes()"),'existing maintenance schedule cadence changed or missing');
must(consoleRoutes.includes('$learning->rebuildDate((string) $date, false);'),'legacy CLI backfill not optimized');
must(consoleRoutes.includes('$learning->refreshLearningStates();'),'legacy CLI backfill does not refresh once at end');

must(noDb.includes('NO DATABASE SCHEMA CHANGES'),'no-database-change declaration missing');
must(!fs.readdirSync(path.join(root,'database/migrations')).some(f => /1584/i.test(f)),'V15.8.4 unexpectedly adds a database migration');

// API compatibility protection: V15.8.4 adds no API route edits and keeps the v1 contract file present.
must(fs.existsSync(path.join(root,'routes/api.php')),'mobile API routes file missing');
must(app.includes("'mobile_api_compatible' => true"),'mobile API compatibility health marker missing');

console.log('PASS: ABS V15.8.4 Admin analytics backfill and shared-hosting maintenance contract');
