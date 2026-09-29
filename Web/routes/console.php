<?php

use App\Models\EmailDeliveryLog;
use App\Models\PulseSignal;
use App\Models\PulseSystemSetting;
use App\Models\PortfolioPerformancePlan;
use App\Models\PortfolioInvestmentTerm;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\BinanceFuturesService;
use App\Services\BrandedMailService;
use App\Services\EconomicCalendarService;
use App\Services\MarketDataService;
use App\Services\InvestorPerformanceService;
use App\Services\InvestorSettlementService;
use App\Services\PulseAutomationService;
use App\Services\PulseMarketDataService;
use App\Services\PulseRuntimeCadenceService;
use App\Services\PulseSignalValidationService;
use App\Services\PulseStrategyAnalyticsService;
use App\Services\PulseStrategyCycleService;
use App\Services\PulseStrategyResearchService;
use App\Services\PulseLearningService;
use App\Services\PulseTradeService;
use App\Services\Push\PulsePushEventService;
use App\Services\Push\PushNotificationService;
use App\Support\AbsSchemaRepair;
use App\Support\LegacyMigrationBaseline;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('abs:about', function (): void {
    $this->info('Alpha Block Solutions V15.7.4 — Automatic Investor Profit Payouts, Statements & USD Consolidation');
    $this->line('ABS API contract: /api/v1');
    $this->line('Pulse entry: /pulse');
    $this->line('Database repair mode: direct, non-destructive schema reconciliation');
    $this->line('Live trading gate: '.(config('pulse.allow_live_trading') ? 'ENABLED' : 'disabled by default'));
    $this->line('Automatic trading gate: '.(config('pulse.allow_automatic_trading') ? 'ENABLED' : 'disabled by default'));
    $this->line('Scheduler profile: '.(string) config('pulse.scheduler.profile', 'standard').' (cron every '.(int) config('pulse.scheduler.cron_minutes', 1).' minute(s))');
})->purpose('Display ABS and Pulse build information');

Artisan::command('abs:doctor', function (): int {
    $this->info('ABS V15.3.0 environment, MySQL database and Pulse doctor');
    $this->newLine();

    $checks = [
        ['PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>=')],
        ['APP_KEY configured', filled(config('app.key'))],
        ['storage writable', is_writable(storage_path())],
        ['bootstrap/cache writable', is_writable(base_path('bootstrap/cache'))],
        ['ABS frontend CSS available', file_exists(public_path('assets/css/abs-app.css'))],
        ['ABS frontend JavaScript available', file_exists(public_path('assets/js/abs-app.js'))],
        ['Pulse frontend CSS available', file_exists(public_path('assets/css/pulse-app.css'))],
        ['Pulse frontend JavaScript available', file_exists(public_path('assets/js/pulse-app.js'))],
        ['Premium Pulse page CSS available', file_exists(public_path('assets/css/pulse-premium.css'))],
        ['Premium Pulse page JavaScript available', file_exists(public_path('assets/js/pulse-premium.js'))],
        ['ABS brand logo available', file_exists(public_path('assets/brand/abs-logo-512.png'))],
        ['ABS favicon.ico available', file_exists(public_path('favicon.ico'))],
        ['ABS favicon PNG available', file_exists(public_path('favicon-32x32.png'))],
        ['ABS Apple touch icon available', file_exists(public_path('apple-touch-icon.png'))],
        ['OpenAPI contract available', file_exists(base_path('docs/openapi.yaml'))],
    ];

    $databaseDriver = (string) config('database.default');
    $checks[] = ['Runtime database driver is MySQL', $databaseDriver === 'mysql'];

    $schedulerProfile = (string) config('pulse.scheduler.profile', 'standard');
    $schedulerMinutes = (int) config('pulse.scheduler.cron_minutes', 1);
    $knownSchedulerProfile = in_array($schedulerProfile, ['standard','hostgator','hostgator_shared','shared'], true);
    $checks[] = ['Scheduler profile recognized', $knownSchedulerProfile];
    if ((bool) config('pulse.scheduler.hostgator_shared', false)) {
        $checks[] = ['HostGator shared cron cadence is 1 minute', $schedulerMinutes === 1];
        $checks[] = ['HostGator central price target is 60 seconds', (int) config('pulse.market_data.target_price_refresh_seconds', 0) === 60];
        $checks[] = ['HostGator market freshness tolerance covers missed cron cycles', (int) config('pulse.market_data.read_max_age_seconds', 0) >= 120];
    }

    foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'ctype', 'json'] as $extension) {
        $checks[] = ['PHP extension: '.$extension, extension_loaded($extension)];
    }

    foreach ($checks as [$label, $ok]) {
        $this->line(($ok ? '<info>PASS</info>' : '<error>FAIL</error>').'  '.$label);
    }

    $diagnosis = AbsSchemaRepair::diagnose();
    $this->newLine();
    $this->line('Connection: '.($diagnosis['connection'] ?: '(not configured)'));
    $this->line('Database driver: '.((string) config('database.default')));
    $this->line('Database: '.($diagnosis['database'] ?: '(not configured)'));

    if ($diagnosis['error']) {
        $this->error('Database connection failed: '.$diagnosis['error']);
    } elseif ($diagnosis['ready']) {
        $this->info('Database schema: READY');
    } else {
        $this->error('Database schema: INCOMPLETE');
        if ($diagnosis['missing_tables']) {
            $this->line('Missing tables: '.implode(', ', $diagnosis['missing_tables']));
        }
        foreach ($diagnosis['missing_columns'] as $table => $columns) {
            $this->line('Missing columns in '.$table.': '.implode(', ', $columns));
        }
        $this->newLine();
        $this->warn('Run: php artisan abs:repair --seed');
    }

    $failed = collect($checks)->contains(fn (array $row): bool => ! $row[1]) || ! $diagnosis['ready'];

    return $failed ? Command::FAILURE : Command::SUCCESS;
})->purpose('Check PHP, direct frontend assets, database connectivity and the complete ABS + Pulse schema');

Artisan::command('abs:repair {--seed : Insert or refresh the reviewed ABS and Pulse configuration} {--fresh : Drop all tables first; use only for a dedicated empty ABS database} {--force : Confirm destructive fresh installation}', function (): int {
    $database = (string) config('database.connections.'.config('database.default').'.database');
    $fresh = (bool) $this->option('fresh');
    $seed = (bool) $this->option('seed');

    $this->info('ABS V15.3.0 MySQL database repair');
    $this->line('Target database: '.($database ?: '(not configured)'));

    if (config('database.default') !== 'mysql') {
        $this->error('ABS V15.3.0 is a MySQL-first runtime build. Set DB_CONNECTION=mysql in .env and clear configuration cache.');
        return Command::FAILURE;
    }

    if ($fresh && ! $this->option('force')) {
        $this->error('Fresh mode deletes every table. Re-run with --fresh --force only for a dedicated ABS database.');
        return Command::FAILURE;
    }

    if ($fresh && config('database.default') === 'mysql' && strtolower($database) === 'pulse') {
        $this->error('Fresh mode is blocked for the legacy database named "pulse" to prevent accidental data loss.');
        $this->line('Create a new dedicated database named abs and update DB_DATABASE in .env.');
        return Command::FAILURE;
    }

    try {
        $this->call('optimize:clear');

        DB::connection()->getPdo();

        if ($fresh) {
            $this->warn('Fresh mode confirmed. Removing tables from the dedicated database...');
            if ($this->call('db:wipe', ['--force' => true]) !== Command::SUCCESS) {
                $this->error('Database wipe returned an error.');
                return Command::FAILURE;
            }
        }

        $this->info('Reconciling required ABS and Pulse tables and columns directly...');
        AbsSchemaRepair::repair();

        $baseline = LegacyMigrationBaseline::synchronize();
        if ($baseline['baselined'] !== []) {
            $this->info('Baselined '.count($baseline['baselined']).' already-satisfied table-creation migration(s).');
            foreach ($baseline['baselined'] as $migration) {
                $this->line('  - '.$migration);
            }
        } else {
            $this->line('No legacy table-creation migrations required baselining.');
        }

        if ($seed) {
            $this->info('Seeding reviewed ABS content and Pulse configuration...');
            if ($this->call('db:seed', ['--force' => true]) !== Command::SUCCESS) {
                $this->error('Database seeding returned an error.');
                return Command::FAILURE;
            }
        }

        $this->call('optimize:clear');
        $diagnosis = AbsSchemaRepair::diagnose();

        if (! $diagnosis['ready']) {
            $this->error('Repair completed, but the schema doctor still found unresolved items.');
            foreach ($diagnosis['missing_tables'] as $table) {
                $this->line('Missing table: '.$table);
            }
            foreach ($diagnosis['missing_columns'] as $table => $columns) {
                $this->line('Missing columns in '.$table.': '.implode(', ', $columns));
            }
            if ($diagnosis['error']) {
                $this->line('Database error: '.$diagnosis['error']);
            }
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('ABS + Pulse database schema is ready.');
        $this->line('Normal repair did not execute conflicting pending legacy migrations.');
        if (! $seed) {
            $this->line('No baseline content/configuration was inserted. Add --seed when required.');
        }

        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Repair failed: '.$e->getMessage());
        $this->line('Check DB_CONNECTION=mysql and verify DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');
        return Command::FAILURE;
    }
})->purpose('Non-destructively reconcile the ABS + Pulse schema without running conflicting legacy migrations');

Artisan::command('abs:market-test', function (MarketDataService $market): int {
    $this->info('ABS V15.3.0 public website market connectivity test');
    $this->line('Binance hosts: '.implode(', ', (array) config('services.binance.base_urls')));
    $this->line('SSL verification: '.(config('services.market.ssl_verify') ? 'enabled' : 'disabled for local development'));

    try {
        $overview = $market->overview(true);
        $chart = $market->chart('BTCUSDT', '15m', 20, true);
        $movers = $market->movers(5, true);

        $btc = collect($overview['core'] ?? [])->firstWhere('symbol', 'BTCUSDT');
        $btcPrice = $btc['price'] ?? null;
        $this->line('Overview source: '.($overview['source'] ?? 'unknown'));
        $this->line('BTC/USDT: '.(is_numeric($btcPrice) ? '$'.number_format((float) $btcPrice, 2) : 'unavailable'));
        $this->line('Chart source: '.($chart['source'] ?? 'unknown').' — candles: '.count($chart['candles'] ?? []));
        $this->line('Movers source: '.($movers['source'] ?? 'unknown').' — gainers: '.count($movers['gainers'] ?? []).' / losers: '.count($movers['losers'] ?? []));
        $global = (array) ($overview['global'] ?? []);
        $this->line('24H liquidations: longs '.(is_numeric($global['liquidation_long_24h_usd'] ?? null) ? '$'.number_format((float) $global['liquidation_long_24h_usd'], 0) : 'unavailable').' / shorts '.(is_numeric($global['liquidation_short_24h_usd'] ?? null) ? '$'.number_format((float) $global['liquidation_short_24h_usd'], 0) : 'unavailable'));
        $this->line('Futures insights: OI '.(is_numeric($global['open_interest_usd'] ?? null) ? '$'.number_format((float) $global['open_interest_usd'], 0) : 'unavailable').' / funding '.(is_numeric($global['funding_rate'] ?? null) ? number_format((float) $global['funding_rate'], 4).'%' : 'unavailable').' / long-short '.(is_numeric($global['long_short_ratio'] ?? null) ? number_format((float) $global['long_short_ratio'], 3) : 'unavailable').' / perp basis '.(is_numeric($global['perp_premium_basis'] ?? null) ? number_format((float) $global['perp_premium_basis'], 4).'%' : 'unavailable'));
        $this->line('Fear & Greed: '.(is_numeric($global['fear_greed_score'] ?? null) ? (int) $global['fear_greed_score'].' '.($global['fear_greed_label'] ?? '') : 'unavailable'));
        $this->line('Industries: '.count($overview['industries'] ?? []));

        $ready = (bool) ($overview['is_live'] ?? false)
            && (bool) ($chart['is_live'] ?? false)
            && (bool) ($movers['is_live'] ?? false)
            && is_numeric($btcPrice)
            && count($chart['candles'] ?? []) > 1
            && count($movers['gainers'] ?? []) > 0
            && count($movers['losers'] ?? []) > 0
            && is_numeric($global['liquidation_long_24h_usd'] ?? null)
            && is_numeric($global['liquidation_short_24h_usd'] ?? null)
            && is_numeric($global['open_interest_usd'] ?? null)
            && is_numeric($global['funding_rate'] ?? null)
            && is_numeric($global['long_short_ratio'] ?? null)
            && is_numeric($global['perp_premium_basis'] ?? null)
            && is_numeric($global['fear_greed_score'] ?? null)
            && count($overview['industries'] ?? []) >= 4;

        if (! $ready) {
            $this->warn('One or more live market providers are unavailable. No substitute prices were generated.');
            $this->line('For XAMPP, keep MARKET_SSL_VERIFY=false locally or configure curl.cainfo in php.ini.');
            return Command::FAILURE;
        }

        $this->info('LIVE MARKET DATA: READY');
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Market test failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Verify homepage prices, chart, movers, liquidations, futures, sentiment and industry data');

Artisan::command('abs:pulse-test {--environment=testnet : Public Binance Futures endpoint to test: testnet or live}', function (BinanceFuturesService $binance): int {
    $environment = strtolower((string) $this->option('environment'));
    if (! in_array($environment, ['live', 'testnet'], true)) {
        $this->error('Environment must be live or testnet.');
        return Command::INVALID;
    }

    $this->info('ABS V15.0.9 Pulse public Binance Futures connectivity test');
    $this->line('Environment: '.$environment);
    $this->line('Base URL: '.config("pulse.binance.{$environment}_base_url"));
    $this->line('This command does not require or use any user API key.');

    try {
        $ping = $binance->publicPing($environment);
        $time = $binance->serverTime($environment);
        $ticker = $binance->publicTicker('BTCUSDT', $environment);
        $candles = $binance->publicKlines('BTCUSDT', '15m', 20, $environment);

        $this->line('Public ping: '.($ping ? 'reachable' : 'failed'));
        $this->line('Server time: '.$time);
        $this->line('BTC/USDT price: '.($ticker['price'] ?? 'unavailable'));
        $this->line('Candles received: '.count($candles));
        $this->line('Exchange-filter synchronization is tested separately with: php artisan abs:pulse-pairs --environment='.$environment);

        if (! $ping || $time <= 0 || ! is_numeric($ticker['price'] ?? null) || count($candles) < 2) {
            $this->error('PULSE PUBLIC MARKET DATA: INCOMPLETE');
            return Command::FAILURE;
        }

        $this->info('PULSE PUBLIC MARKET DATA: READY');
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Pulse test failed: '.$e->getMessage());
        $this->line('For local XAMPP only, set PULSE_BINANCE_SSL_VERIFY=false if the PHP CA bundle is not configured.');
        return Command::FAILURE;
    }
})->purpose('Verify Binance USD-M Futures public endpoints used by Pulse');

Artisan::command('abs:pulse-pairs {--environment=live : Source exchange environment}', function (BinanceFuturesService $binance): int {
    $environment = strtolower((string) $this->option('environment'));
    if (! in_array($environment, ['live', 'testnet'], true)) {
        $this->error('Environment must be live or testnet.');
        return Command::INVALID;
    }

    try {
        $count = $binance->syncPairsFromExchange($environment);
        $this->info("Pulse exchange filters synchronized for {$count} active USD-M perpetual Futures markets.");
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Pair synchronization failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Synchronize Binance USD-M Futures pair precision and minimum filters');

Artisan::command('abs:test-doctor', function (): int {
    $this->info('ABS V15.0.9 PHPUnit and migration doctor');
    $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
    $userCreators = [];
    $legacySignatures = [];

    foreach ($migrationFiles as $file) {
        $source = (string) file_get_contents($file);
        if (preg_match("/Schema::create\\s*\\(\\s*['\"]users['\"]/", $source)) {
            $userCreators[] = basename($file);
        }
        if (str_contains($source, 'accepted_terms_at') || str_contains($source, "enum('role', ['admin', 'user'])")) {
            $legacySignatures[] = basename($file);
        }
    }

    $this->line('Create-users migrations found: '.count($userCreators));
    foreach ($userCreators as $file) $this->line('  - '.$file);

    if (count($userCreators) > 1 || $legacySignatures !== []) {
        $this->error('STALE MIGRATION FILES DETECTED');
        foreach ($legacySignatures as $file) $this->line('Legacy signature: '.$file);
        $this->line('Run: php cleanup-legacy-migrations.php, then rerun this doctor. A clean V14.7.9 extraction is recommended.');
        return Command::FAILURE;
    }

    $this->info('PHPUNIT MIGRATION SET: READY');
    $this->line('Feature tests use an isolated in-memory database harness; the application runtime remains MySQL-first.');
    return Command::SUCCESS;
})->purpose('Detect stale legacy migrations before running PHPUnit');

Artisan::command('abs:pulse-maintenance', function (): int {
    $expiredSignals = PulseSignal::query()
        ->where('status', 'active')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->update(['status' => 'expired']);

    $expiredAccess = UserServiceAccess::query()
        ->where('service', 'pulse')
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', now())
        ->update(['status' => 'expired']);

    $this->info("Pulse maintenance complete: {$expiredSignals} signals expired; {$expiredAccess} access records expired.");
    return Command::SUCCESS;
})->purpose('Expire stale Pulse signals and ended service access without sending exchange orders');

Artisan::command('abs:pulse-sync', function (PulseTradeService $trades): int {
    $completed = 0;
    $failed = 0;

    // Always reconcile users with live trade records, even if their package expired
    // after entry. Active-access users are included as well for normal account sync.
    $activeAccessUsers = UserServiceAccess::query()->where('service', 'pulse')->where('status', 'active')->pluck('user_id');
    $liveTradeUsers = DB::table('pulse_trades')->whereIn('status', ['pending','open','closing','protection_failed'])->pluck('user_id');
    $userIds = $activeAccessUsers->merge($liveTradeUsers)->filter()->unique()->values();

    User::query()->whereIn('id', $userIds)->chunkById(50, function ($users) use ($trades, &$completed, &$failed): void {
        foreach ($users as $user) {
            try {
                $summary = $trades->sync($user);
                $completed++;
                foreach ((array) ($summary['errors'] ?? []) as $error) {
                    $failed++;
                    $this->warn('User '.$user->id.' trade '.($error['trade_id'] ?? '?').': '.($error['message'] ?? 'Synchronization error'));
                }
            } catch (Throwable $e) {
                $failed++;
                $this->warn('User '.$user->id.': '.$e->getMessage());
            }
        }
    });

    $this->info("Pulse signed synchronization complete: {$completed} users synchronized; {$failed} trade/user error(s).");
    return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Reconcile all live Pulse orders/positions (including trades whose package later expired) and attach exchange-side protection after fills');

Artisan::command('abs:pulse-automation', function (PulseAutomationService $automation): int {
    $result = $automation->runEligibleUsers();
    if ($result['disabled'] ?? false) {
        $this->line('Pulse automation is disabled by the platform-wide controls.');
        return Command::SUCCESS;
    }

    $this->line('Attempted: '.($result['attempted'] ?? 0));
    $this->line('Completed: '.($result['completed'] ?? 0));
    $this->line('Failed: '.($result['failed'] ?? 0));
    foreach ($result['errors'] ?? [] as $error) {
        $this->warn('User '.($error['user_id'] ?? '?').': '.($error['message'] ?? 'Unknown error'));
    }

    return ($result['failed'] ?? 0) > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Run gated Pulse automatic workflows for eligible users');

Artisan::command('abs:email-test {email : Recipient email address for a real outbound delivery test}', function (BrandedMailService $mail): int {
    $email = strtolower(trim((string) $this->argument('email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email address.');
        return Command::INVALID;
    }

    $started = now();
    $mail->testEmail($email);
    $log = EmailDeliveryLog::query()
        ->where('event', 'system_test')
        ->where('recipient_email', $email)
        ->where('created_at', '>=', $started->copy()->subSecond())
        ->latest('id')
        ->first();

    if (! $log) {
        $this->error('No delivery log was created. Run php artisan abs:repair --seed and retry.');
        return Command::FAILURE;
    }

    $this->line('Recipient: '.$email);
    $this->line('Delivery status: '.strtoupper((string) $log->status));
    if ($log->error_message) $this->line('Transport error: '.$log->error_message);

    if ($log->status !== 'sent') {
        $this->error('EMAIL DELIVERY: FAILED');
        return Command::FAILURE;
    }

    $this->info('EMAIL DELIVERY: SENT');
    $this->line('Confirm inbox/spam placement and production SPF/DKIM/DMARC separately.');
    return Command::SUCCESS;
})->purpose('Send and verify a branded ABS production email through the configured mail transport');

Artisan::command('abs:expiry-reminders', function (BrandedMailService $mail): int {
    if (! (bool) PulseSystemSetting::value('expiry_emails_enabled', true)) {
        $this->line('Expiry email reminders are disabled in Admin → Email Communications.');
        return Command::SUCCESS;
    }

    $sent = 0;
    $skipped = 0;
    $failed = 0;
    $today = now()->startOfDay();
    $configuredDays = PulseSystemSetting::value('expiry_reminder_days', [7, 3, 1, 0]);
    if (! is_array($configuredDays)) $configuredDays = explode(',', (string) $configuredDays);
    $reminderDays = collect($configuredDays)->map(fn ($day) => (int) $day)->filter(fn ($day) => $day >= 0 && $day <= 90)->unique()->values()->all();
    if ($reminderDays === []) $reminderDays = [7, 3, 1, 0];
    $maximumReminderDay = max($reminderDays);

    UserServiceAccess::query()
        ->with(['user.pulseSettings', 'plan'])
        ->where('service', 'pulse')
        ->whereNotNull('ends_at')
        ->whereBetween('ends_at', [$today->copy()->subDay(), $today->copy()->addDays($maximumReminderDay + 1)->endOfDay()])
        ->chunkById(100, function ($accesses) use ($mail, &$sent, &$skipped, &$failed, $today, $reminderDays): void {
            foreach ($accesses as $access) {
                $user = $access->user;
                if (! $user || $user->status !== 'active' || ! $user->email_verified_at) {
                    $skipped++;
                    continue;
                }

                $daysLeft = (int) $today->diffInDays($access->ends_at->copy()->startOfDay(), false);
                if ($daysLeft >= 0 && in_array($daysLeft, $reminderDays, true)) {
                    $event = 'plan_expiry_'.$daysLeft.'d';
                    $already = EmailDeliveryLog::query()
                        ->where('user_id', $user->id)
                        ->where('event', $event)
                        ->where('created_at', '>=', now()->subDays(9))
                        ->get()
                        ->contains(fn ($log) => (int) data_get($log->metadata, 'access_id') === (int) $access->id && $log->status === 'sent');
                    if ($already) { $skipped++; continue; }
                    $before = EmailDeliveryLog::query()->max('id') ?: 0;
                    $mail->planExpiryReminder($user, $access, $daysLeft);
                    $latest = EmailDeliveryLog::query()->where('id', '>', $before)->where('user_id', $user->id)->where('event', $event)->latest('id')->first();
                    if ($latest?->status === 'sent') $sent++; elseif ($latest?->status === 'failed') $failed++; else $skipped++;
                    continue;
                }

                if ($daysLeft < 0) {
                    $already = EmailDeliveryLog::query()
                        ->where('user_id', $user->id)
                        ->where('event', 'plan_expired')
                        ->get()
                        ->contains(fn ($log) => (int) data_get($log->metadata, 'access_id') === (int) $access->id && $log->status === 'sent');
                    if ($already) { $skipped++; continue; }
                    $before = EmailDeliveryLog::query()->max('id') ?: 0;
                    $mail->planExpired($user, $access);
                    $latest = EmailDeliveryLog::query()->where('id', '>', $before)->where('user_id', $user->id)->where('event', 'plan_expired')->latest('id')->first();
                    if ($latest?->status === 'sent') $sent++; elseif ($latest?->status === 'failed') $failed++; else $skipped++;
                }
            }
        });

    $this->info("Expiry communications complete: {$sent} sent; {$skipped} skipped; {$failed} failed.");
    return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Send Admin-configured, deduplicated Pulse plan expiry reminders and expired-access notices');

Artisan::command('abs:daily-market-brief', function (BrandedMailService $mail, MarketDataService $market): int {
    if (! (bool) PulseSystemSetting::value('daily_market_brief_enabled', false)) {
        $this->line('Daily Market Brief is disabled in Admin → Email Communications.');
        return Command::SUCCESS;
    }

    try {
        $overview = $market->overview(true);
    } catch (Throwable $e) {
        $this->error('Market brief aborted because live market data could not be loaded: '.$e->getMessage());
        return Command::FAILURE;
    }

    if (! ($overview['is_live'] ?? false)) {
        $this->error('Market brief aborted because upstream market data is not live. No substitute figures were emailed.');
        return Command::FAILURE;
    }

    $sentBefore = EmailDeliveryLog::query()->where('event', 'daily_market_brief')->where('status', 'sent')->count();
    User::query()
        ->with('pulseSettings')
        ->where('status', 'active')
        ->whereNotNull('email_verified_at')
        ->chunkById(100, function ($users) use ($mail, $overview): void {
            foreach ($users as $user) {
                $mail->dailyMarketBrief($user, $overview);
            }
        });
    $sentAfter = EmailDeliveryLog::query()->where('event', 'daily_market_brief')->where('status', 'sent')->count();
    $this->info('Daily Market Brief complete: '.max(0, $sentAfter - $sentBefore).' email(s) sent.');
    return Command::SUCCESS;
})->purpose('Send the opt-in ABS Daily Market Brief using live market data only');

Artisan::command('abs:scheduler-check', function (PulseRuntimeCadenceService $cadence): int {
    $profile = (string) config('pulse.scheduler.profile', 'standard');
    $minutes = (int) config('pulse.scheduler.cron_minutes', 1);
    $target = (int) config('pulse.market_data.target_price_refresh_seconds', 60);
    $readAge = (int) config('pulse.market_data.read_max_age_seconds', 300);
    $hostgator = (bool) config('pulse.scheduler.hostgator_shared', false);

    $this->info('ABS V15.3.0 scheduler profile check');
    $this->line('Profile: '.$profile);
    $this->line('Expected host cron cadence: every '.$minutes.' minute(s)');
    $runtime = $cadence->profile();
    $this->line('Configured Admin background cadence: '.(int) $runtime['configured_seconds'].' seconds');
    $this->line('Effective runtime cadence: '.(int) $runtime['effective_seconds'].' seconds');
    $this->line('Static central-price target baseline: '.$target.' seconds');
    $this->line('Stored-price read tolerance: '.$readAge.' seconds');

    if ($hostgator) {
        if ($minutes !== 1 || $target !== 60 || $readAge < 120) {
            $this->error('HOSTGATOR SHARED SCHEDULER: MISCONFIGURED');
            $this->line('Use PULSE_SCHEDULER_PROFILE=hostgator_shared with the HostGator cron running once per minute.');
            return Command::FAILURE;
        }
        $this->info('HOSTGATOR SHARED SCHEDULER: READY');
        $this->line('Central Binance Futures prices are written to ABS every minute; scanner and validation consume the shared ABS database.');
        return Command::SUCCESS;
    }

    if ($minutes !== 1) {
        $this->warn('Standard scheduler profile normally expects a once-per-minute host cron when schedule:run is used.');
    }
    $this->info('STANDARD SCHEDULER: READY');
    $this->line('For 30-second local cadence use php artisan schedule:work (RUN-ABS-LARAGON.bat starts it automatically).');
    return Command::SUCCESS;
})->purpose('Verify the selected Laravel scheduler profile and HostGator-safe market-data freshness settings');

Artisan::command('abs:production-check {--email= : Optional recipient for a real outbound email test}', function (): int {
    $this->info('ABS V15.3.0 production acceptance check');
    $results = [];
    $results['environment'] = $this->call('abs:doctor');
    $results['views'] = $this->call('abs:view-audit');
    $results['scheduler'] = $this->call('abs:scheduler-check');
    $results['market'] = $this->call('abs:market-test');
    $results['pulse_public'] = $this->call('abs:pulse-test', ['--environment' => 'live']);
    $results['central_market'] = $this->call('abs:pulse-market-data');
    $results['signal_validation'] = $this->call('abs:pulse-validate-signals', ['--limit' => 100]);
    $results['trade_sync'] = $this->call('abs:pulse-sync');
    $results['execution_reconciliation'] = $this->call('abs:pulse-execution-check');

    $email = trim((string) $this->option('email'));
    if ($email !== '') {
        $results['email'] = $this->call('abs:email-test', ['email' => $email]);
    } else {
        $this->warn('Email delivery was not tested. Re-run with --email=you@example.com before live launch.');
    }

    $failed = collect($results)->filter(fn ($status) => $status !== Command::SUCCESS);
    $this->newLine();
    foreach ($results as $name => $status) {
        $this->line(($status === Command::SUCCESS ? '<info>PASS</info>' : '<error>FAIL</error>').'  '.ucfirst($name));
    }
    if ($failed->isNotEmpty()) {
        $this->error('PRODUCTION ACCEPTANCE: NOT READY');
        return Command::FAILURE;
    }
    $this->info('PRODUCTION ACCEPTANCE: READY FOR LIVE DEPLOYMENT');
    return Command::SUCCESS;
})->purpose('Run full ABS environment, Blade/route, scheduler, public market, central market-data, signal validation, execution reconciliation and optional SMTP production checks');

Artisan::command('abs:pulse-market-data', function (PulseMarketDataService $market): int {
    try {
        $run = $market->syncCentral();
        $this->info("Central Pulse market data synced: {$run->prices_updated} prices; {$run->candle_symbols_updated} scanner candle-symbol updates; {$run->validation_symbols_updated} validation symbols.");
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Central Pulse market-data sync failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Refresh central Binance Futures prices, 15M/4H scanner buffers and short-retention 1M validation candles');

Artisan::command('abs:pulse-validate-signals {--limit=500}', function (PulseSignalValidationService $validation, PulseRuntimeCadenceService $cadence): int {
    try {
        $result = $validation->process(max(1, (int) $this->option('limit')));
        $cadence->markValidationRun();
        $this->info("Signal validation complete: {$result['processed']} checked; {$result['resolved']} resolved.");
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Signal validation failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Validate unresolved Pulse research/signals against future 1M candles with entry-before-TP/SL and ambiguity protection');

Artisan::command('abs:pulse-strategy-cycle {--force : Run one complete cycle even when the configured cadence is not due}', function (PulseStrategyCycleService $cycle): int {
    $result = $cycle->run((bool) $this->option('force'));
    $status = (string) ($result['status'] ?? 'unknown');
    $message = (string) ($result['message'] ?? 'Pulse Strategy Lab cycle finished.');

    if ($status === 'failed') {
        $this->error($message);
        return Command::FAILURE;
    }
    if (in_array($status, ['not_due','disabled','locked'], true)) {
        $this->line($message);
        return Command::SUCCESS;
    }

    $this->info($message);
    $this->line('Market run: #'.(int) data_get($result, 'stages.market.run_id', 0).' · '.(int) data_get($result, 'stages.market.prices_updated', 0).' prices');
    if (data_get($result, 'stages.scan.run_id')) $this->line('Scanner run: #'.(int) data_get($result, 'stages.scan.run_id'));
    $this->line('Paper validations: '.(int) data_get($result, 'stages.validation.processed', 0).' checked · '.(int) data_get($result, 'stages.validation.resolved', 0).' resolved');
    return Command::SUCCESS;
})->purpose('Run the complete Admin research cycle: market refresh, strategy scan and paper-signal reconciliation');

Artisan::command('abs:pulse-strategy-scan {--force : Run now even when the configured cadence is not due}', function (PulseStrategyResearchService $research): int {
    $result = $research->runIfDue((bool) $this->option('force'));
    $status = (string) ($result['status'] ?? 'unknown');
    $message = (string) ($result['message'] ?? 'Strategy research cycle finished.');

    if ($status === 'failed') {
        $this->error($message);
        return Command::FAILURE;
    }
    if (in_array($status, ['not_due','disabled','locked'], true)) {
        $this->line($message);
        return Command::SUCCESS;
    }

    $this->info($message);
    if (! empty($result['run_id'])) $this->line('Scanner run: #'.(int) $result['run_id']);
    if (! empty($result['signal_id'])) $this->line('Qualified system signal: #'.(int) $result['signal_id']);
    return Command::SUCCESS;
})->purpose('Run the user-independent Pulse 15-strategy research engine for the Strategy Dashboard');

Artisan::command('abs:pulse-learning {--date=}', function (PulseLearningService $learning): int {
    $date = trim((string) $this->option('date')) ?: now()->subDay()->toDateString();
    try {
        $learning->rebuildDate(\Illuminate\Support\Carbon::parse($date));
        $this->info('Pulse strategy aggregates and learning state rebuilt for '.$date.'.');
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Pulse learning rebuild failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Rebuild permanent daily strategy aggregates and evidence-protected learning state');

Artisan::command('abs:pulse-analytics-backfill {--days=7 : Rebuild recent retained validation days for V15.3.0 profitability metrics}', function (PulseLearningService $learning): int {
    $days = max(1, min(30, (int) $this->option('days')));
    $this->info('ABS V15.3.0 strategy-profitability backfill');
    $this->line('Requested window: '.$days.' day(s). Detailed signal validation retention may limit older reconstruction.');
    try {
        $availableDates = DB::table('pulse_signal_validations')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('DATE(resolved_at) as resolved_date')
            ->distinct()->orderBy('resolved_date')->pluck('resolved_date');
        if ($availableDates->isEmpty()) {
            $this->warn('No retained resolved validation dates were available to backfill. New validations will populate V15.3.0 analytics automatically.');
            return Command::SUCCESS;
        }
        foreach ($availableDates as $date) {
            $learning->rebuildDate((string) $date);
            $this->line('  rebuilt '.$date);
        }
        $this->info('Profitability analytics backfill complete: '.$availableDates->count().' day(s) rebuilt.');
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Profitability analytics backfill failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Populate recent V15.3.0 R-multiple and strategy-profitability metrics from retained resolved validations');

Artisan::command('abs:pulse-execution-check', function (PulseStrategyAnalyticsService $analytics): int {
    $from = now()->subDay();
    $to = now();
    try {
        $execution = $analytics->actualExecution($from, $to);
        $lastMarketRun = DB::table('pulse_market_data_runs')->latest('id')->first();
        $lastValidation = DB::table('pulse_signal_validations')->max('last_checked_at');
        $lastTradeSync = DB::table('pulse_trades')->max('last_synced_at');
        $staleTrades = DB::table('pulse_trades')->whereIn('status', ['submitting','pending','open','closing','protection_failed'])
            ->where(function ($q) { $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subMinutes(3)); })->count();

        $this->info('ABS V15.3.0 market → validation → execution health');
        $this->line('Last central market run: '.($lastMarketRun?->completed_at ?: $lastMarketRun?->started_at ?: 'never'));
        $this->line('Last signal validation check: '.($lastValidation ?: 'never'));
        $this->line('Last trade sync: '.($lastTradeSync ?: 'never'));
        $this->line('Active/open trade records: '.number_format((int) ($execution['open_trades'] ?? 0)));
        $this->line('Stale active trade records (>3 min): '.number_format((int) $staleTrades));
        $this->line('24h closed trades: '.number_format((int) ($execution['closed_trades'] ?? 0)));
        $this->line('24h confirmed TP / SL: '.number_format((int) ($execution['tp_hits'] ?? 0)).' / '.number_format((int) ($execution['sl_hits'] ?? 0)));
        $this->line('24h actual realized P&L: '.number_format((float) ($execution['realized_pnl'] ?? 0), 6).' · fees '.number_format((float) ($execution['fees'] ?? 0), 6));
        if ($staleTrades > 0) {
            $this->error('Execution reconciliation is stale. Verify the once-per-minute scheduler cron and Binance connectivity before live deployment.');
            return Command::FAILURE;
        }
        $this->info('EXECUTION RECONCILIATION: HEALTHY');
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Execution health check failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Verify central feed, signal validation and Binance trade reconciliation freshness and 24h TP/SL/P&L');

Artisan::command('abs:investor-accruals', function (InvestorPerformanceService $performance, InvestorSettlementService $settlements): int {
    $terms = PortfolioInvestmentTerm::query()->with('account')->where('status','active')->get();
    foreach ($terms as $term) {
        if ($term->account) $performance->syncAgreementPlans($term->account, now(), now()->startOfMonth(), $term->created_by);
    }

    $plans = PortfolioPerformancePlan::query()->where('status', 'active')->whereDate('plan_month', now()->startOfMonth()->toDateString())->get();
    $posted = 0;
    foreach ($plans as $plan) {
        $performance->ensureSchedule($plan);
        $performance->rebalanceFuture($plan);
        $posted += $performance->postDue($plan);
    }

    // V15.7.4: once a month reaches its agreed payout moment, convert the
    // provisional schedule into one posted Profit Paid entry and reconcile the
    // investor statement. This is idempotent and safe to run every minute.
    $settled = $settlements->processAll(now(), true);
    $this->info('Investor accruals: '.$posted.' newly posted; '.$terms->count().' agreement(s) checked.');
    $this->line('Monthly settlements: '.($settled['payouts_created'] ?? 0).' payout(s); '.(($settled['statements_created'] ?? 0)+($settled['statements_updated'] ?? 0)).' statement(s) reconciled; '.count($settled['errors'] ?? []).' error(s).');
    foreach (($settled['errors'] ?? []) as $error) {
        $this->warn('Account '.($error['portfolio_account_id'] ?? '?').': '.($error['message'] ?? 'Settlement failed'));
    }
    return empty($settled['errors']) ? Command::SUCCESS : Command::FAILURE;
})->purpose('Maintain daily investor accruals, pay due monthly profit and reconcile monthly statements');

Artisan::command('abs:sync-economic-calendar', function (EconomicCalendarService $calendar): int {
    if (! $calendar->configured()) {
        $this->warn('Economic Calendar sync skipped: no provider is available. Manual CMS remains available.');
        return Command::SUCCESS;
    }
    try {
        $result = $calendar->sync();
        $this->info('Economic Calendar synced via '.($result['provider'] ?? $calendar->providerName()).': '.$result['created'].' new; '.$result['updated'].' updated; '.$result['skipped'].' skipped.');
        return Command::SUCCESS;
    } catch (Throwable $e) {
        $this->error('Economic Calendar sync failed: '.$e->getMessage());
        return Command::FAILURE;
    }
})->purpose('Sync CPI, PPI, FOMC, jobs, GDP and other macro events with previous, forecast, actual and crypto context');

// V15.3.0 Strategy Lab: the Admin chooses Production Cron, ABS Internal Scheduler,
// or Manual Only. HostGator remains protected at one-minute resolution; local/VPS
// schedule:work can execute the configured 5/30/60/120/300-second research cadence.
if ((bool) config('pulse.scheduler.hostgator_shared', false)) {
    Schedule::command('abs:pulse-strategy-cycle')->everyMinute()->withoutOverlapping(5)
        ->when(fn () => app(PulseRuntimeCadenceService::class)->scheduledEnabled() && app(PulseRuntimeCadenceService::class)->marketDataDue());
    Schedule::command('abs:pulse-sync')->everyMinute()->withoutOverlapping(5);
    Schedule::command('abs:pulse-automation')->everyFiveMinutes()->withoutOverlapping(5);
    Schedule::command('abs:pulse-maintenance')->everyFiveMinutes()->withoutOverlapping(10);
    Schedule::command('abs:cache-market')->everyFiveMinutes()->withoutOverlapping(5);
    Schedule::command('abs:investor-accruals')->everyMinute()->withoutOverlapping(5);
    Schedule::command('abs:sync-economic-calendar')->everyFifteenMinutes()->withoutOverlapping(10)->when(fn () => app(EconomicCalendarService::class)->autoSyncEnabled());
    Schedule::command('abs:pulse-learning')->dailyAt('00:30')->withoutOverlapping(60);
    Schedule::command('abs:expiry-reminders')->dailyAt('08:00')->withoutOverlapping(60);
    Schedule::command('abs:daily-market-brief')->dailyAt('08:15')->withoutOverlapping(60);
    Schedule::command('abs:pulse-pairs --environment=live')->everySixHours()->withoutOverlapping(60);
} else {
    // schedule:work checks every five seconds; runtime guards decide whether the
    // selected Admin cadence is due. Production Cron mode therefore still waits
    // at least 60 seconds, while Internal Scheduler can run at 5 seconds locally/VPS.
    Schedule::command('abs:pulse-strategy-cycle')->everyFiveSeconds()->withoutOverlapping(5)
        ->when(fn () => app(PulseRuntimeCadenceService::class)->scheduledEnabled() && app(PulseRuntimeCadenceService::class)->marketDataDue());
    Schedule::command('abs:pulse-learning')->dailyAt('00:20')->withoutOverlapping(60);
    Schedule::command('abs:cache-market')->everyMinute()->withoutOverlapping(5);
    Schedule::command('abs:investor-accruals')->everyMinute()->withoutOverlapping(5);
    Schedule::command('abs:sync-economic-calendar')->everyFifteenMinutes()->withoutOverlapping(10)->when(fn () => app(EconomicCalendarService::class)->autoSyncEnabled());
    Schedule::command('abs:expiry-reminders')->dailyAt('08:00')->withoutOverlapping(60);
    Schedule::command('abs:daily-market-brief')->dailyAt('08:15')->withoutOverlapping(60);
    Schedule::command('abs:pulse-maintenance')->everyFiveMinutes()->withoutOverlapping(10);
    Schedule::command('abs:pulse-pairs --environment=live')->everySixHours()->withoutOverlapping(60);
    Schedule::command('abs:pulse-sync')->everyMinute()->withoutOverlapping(5);
    Schedule::command('abs:pulse-automation')->everyMinute()->withoutOverlapping(5);
}

// Pulse push notifications (both scheduler profiles): event producers then the
// FCM outbox dispatch. Every producer is deduplicated and rate-limited.
Schedule::command('abs:push-events')->everyMinute()->withoutOverlapping(5);

Artisan::command('abs:push-events {--dispatch-only : Only send already-queued pushes}', function (PulsePushEventService $events, PushNotificationService $push): int {
    if (! $this->option('dispatch-only')) {
        foreach ($events->runAll() as $producer => $queued) {
            if ($queued > 0) $this->line("Queued {$queued} {$producer} push(es).");
        }
    }
    $result = $push->dispatch();
    $this->info("Push dispatch: {$result['sent']} sent; {$result['skipped']} skipped; {$result['failed']} failed.");
    return Command::SUCCESS;
})->purpose('Produce deduplicated Pulse push events (market moves, macro, news, brief, expiry) and dispatch the FCM outbox');

Artisan::command('abs:push-test {--topic= : Public topic, e.g. abs_breaking_news} {--user= : User ID for a private device push} {--type=breaking_news : Notification type for routing} {--title=Pulse test notification} {--body=This is a Pulse push delivery test.}', function (PushNotificationService $push): int {
    $type = (string) $this->option('type');
    $key = 'test:'.now()->format('YmdHis').':'.bin2hex(random_bytes(3));
    $topic = (string) $this->option('topic');
    $user = (string) $this->option('user');
    if (($topic === '') === ($user === '')) {
        $this->error('Pass exactly one of --topic or --user.');
        return Command::FAILURE;
    }
    try {
        $queued = $topic !== ''
            ? $push->queueTopic($topic, $type, (string) $this->option('title'), (string) $this->option('body'), [], $key)
            : $push->queueUser((int) $user, $type, (string) $this->option('title'), (string) $this->option('body'), [], $key);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());
        return Command::FAILURE;
    }
    if (! $queued) {
        $this->error('Not queued: notifications disabled, push table missing, or user not found.');
        return Command::FAILURE;
    }
    $result = $push->dispatch();
    $this->info("Push test: {$result['sent']} sent; {$result['skipped']} skipped; {$result['failed']} failed.");
    $log = \App\Models\PushNotificationLog::query()->where('dedupe_key', $key)->first();
    if ($log?->error) $this->warn('Detail: '.$log->error);
    return $result['sent'] > 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Send one Pulse push to a public topic or a user\'s devices to verify FCM delivery');

Artisan::command('abs:view-audit', function (): int {
    $this->info('ABS V15.1.2 Blade view and named-route audit');

    $viewRoot = resource_path('views');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewRoot, FilesystemIterator::SKIP_DOTS));
    $bladeFiles = [];
    foreach ($files as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $bladeFiles[] = $file->getPathname();
        }
    }

    $compileFailures = [];
    $routeFailures = [];
    $routeReferences = [];
    $lintAvailable = function_exists('proc_open');
    $temporary = storage_path('framework/cache/abs-view-audit.php');
    @mkdir(dirname($temporary), 0775, true);

    foreach ($bladeFiles as $file) {
        $source = (string) file_get_contents($file);
        try {
            $compiled = \Illuminate\Support\Facades\Blade::compileString($source);
            if ($lintAvailable) {
                file_put_contents($temporary, $compiled);
                $process = proc_open([PHP_BINARY, '-l', $temporary], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (is_resource($process)) {
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $exit = proc_close($process);
                    if ($exit !== 0) {
                        $compileFailures[] = str_replace($viewRoot.DIRECTORY_SEPARATOR, '', $file).' — '.trim($stderr ?: $stdout);
                    }
                }
            }
        } catch (Throwable $e) {
            $compileFailures[] = str_replace($viewRoot.DIRECTORY_SEPARATOR, '', $file).' — '.$e->getMessage();
        }

        if (preg_match_all("/route\\(\\s*['\"]([^'\"]+)['\"]/", $source, $matches)) {
            foreach ($matches[1] as $name) $routeReferences[$name] = true;
        }
    }
    @unlink($temporary);

    foreach (array_keys($routeReferences) as $routeName) {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) $routeFailures[] = $routeName;
    }

    $this->line('Blade files inspected: '.count($bladeFiles));
    $this->line('Named routes referenced: '.count($routeReferences));
    $this->line('Compiled PHP lint: '.($lintAvailable ? 'enabled' : 'unavailable on this host; Blade compilation still checked'));

    if ($compileFailures !== []) {
        $this->error('VIEW COMPILE FAILURES: '.count($compileFailures));
        foreach ($compileFailures as $failure) $this->line('  - '.$failure);
    } else {
        $this->info('Blade compilation: PASS');
    }

    if ($routeFailures !== []) {
        $this->error('MISSING NAMED ROUTES: '.count($routeFailures));
        foreach ($routeFailures as $routeName) $this->line('  - '.$routeName);
    } else {
        $this->info('Named-route references: PASS');
    }

    return ($compileFailures === [] && $routeFailures === []) ? Command::SUCCESS : Command::FAILURE;
})->purpose('Compile every Blade view and verify every literal named route referenced by the UI');
