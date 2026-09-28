<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AbsSchemaRepair
{
    public static function diagnosisCacheKey(): string
    {
        $signature = substr(hash('sha256', json_encode([self::REQUIRED_SCHEMA, PulseSchemaRepair::REQUIRED_SCHEMA])), 0, 16);

        return 'abs.installation.diagnosis.'.$signature;
    }

    /**
     * Tables and columns required by the ABS V14.6.1 focused web application, Pulse module and API.
     * The doctor and HTTP readiness middleware both use this map.
     */
    public const REQUIRED_SCHEMA = [
        'users' => ['id', 'name', 'email', 'email_verified_at', 'country_code', 'phone', 'country', 'password', 'role', 'status', 'private_member_approved_at', 'last_login_at', 'remember_token', 'created_at', 'updated_at', 'deleted_at'],
        'password_reset_tokens' => ['email', 'token', 'created_at'],
        'sessions' => ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'],
        'cache' => ['key', 'value', 'expiration'],
        'cache_locks' => ['key', 'owner', 'expiration'],
        'jobs' => ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'],
        'job_batches' => ['id', 'name', 'total_jobs', 'pending_jobs', 'failed_jobs', 'failed_job_ids', 'options', 'cancelled_at', 'created_at', 'finished_at'],
        'failed_jobs' => ['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'],
        'products' => ['id', 'name', 'slug', 'category', 'tagline', 'description', 'icon', 'accent', 'features', 'status', 'sort_order', 'is_featured', 'created_at', 'updated_at', 'deleted_at'],
        'news_articles' => ['id', 'title', 'slug', 'excerpt', 'body', 'category', 'image_url', 'source_name', 'source_url', 'author_name', 'status', 'is_featured', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
        'research_reports' => ['id', 'title', 'slug', 'summary', 'body', 'category', 'asset_symbol', 'risk_level', 'image_url', 'status', 'is_featured', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
        'learning_articles' => ['id', 'title', 'slug', 'excerpt', 'body', 'category', 'level', 'duration_minutes', 'status', 'is_featured', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
        'economic_events' => ['id', 'title', 'country', 'currency', 'impact', 'event_at', 'previous_value', 'forecast_value', 'actual_value', 'source', 'provider_event_id', 'source_url', 'crypto_impact', 'easy_explanation', 'crypto_impact_summary', 'is_crypto_relevant', 'synced_at', 'created_at', 'updated_at'],
        'site_settings' => ['id', 'key', 'value', 'type', 'group'],
        'community_posts' => ['id', 'user_id', 'title', 'body', 'category', 'sentiment', 'status', 'is_pinned', 'created_at', 'updated_at', 'deleted_at'],
        'community_comments' => ['id', 'community_post_id', 'user_id', 'body', 'status', 'created_at', 'updated_at'],
        'newsletter_subscribers' => ['id', 'email', 'preferences', 'status', 'confirmed_at', 'unsubscribed_at', 'created_at', 'updated_at'],
        'watchlists' => ['id', 'user_id', 'symbol', 'display_name', 'sort_order', 'created_at', 'updated_at'],
        'portfolio_accounts' => ['id', 'user_id', 'account_name', 'currency', 'opening_value', 'current_value', 'net_contributions', 'total_profit', 'monthly_profit', 'opening_value_usd', 'current_value_usd', 'net_contributions_usd', 'total_profit_usd', 'monthly_profit_usd', 'realized_fx_gain_loss_usd', 'valuation_date', 'is_active', 'notes', 'created_at', 'updated_at'],
        'portfolio_transactions' => ['id', 'portfolio_account_id', 'type', 'amount', 'currency', 'fx_rate_to_usd', 'usd_amount', 'principal_usd_basis', 'settlement_usd_amount', 'fx_gain_loss_usd', 'transaction_date', 'performance_month', 'entry_source', 'reference', 'description', 'status', 'created_by', 'current_value_effect', 'net_contributions_effect', 'profit_effect', 'monthly_profit_effect', 'usd_current_value_effect', 'usd_net_contributions_effect', 'usd_profit_effect', 'usd_monthly_profit_effect', 'fx_source', 'fx_locked_at', 'posted_at', 'voided_at', 'voided_by', 'void_reason', 'created_at', 'updated_at'],
        'monthly_statements' => ['id', 'portfolio_account_id', 'statement_month', 'currency', 'fx_rate_to_usd', 'opening_balance', 'contributions', 'withdrawals', 'profit_loss', 'profit_paid', 'closing_balance', 'opening_balance_usd', 'contributions_usd', 'withdrawals_usd', 'profit_loss_usd', 'profit_paid_usd', 'closing_balance_usd', 'payment_date', 'auto_generated', 'notes', 'pdf_path', 'published_at', 'created_at', 'updated_at'],
        'portfolio_requests' => ['id', 'user_id', 'portfolio_account_id', 'type', 'amount', 'currency', 'status', 'message', 'admin_note', 'processed_by', 'processed_at', 'created_at', 'updated_at'],
        'portfolio_performance_plans' => ['id', 'portfolio_account_id', 'investment_term_id', 'plan_month', 'accrual_start_date', 'accrual_end_date', 'base_amount', 'target_rate', 'target_amount', 'calculation_basis', 'status', 'created_by', 'notes', 'created_at', 'updated_at'],
        'portfolio_investment_terms' => ['id', 'portfolio_account_id', 'effective_from', 'monthly_target_rate', 'status', 'auto_payout', 'payout_day', 'created_by', 'notes', 'created_at', 'updated_at'],
        'portfolio_daily_accruals' => ['id', 'portfolio_performance_plan_id', 'accrual_date', 'scheduled_at', 'planned_amount', 'manual_adjustment', 'posted_amount', 'posted_at', 'adjusted_by', 'admin_note', 'created_at', 'updated_at'],
        'notifications' => ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'],
        'email_delivery_logs' => ['id', 'user_id', 'event', 'recipient_email', 'subject', 'status', 'error_message', 'metadata', 'sent_at', 'created_at', 'updated_at'],
        'mobile_devices' => ['id', 'user_id', 'device_uuid', 'platform', 'device_name', 'app_version', 'os_version', 'push_token', 'is_active', 'last_seen_at', 'created_at', 'updated_at'],
        'contact_messages' => ['id', 'user_id', 'name', 'email', 'subject', 'category', 'message', 'status', 'priority', 'admin_notes', 'replied_at', 'created_at', 'updated_at'],
        'support_conversations' => ['id', 'user_id', 'assigned_admin_id', 'name', 'email', 'subject', 'category', 'channel', 'status', 'priority', 'last_message_at', 'last_customer_message_at', 'last_admin_message_at', 'assistant_handled_at', 'escalated_at', 'closed_at', 'created_at', 'updated_at'],
        'support_messages' => ['id', 'support_conversation_id', 'user_id', 'sender_type', 'message_type', 'body', 'metadata', 'read_by_customer_at', 'read_by_admin_at', 'created_at', 'updated_at'],
        'personal_access_tokens' => ['id', 'tokenable_type', 'tokenable_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at'],
    ];

    public static function diagnose(): array
    {
        $result = [
            'ready' => false,
            'connection' => (string) config('database.default'),
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'missing_tables' => [],
            'missing_columns' => [],
            'error' => null,
        ];

        try {
            DB::connection()->getPdo();

            foreach (array_merge(self::REQUIRED_SCHEMA, PulseSchemaRepair::REQUIRED_SCHEMA) as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    $result['missing_tables'][] = $table;
                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $result['missing_columns'][$table][] = $column;
                    }
                }
            }

            $result['ready'] = $result['missing_tables'] === [] && $result['missing_columns'] === [];
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Non-destructively creates missing ABS tables and adds missing columns.
     * Existing rows and unrelated legacy/Pulse tables are preserved.
     */
    public static function repair(): void
    {
        self::ensureUsers();
        self::ensureFrameworkTables();
        self::ensureContentTables();
        self::ensureCommunityAndMemberTables();
        self::ensureProductionTables();
        self::widenLegacyEnums();
        self::normalizeDefaults();
        PulseSchemaRepair::repair();
    }

    private static function ensureUsers(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('country_code', 8)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('country', 80)->nullable();
                $table->string('password');
                $table->string('role', 40)->default('user')->index();
                $table->string('status', 40)->default('active')->index();
                $table->timestamp('private_member_approved_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            self::addMissing('users', [
                'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
                'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
                'email_verified_at' => fn (Blueprint $t) => $t->timestamp('email_verified_at')->nullable(),
                'country_code' => fn (Blueprint $t) => $t->string('country_code', 8)->nullable(),
                'phone' => fn (Blueprint $t) => $t->string('phone', 32)->nullable(),
                'country' => fn (Blueprint $t) => $t->string('country', 80)->nullable(),
                'password' => fn (Blueprint $t) => $t->string('password')->nullable(),
                'role' => fn (Blueprint $t) => $t->string('role', 40)->default('user'),
                'status' => fn (Blueprint $t) => $t->string('status', 40)->default('active'),
                'private_member_approved_at' => fn (Blueprint $t) => $t->timestamp('private_member_approved_at')->nullable(),
                'last_login_at' => fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable(),
                'remember_token' => fn (Blueprint $t) => $t->string('remember_token', 100)->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        } else {
            self::addMissing('password_reset_tokens', [
                'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
                'token' => fn (Blueprint $t) => $t->string('token')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        } else {
            self::addMissing('sessions', [
                'id' => fn (Blueprint $t) => $t->string('id')->nullable(),
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'ip_address' => fn (Blueprint $t) => $t->string('ip_address', 45)->nullable(),
                'user_agent' => fn (Blueprint $t) => $t->text('user_agent')->nullable(),
                'payload' => fn (Blueprint $t) => $t->longText('payload')->nullable(),
                'last_activity' => fn (Blueprint $t) => $t->integer('last_activity')->default(0),
            ]);
        }
    }

    private static function ensureFrameworkTables(): void
    {
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        } else {
            self::addMissing('cache', [
                'key' => fn (Blueprint $t) => $t->string('key')->nullable(),
                'value' => fn (Blueprint $t) => $t->mediumText('value')->nullable(),
                'expiration' => fn (Blueprint $t) => $t->integer('expiration')->default(0),
            ]);
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        } else {
            self::addMissing('cache_locks', [
                'key' => fn (Blueprint $t) => $t->string('key')->nullable(),
                'owner' => fn (Blueprint $t) => $t->string('owner')->nullable(),
                'expiration' => fn (Blueprint $t) => $t->integer('expiration')->default(0),
            ]);
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        } else {
            self::addMissing('jobs', [
                'queue' => fn (Blueprint $t) => $t->string('queue')->default('default'),
                'payload' => fn (Blueprint $t) => $t->longText('payload')->nullable(),
                'attempts' => fn (Blueprint $t) => $t->unsignedTinyInteger('attempts')->default(0),
                'reserved_at' => fn (Blueprint $t) => $t->unsignedInteger('reserved_at')->nullable(),
                'available_at' => fn (Blueprint $t) => $t->unsignedInteger('available_at')->default(0),
                'created_at' => fn (Blueprint $t) => $t->unsignedInteger('created_at')->default(0),
            ]);
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        } else {
            self::addMissing('job_batches', [
                'id' => fn (Blueprint $t) => $t->string('id')->nullable(),
                'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
                'total_jobs' => fn (Blueprint $t) => $t->integer('total_jobs')->default(0),
                'pending_jobs' => fn (Blueprint $t) => $t->integer('pending_jobs')->default(0),
                'failed_jobs' => fn (Blueprint $t) => $t->integer('failed_jobs')->default(0),
                'failed_job_ids' => fn (Blueprint $t) => $t->longText('failed_job_ids')->nullable(),
                'options' => fn (Blueprint $t) => $t->mediumText('options')->nullable(),
                'cancelled_at' => fn (Blueprint $t) => $t->integer('cancelled_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->integer('created_at')->default(0),
                'finished_at' => fn (Blueprint $t) => $t->integer('finished_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        } else {
            self::addMissing('failed_jobs', [
                'uuid' => fn (Blueprint $t) => $t->string('uuid')->nullable(),
                'connection' => fn (Blueprint $t) => $t->text('connection')->nullable(),
                'queue' => fn (Blueprint $t) => $t->text('queue')->nullable(),
                'payload' => fn (Blueprint $t) => $t->longText('payload')->nullable(),
                'exception' => fn (Blueprint $t) => $t->longText('exception')->nullable(),
                'failed_at' => fn (Blueprint $t) => $t->timestamp('failed_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('tokenable_type');
                $table->unsignedBigInteger('tokenable_id');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
                $table->index(['tokenable_type', 'tokenable_id']);
            });
        } else {
            self::addMissing('personal_access_tokens', [
                'tokenable_type' => fn (Blueprint $t) => $t->string('tokenable_type')->nullable(),
                'tokenable_id' => fn (Blueprint $t) => $t->unsignedBigInteger('tokenable_id')->nullable(),
                'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
                'token' => fn (Blueprint $t) => $t->string('token', 64)->nullable(),
                'abilities' => fn (Blueprint $t) => $t->text('abilities')->nullable(),
                'last_used_at' => fn (Blueprint $t) => $t->timestamp('last_used_at')->nullable(),
                'expires_at' => fn (Blueprint $t) => $t->timestamp('expires_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }
    }

    private static function ensureContentTables(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('category')->index();
                $table->string('tagline');
                $table->text('description');
                $table->string('icon', 20)->default('◈');
                $table->string('accent', 30)->default('violet');
                $table->json('features')->nullable();
                $table->string('status', 30)->default('live')->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            self::addMissing('products', [
                'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
                'slug' => fn (Blueprint $t) => $t->string('slug')->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category')->default('General'),
                'tagline' => fn (Blueprint $t) => $t->string('tagline')->default('ABS product'),
                'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
                'icon' => fn (Blueprint $t) => $t->string('icon', 20)->default('◈'),
                'accent' => fn (Blueprint $t) => $t->string('accent', 30)->default('violet'),
                'features' => fn (Blueprint $t) => $t->json('features')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('live'),
                'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
                'is_featured' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('news_articles')) {
            Schema::create('news_articles', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt');
                $table->longText('body');
                $table->string('category')->index();
                $table->string('image_url')->nullable();
                $table->string('source_name')->nullable();
                $table->string('source_url')->nullable();
                $table->string('author_name')->default('ABS Editorial');
                $table->string('status', 30)->default('draft')->index();
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            self::addMissing('news_articles', [
                'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
                'slug' => fn (Blueprint $t) => $t->string('slug')->nullable(),
                'excerpt' => fn (Blueprint $t) => $t->text('excerpt')->nullable(),
                'body' => fn (Blueprint $t) => $t->longText('body')->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category')->default('Market News'),
                'image_url' => fn (Blueprint $t) => $t->string('image_url')->nullable(),
                'source_name' => fn (Blueprint $t) => $t->string('source_name')->nullable(),
                'source_url' => fn (Blueprint $t) => $t->string('source_url')->nullable(),
                'author_name' => fn (Blueprint $t) => $t->string('author_name')->default('ABS Editorial'),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('draft'),
                'is_featured' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
                'published_at' => fn (Blueprint $t) => $t->timestamp('published_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('research_reports')) {
            Schema::create('research_reports', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('summary');
                $table->longText('body');
                $table->string('category')->index();
                $table->string('asset_symbol', 20)->nullable()->index();
                $table->string('risk_level', 30)->default('not_rated');
                $table->string('image_url')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            self::addMissing('research_reports', [
                'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
                'slug' => fn (Blueprint $t) => $t->string('slug')->nullable(),
                'summary' => fn (Blueprint $t) => $t->text('summary')->nullable(),
                'body' => fn (Blueprint $t) => $t->longText('body')->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category')->default('Research'),
                'asset_symbol' => fn (Blueprint $t) => $t->string('asset_symbol', 20)->nullable(),
                'risk_level' => fn (Blueprint $t) => $t->string('risk_level', 30)->default('not_rated'),
                'image_url' => fn (Blueprint $t) => $t->string('image_url')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('draft'),
                'is_featured' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
                'published_at' => fn (Blueprint $t) => $t->timestamp('published_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('learning_articles')) {
            Schema::create('learning_articles', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt');
                $table->longText('body');
                $table->string('category')->index();
                $table->string('level', 30)->default('beginner');
                $table->unsignedInteger('duration_minutes')->default(5);
                $table->string('status', 30)->default('draft')->index();
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            self::addMissing('learning_articles', [
                'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
                'slug' => fn (Blueprint $t) => $t->string('slug')->nullable(),
                'excerpt' => fn (Blueprint $t) => $t->text('excerpt')->nullable(),
                'body' => fn (Blueprint $t) => $t->longText('body')->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category')->default('Education'),
                'level' => fn (Blueprint $t) => $t->string('level', 30)->default('beginner'),
                'duration_minutes' => fn (Blueprint $t) => $t->unsignedInteger('duration_minutes')->default(5),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('draft'),
                'is_featured' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
                'published_at' => fn (Blueprint $t) => $t->timestamp('published_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('economic_events')) {
            Schema::create('economic_events', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('country', 80)->nullable();
                $table->string('currency', 10)->nullable()->index();
                $table->string('impact', 30)->default('medium')->index();
                $table->timestamp('event_at')->index();
                $table->string('previous_value')->nullable();
                $table->string('forecast_value')->nullable();
                $table->string('actual_value')->nullable();
                $table->string('source')->nullable();
                $table->string('provider_event_id', 190)->nullable()->index();
                $table->string('source_url', 500)->nullable();
                $table->string('crypto_impact', 20)->nullable()->index();
                $table->text('easy_explanation')->nullable();
                $table->text('crypto_impact_summary')->nullable();
                $table->boolean('is_crypto_relevant')->default(true)->index();
                $table->timestamp('synced_at')->nullable()->index();
                $table->timestamps();
            });
        } else {
            self::addMissing('economic_events', [
                'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
                'country' => fn (Blueprint $t) => $t->string('country', 80)->nullable(),
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->nullable(),
                'impact' => fn (Blueprint $t) => $t->string('impact', 30)->default('medium'),
                'event_at' => fn (Blueprint $t) => $t->timestamp('event_at')->nullable(),
                'previous_value' => fn (Blueprint $t) => $t->string('previous_value')->nullable(),
                'forecast_value' => fn (Blueprint $t) => $t->string('forecast_value')->nullable(),
                'actual_value' => fn (Blueprint $t) => $t->string('actual_value')->nullable(),
                'source' => fn (Blueprint $t) => $t->string('source')->nullable(),
                'provider_event_id' => fn (Blueprint $t) => $t->string('provider_event_id', 190)->nullable(),
                'source_url' => fn (Blueprint $t) => $t->string('source_url', 500)->nullable(),
                'crypto_impact' => fn (Blueprint $t) => $t->string('crypto_impact', 20)->nullable(),
                'easy_explanation' => fn (Blueprint $t) => $t->text('easy_explanation')->nullable(),
                'crypto_impact_summary' => fn (Blueprint $t) => $t->text('crypto_impact_summary')->nullable(),
                'is_crypto_relevant' => fn (Blueprint $t) => $t->boolean('is_crypto_relevant')->default(true),
                'synced_at' => fn (Blueprint $t) => $t->timestamp('synced_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->string('group')->default('general')->index();
            });
        } else {
            self::addMissing('site_settings', [
                'key' => fn (Blueprint $t) => $t->string('key')->nullable(),
                'value' => fn (Blueprint $t) => $t->text('value')->nullable(),
                'type' => fn (Blueprint $t) => $t->string('type')->default('string'),
                'group' => fn (Blueprint $t) => $t->string('group')->default('general'),
            ]);
        }
    }

    private static function ensureCommunityAndMemberTables(): void
    {
        if (! Schema::hasTable('community_posts')) {
            Schema::create('community_posts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('title');
                $table->text('body');
                $table->string('category')->default('general')->index();
                $table->string('sentiment', 30)->default('neutral');
                $table->string('status', 30)->default('published')->index();
                $table->boolean('is_pinned')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            self::addMissing('community_posts', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
                'body' => fn (Blueprint $t) => $t->text('body')->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category')->default('general'),
                'sentiment' => fn (Blueprint $t) => $t->string('sentiment', 30)->default('neutral'),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('published'),
                'is_pinned' => fn (Blueprint $t) => $t->boolean('is_pinned')->default(false),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
                'deleted_at' => fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('community_comments')) {
            Schema::create('community_comments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('community_post_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->text('body');
                $table->string('status', 30)->default('published');
                $table->timestamps();
            });
        } else {
            self::addMissing('community_comments', [
                'community_post_id' => fn (Blueprint $t) => $t->unsignedBigInteger('community_post_id')->nullable(),
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'body' => fn (Blueprint $t) => $t->text('body')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('published'),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->json('preferences')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('newsletter_subscribers', [
                'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
                'preferences' => fn (Blueprint $t) => $t->json('preferences')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('active'),
                'confirmed_at' => fn (Blueprint $t) => $t->timestamp('confirmed_at')->nullable(),
                'unsubscribed_at' => fn (Blueprint $t) => $t->timestamp('unsubscribed_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('watchlists')) {
            Schema::create('watchlists', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('symbol', 30);
                $table->string('display_name')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'symbol']);
            });
        } else {
            self::addMissing('watchlists', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'symbol' => fn (Blueprint $t) => $t->string('symbol', 30)->nullable(),
                'display_name' => fn (Blueprint $t) => $t->string('display_name')->nullable(),
                'sort_order' => fn (Blueprint $t) => $t->unsignedInteger('sort_order')->default(0),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('portfolio_accounts')) {
            Schema::create('portfolio_accounts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('account_name')->default('Private Investor Portfolio');
                $table->string('currency', 10)->default('USD');
                $table->decimal('opening_value', 18, 2)->default(0);
                $table->decimal('current_value', 18, 2)->default(0);
                $table->decimal('net_contributions', 18, 2)->default(0);
                $table->decimal('total_profit', 18, 2)->default(0);
                $table->decimal('monthly_profit', 18, 2)->default(0);
                $table->decimal('opening_value_usd', 18, 2)->nullable();
                $table->decimal('current_value_usd', 18, 2)->nullable();
                $table->decimal('net_contributions_usd', 18, 2)->nullable();
                $table->decimal('total_profit_usd', 18, 2)->nullable();
                $table->decimal('monthly_profit_usd', 18, 2)->nullable();
                $table->decimal('realized_fx_gain_loss_usd', 18, 2)->default(0);
                $table->date('valuation_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('portfolio_accounts', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'account_name' => fn (Blueprint $t) => $t->string('account_name')->default('Private Investor Portfolio'),
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->default('USD'),
                'opening_value' => fn (Blueprint $t) => $t->decimal('opening_value', 18, 2)->default(0),
                'current_value' => fn (Blueprint $t) => $t->decimal('current_value', 18, 2)->default(0),
                'net_contributions' => fn (Blueprint $t) => $t->decimal('net_contributions', 18, 2)->default(0),
                'total_profit' => fn (Blueprint $t) => $t->decimal('total_profit', 18, 2)->default(0),
                'monthly_profit' => fn (Blueprint $t) => $t->decimal('monthly_profit', 18, 2)->default(0),
                'opening_value_usd' => fn (Blueprint $t) => $t->decimal('opening_value_usd', 18, 2)->nullable(),
                'current_value_usd' => fn (Blueprint $t) => $t->decimal('current_value_usd', 18, 2)->nullable(),
                'net_contributions_usd' => fn (Blueprint $t) => $t->decimal('net_contributions_usd', 18, 2)->nullable(),
                'total_profit_usd' => fn (Blueprint $t) => $t->decimal('total_profit_usd', 18, 2)->nullable(),
                'monthly_profit_usd' => fn (Blueprint $t) => $t->decimal('monthly_profit_usd', 18, 2)->nullable(),
                'realized_fx_gain_loss_usd' => fn (Blueprint $t) => $t->decimal('realized_fx_gain_loss_usd', 18, 2)->default(0),
                'valuation_date' => fn (Blueprint $t) => $t->date('valuation_date')->nullable(),
                'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
                'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('portfolio_transactions')) {
            Schema::create('portfolio_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->index();
                $table->string('type', 30);
                $table->decimal('amount', 18, 2);
                $table->string('currency', 10)->nullable();
                $table->decimal('fx_rate_to_usd', 18, 8)->nullable();
                $table->decimal('usd_amount', 18, 2)->nullable();
                $table->decimal('principal_usd_basis', 18, 2)->nullable();
                $table->decimal('settlement_usd_amount', 18, 2)->nullable();
                $table->decimal('fx_gain_loss_usd', 18, 2)->default(0);
                $table->date('transaction_date')->index();
                $table->date('performance_month')->nullable()->index();
                $table->string('entry_source', 40)->default('admin_manual')->index();
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('posted')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->decimal('current_value_effect', 18, 2)->default(0);
                $table->decimal('net_contributions_effect', 18, 2)->default(0);
                $table->decimal('profit_effect', 18, 2)->default(0);
                $table->decimal('monthly_profit_effect', 18, 2)->default(0);
                $table->decimal('usd_current_value_effect', 18, 2)->nullable();
                $table->decimal('usd_net_contributions_effect', 18, 2)->nullable();
                $table->decimal('usd_profit_effect', 18, 2)->nullable();
                $table->decimal('usd_monthly_profit_effect', 18, 2)->nullable();
                $table->string('fx_source', 40)->nullable();
                $table->timestamp('fx_locked_at')->nullable();
                $table->timestamp('posted_at')->nullable()->index();
                $table->timestamp('voided_at')->nullable()->index();
                $table->unsignedBigInteger('voided_by')->nullable()->index();
                $table->text('void_reason')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('portfolio_transactions', [
                'portfolio_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('portfolio_account_id')->nullable(),
                'type' => fn (Blueprint $t) => $t->string('type', 30)->default('adjustment'),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 18, 2)->default(0),
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->nullable(),
                'fx_rate_to_usd' => fn (Blueprint $t) => $t->decimal('fx_rate_to_usd', 18, 8)->nullable(),
                'usd_amount' => fn (Blueprint $t) => $t->decimal('usd_amount', 18, 2)->nullable(),
                'principal_usd_basis' => fn (Blueprint $t) => $t->decimal('principal_usd_basis', 18, 2)->nullable(),
                'settlement_usd_amount' => fn (Blueprint $t) => $t->decimal('settlement_usd_amount', 18, 2)->nullable(),
                'fx_gain_loss_usd' => fn (Blueprint $t) => $t->decimal('fx_gain_loss_usd', 18, 2)->default(0),
                'transaction_date' => fn (Blueprint $t) => $t->date('transaction_date')->nullable(),
                'performance_month' => fn (Blueprint $t) => $t->date('performance_month')->nullable(),
                'entry_source' => fn (Blueprint $t) => $t->string('entry_source', 40)->default('admin_manual'),
                'reference' => fn (Blueprint $t) => $t->string('reference')->nullable(),
                'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 20)->default('posted'),
                'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                'current_value_effect' => fn (Blueprint $t) => $t->decimal('current_value_effect', 18, 2)->default(0),
                'net_contributions_effect' => fn (Blueprint $t) => $t->decimal('net_contributions_effect', 18, 2)->default(0),
                'profit_effect' => fn (Blueprint $t) => $t->decimal('profit_effect', 18, 2)->default(0),
                'monthly_profit_effect' => fn (Blueprint $t) => $t->decimal('monthly_profit_effect', 18, 2)->default(0),
                'usd_current_value_effect' => fn (Blueprint $t) => $t->decimal('usd_current_value_effect', 18, 2)->nullable(),
                'usd_net_contributions_effect' => fn (Blueprint $t) => $t->decimal('usd_net_contributions_effect', 18, 2)->nullable(),
                'usd_profit_effect' => fn (Blueprint $t) => $t->decimal('usd_profit_effect', 18, 2)->nullable(),
                'usd_monthly_profit_effect' => fn (Blueprint $t) => $t->decimal('usd_monthly_profit_effect', 18, 2)->nullable(),
                'fx_source' => fn (Blueprint $t) => $t->string('fx_source', 40)->nullable(),
                'fx_locked_at' => fn (Blueprint $t) => $t->timestamp('fx_locked_at')->nullable(),
                'posted_at' => fn (Blueprint $t) => $t->timestamp('posted_at')->nullable(),
                'voided_at' => fn (Blueprint $t) => $t->timestamp('voided_at')->nullable(),
                'voided_by' => fn (Blueprint $t) => $t->unsignedBigInteger('voided_by')->nullable(),
                'void_reason' => fn (Blueprint $t) => $t->text('void_reason')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        try {
            if (Schema::hasTable('portfolio_accounts') && Schema::hasColumn('portfolio_accounts', 'opening_value_usd')) {
                DB::table('portfolio_accounts')->whereRaw("UPPER(currency) = 'USD'")->update([
                    'opening_value_usd' => DB::raw('opening_value'),
                    'current_value_usd' => DB::raw('current_value'),
                    'net_contributions_usd' => DB::raw('net_contributions'),
                    'total_profit_usd' => DB::raw('total_profit'),
                    'monthly_profit_usd' => DB::raw('monthly_profit'),
                    'realized_fx_gain_loss_usd' => DB::raw('COALESCE(realized_fx_gain_loss_usd, 0)'),
                ]);
            }
            if (Schema::hasTable('portfolio_transactions') && Schema::hasColumn('portfolio_transactions', 'status')) {
                DB::table('portfolio_transactions')->whereNull('status')->update(['status' => 'posted']);
                if (Schema::hasColumn('portfolio_transactions', 'posted_at')) {
                    DB::table('portfolio_transactions')->where('status', 'posted')->whereNull('posted_at')->update(['posted_at' => DB::raw('COALESCE(created_at, updated_at)')]);
                }
                if (Schema::hasColumn('portfolio_transactions', 'current_value_effect')) {
                    // V15.7.4: paid profit is an external distribution and never increases principal.
                    DB::statement("UPDATE portfolio_transactions SET current_value_effect = CASE type WHEN 'deposit' THEN amount WHEN 'withdrawal' THEN -amount WHEN 'profit' THEN 0 WHEN 'loss' THEN -amount WHEN 'fee' THEN -amount ELSE current_value_effect END WHERE status = 'posted'");
                    DB::statement("UPDATE portfolio_transactions SET net_contributions_effect = CASE type WHEN 'deposit' THEN amount WHEN 'withdrawal' THEN -amount WHEN 'profit' THEN 0 ELSE net_contributions_effect END WHERE status = 'posted'");
                    DB::statement("UPDATE portfolio_transactions SET profit_effect = CASE type WHEN 'profit' THEN amount WHEN 'loss' THEN -amount WHEN 'fee' THEN -amount ELSE profit_effect END WHERE status = 'posted'");
                    DB::statement("UPDATE portfolio_transactions SET monthly_profit_effect = CASE type WHEN 'profit' THEN amount WHEN 'loss' THEN -amount WHEN 'fee' THEN -amount ELSE monthly_profit_effect END WHERE status = 'posted'");
                }
                if (Schema::hasColumn('portfolio_transactions', 'currency') && Schema::hasColumn('portfolio_transactions', 'usd_amount')) {
                    $usdAccountIds = DB::table('portfolio_accounts')->whereRaw("UPPER(currency) = 'USD'")->pluck('id');
                    if ($usdAccountIds->isNotEmpty()) {
                        DB::table('portfolio_transactions')->whereIn('portfolio_account_id', $usdAccountIds)->whereNull('currency')->update(['currency' => 'USD']);
                        DB::table('portfolio_transactions')->whereIn('portfolio_account_id', $usdAccountIds)->update([
                            'fx_rate_to_usd' => 1,
                            'usd_amount' => DB::raw('amount'),
                            'settlement_usd_amount' => DB::raw('amount'),
                            'principal_usd_basis' => DB::raw("CASE WHEN type IN ('deposit','withdrawal') THEN amount ELSE principal_usd_basis END"),
                            'fx_gain_loss_usd' => 0,
                            'usd_current_value_effect' => DB::raw('current_value_effect'),
                            'usd_net_contributions_effect' => DB::raw('net_contributions_effect'),
                            'usd_profit_effect' => DB::raw('profit_effect'),
                            'usd_monthly_profit_effect' => DB::raw('monthly_profit_effect'),
                            'fx_source' => DB::raw("COALESCE(fx_source, 'native_usd')"),
                        ]);
                    }
                }
            }
        } catch (Throwable) {
            // Schema repair remains best-effort. Normal migrations perform the same backfill.
        }

        try {
            if (Schema::hasTable('portfolio_transactions')) {
                if (Schema::hasColumn('portfolio_transactions','entry_source')) {
                    DB::table('portfolio_transactions')->whereNull('entry_source')->orWhere('entry_source','')->update(['entry_source'=>'admin_manual']);
                }
                if (Schema::hasColumn('portfolio_transactions','performance_month')) {
                    DB::table('portfolio_transactions')->where('type','profit')->whereNull('performance_month')->orderBy('id')->chunkById(250, function ($rows): void {
                        foreach ($rows as $row) {
                            $date = \Carbon\Carbon::parse($row->transaction_date ?: $row->created_at ?: now())->startOfDay();
                            $month = $date->day <= 5 ? $date->copy()->subMonthNoOverflow()->startOfMonth() : $date->copy()->startOfMonth();
                            DB::table('portfolio_transactions')->where('id',$row->id)->update(['performance_month'=>$month->toDateString()]);
                        }
                    });
                }
                // Paid profit is external to capital in both investor and Admin USD ledgers.
                if (Schema::hasColumn('portfolio_transactions','usd_current_value_effect')) {
                    DB::table('portfolio_transactions')->where('type','profit')->update(['current_value_effect'=>0,'usd_current_value_effect'=>0]);
                } else {
                    DB::table('portfolio_transactions')->where('type','profit')->update(['current_value_effect'=>0]);
                }
            }

            // Keep account rollups aligned with the preserved ledger after the V15.7.4 correction.
            if (Schema::hasTable('portfolio_accounts') && Schema::hasTable('portfolio_transactions')) {
                DB::table('portfolio_accounts')->orderBy('id')->chunkById(100, function ($accounts): void {
                    foreach ($accounts as $account) {
                        $posted = DB::table('portfolio_transactions')->where('portfolio_account_id',$account->id)->where('status','posted');
                        $openingLocal = (float)($account->opening_value ?? 0);
                        $currency = strtoupper((string)($account->currency ?? 'USD'));
                        $openingUsd = (float)($account->opening_value_usd ?? ($currency === 'USD' ? $openingLocal : 0));
                        $update = [
                            'current_value'=>max(0,round($openingLocal + (float)(clone $posted)->sum('current_value_effect'),2)),
                            'net_contributions'=>max(0,round($openingLocal + (float)(clone $posted)->sum('net_contributions_effect'),2)),
                            'total_profit'=>round((float)(clone $posted)->sum('profit_effect'),2),
                        ];
                        if (Schema::hasColumn('portfolio_accounts','current_value_usd')) {
                            $update['current_value_usd']=max(0,round($openingUsd + (float)(clone $posted)->sum('usd_current_value_effect'),2));
                            $update['net_contributions_usd']=max(0,round($openingUsd + (float)(clone $posted)->sum('usd_net_contributions_effect'),2));
                            $update['total_profit_usd']=round((float)(clone $posted)->sum('usd_profit_effect'),2);
                            $update['realized_fx_gain_loss_usd']=round((float)(clone $posted)->sum('fx_gain_loss_usd'),2);
                        }
                        $monthStart=now()->startOfMonth()->toDateString();
                        if (Schema::hasColumn('portfolio_transactions','performance_month')) {
                            $update['monthly_profit']=round((float)DB::table('portfolio_transactions')->where('portfolio_account_id',$account->id)->where('status','posted')->where('type','profit')->whereDate('performance_month',$monthStart)->sum('amount'),2);
                            if (Schema::hasColumn('portfolio_accounts','monthly_profit_usd')) $update['monthly_profit_usd']=round((float)DB::table('portfolio_transactions')->where('portfolio_account_id',$account->id)->where('status','posted')->where('type','profit')->whereDate('performance_month',$monthStart)->sum('usd_amount'),2);
                        }
                        DB::table('portfolio_accounts')->where('id',$account->id)->update($update);
                    }
                });
            }
        } catch (Throwable) {
            // Historical payout/account reconciliation is best-effort and is also enforced by the migration.
        }

        if (! Schema::hasTable('monthly_statements')) {
            Schema::create('monthly_statements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->index();
                $table->date('statement_month')->index();
                $table->string('currency', 10)->nullable();
                $table->decimal('fx_rate_to_usd', 18, 8)->nullable();
                $table->decimal('opening_balance', 18, 2)->default(0);
                $table->decimal('contributions', 18, 2)->default(0);
                $table->decimal('withdrawals', 18, 2)->default(0);
                $table->decimal('profit_loss', 18, 2)->default(0);
                $table->decimal('profit_paid', 18, 2)->default(0);
                $table->decimal('closing_balance', 18, 2)->default(0);
                $table->decimal('opening_balance_usd', 18, 2)->nullable();
                $table->decimal('contributions_usd', 18, 2)->nullable();
                $table->decimal('withdrawals_usd', 18, 2)->nullable();
                $table->decimal('profit_loss_usd', 18, 2)->nullable();
                $table->decimal('profit_paid_usd', 18, 2)->nullable();
                $table->decimal('closing_balance_usd', 18, 2)->nullable();
                $table->date('payment_date')->nullable();
                $table->boolean('auto_generated')->default(false)->index();
                $table->text('notes')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['portfolio_account_id', 'statement_month']);
            });
        } else {
            self::addMissing('monthly_statements', [
                'portfolio_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('portfolio_account_id')->nullable(),
                'statement_month' => fn (Blueprint $t) => $t->date('statement_month')->nullable(),
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->nullable(),
                'fx_rate_to_usd' => fn (Blueprint $t) => $t->decimal('fx_rate_to_usd', 18, 8)->nullable(),
                'opening_balance' => fn (Blueprint $t) => $t->decimal('opening_balance', 18, 2)->default(0),
                'contributions' => fn (Blueprint $t) => $t->decimal('contributions', 18, 2)->default(0),
                'withdrawals' => fn (Blueprint $t) => $t->decimal('withdrawals', 18, 2)->default(0),
                'profit_loss' => fn (Blueprint $t) => $t->decimal('profit_loss', 18, 2)->default(0),
                'profit_paid' => fn (Blueprint $t) => $t->decimal('profit_paid', 18, 2)->default(0),
                'closing_balance' => fn (Blueprint $t) => $t->decimal('closing_balance', 18, 2)->default(0),
                'opening_balance_usd' => fn (Blueprint $t) => $t->decimal('opening_balance_usd', 18, 2)->nullable(),
                'contributions_usd' => fn (Blueprint $t) => $t->decimal('contributions_usd', 18, 2)->nullable(),
                'withdrawals_usd' => fn (Blueprint $t) => $t->decimal('withdrawals_usd', 18, 2)->nullable(),
                'profit_loss_usd' => fn (Blueprint $t) => $t->decimal('profit_loss_usd', 18, 2)->nullable(),
                'profit_paid_usd' => fn (Blueprint $t) => $t->decimal('profit_paid_usd', 18, 2)->nullable(),
                'closing_balance_usd' => fn (Blueprint $t) => $t->decimal('closing_balance_usd', 18, 2)->nullable(),
                'payment_date' => fn (Blueprint $t) => $t->date('payment_date')->nullable(),
                'auto_generated' => fn (Blueprint $t) => $t->boolean('auto_generated')->default(false),
                'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
                'pdf_path' => fn (Blueprint $t) => $t->string('pdf_path')->nullable(),
                'published_at' => fn (Blueprint $t) => $t->timestamp('published_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        try {
            if (Schema::hasTable('monthly_statements') && Schema::hasColumn('monthly_statements', 'currency') && Schema::hasColumn('monthly_statements', 'closing_balance_usd')) {
                $usdAccountIds = DB::table('portfolio_accounts')->whereRaw("UPPER(currency) = 'USD'")->pluck('id');
                if ($usdAccountIds->isNotEmpty()) {
                    DB::table('monthly_statements')->whereIn('portfolio_account_id', $usdAccountIds)->whereNull('currency')->update(['currency' => 'USD']);
                    DB::table('monthly_statements')->whereIn('portfolio_account_id', $usdAccountIds)->update([
                        'fx_rate_to_usd' => 1,
                        'opening_balance_usd' => DB::raw('opening_balance'),
                        'contributions_usd' => DB::raw('contributions'),
                        'withdrawals_usd' => DB::raw('withdrawals'),
                        'profit_loss_usd' => DB::raw('profit_loss'),
                        'closing_balance_usd' => DB::raw('closing_balance'),
                    ]);
                }
            }
        } catch (Throwable) {
            // Best-effort native-USD backfill; migrations perform the same normalization.
        }

        if (! Schema::hasTable('portfolio_requests')) {
            Schema::create('portfolio_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('portfolio_account_id')->nullable()->index();
                $table->string('type', 30)->index();
                $table->decimal('amount', 18, 2)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->string('status', 30)->default('submitted')->index();
                $table->text('message')->nullable();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('portfolio_requests', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'portfolio_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('portfolio_account_id')->nullable(),
                'type' => fn (Blueprint $t) => $t->string('type', 30)->default('portfolio_review'),
                'amount' => fn (Blueprint $t) => $t->decimal('amount', 18, 2)->nullable(),
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->default('USD'),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('submitted'),
                'message' => fn (Blueprint $t) => $t->text('message')->nullable(),
                'admin_note' => fn (Blueprint $t) => $t->text('admin_note')->nullable(),
                'processed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('processed_by')->nullable(),
                'processed_at' => fn (Blueprint $t) => $t->timestamp('processed_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('portfolio_investment_terms')) {
            Schema::create('portfolio_investment_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->unique();
                $table->date('effective_from')->index();
                $table->decimal('monthly_target_rate', 8, 4)->default(0);
                $table->string('status', 24)->default('active')->index();
                $table->boolean('auto_payout')->default(true);
                $table->unsignedTinyInteger('payout_day')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('portfolio_investment_terms', [
                'portfolio_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('portfolio_account_id')->nullable(),
                'effective_from' => fn (Blueprint $t) => $t->date('effective_from')->nullable(),
                'monthly_target_rate' => fn (Blueprint $t) => $t->decimal('monthly_target_rate', 8, 4)->default(0),
                'status' => fn (Blueprint $t) => $t->string('status', 24)->default('active'),
                'auto_payout' => fn (Blueprint $t) => $t->boolean('auto_payout')->default(true),
                'payout_day' => fn (Blueprint $t) => $t->unsignedTinyInteger('payout_day')->nullable(),
                'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('portfolio_performance_plans')) {
            Schema::create('portfolio_performance_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->index();
                $table->unsignedBigInteger('investment_term_id')->nullable()->index();
                $table->date('plan_month')->index();
                $table->date('accrual_start_date')->nullable()->index();
                $table->date('accrual_end_date')->nullable();
                $table->decimal('base_amount', 18, 2)->default(0);
                $table->decimal('target_rate', 8, 4)->default(0);
                $table->decimal('target_amount', 18, 2)->default(0);
                $table->string('calculation_basis', 40)->default('calendar_month');
                $table->string('status', 24)->default('active')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('portfolio_performance_plans', [
                'portfolio_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('portfolio_account_id')->nullable(),
                'investment_term_id' => fn (Blueprint $t) => $t->unsignedBigInteger('investment_term_id')->nullable(),
                'plan_month' => fn (Blueprint $t) => $t->date('plan_month')->nullable(),
                'accrual_start_date' => fn (Blueprint $t) => $t->date('accrual_start_date')->nullable(),
                'accrual_end_date' => fn (Blueprint $t) => $t->date('accrual_end_date')->nullable(),
                'base_amount' => fn (Blueprint $t) => $t->decimal('base_amount', 18, 2)->default(0),
                'target_rate' => fn (Blueprint $t) => $t->decimal('target_rate', 8, 4)->default(0),
                'target_amount' => fn (Blueprint $t) => $t->decimal('target_amount', 18, 2)->default(0),
                'calculation_basis' => fn (Blueprint $t) => $t->string('calculation_basis', 40)->default('calendar_month'),
                'status' => fn (Blueprint $t) => $t->string('status', 24)->default('active'),
                'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                'notes' => fn (Blueprint $t) => $t->text('notes')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('portfolio_daily_accruals')) {
            Schema::create('portfolio_daily_accruals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_performance_plan_id')->index();
                $table->date('accrual_date')->index();
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->decimal('planned_amount', 18, 2)->default(0);
                $table->decimal('manual_adjustment', 18, 2)->default(0);
                $table->decimal('posted_amount', 18, 2)->nullable();
                $table->timestamp('posted_at')->nullable()->index();
                $table->unsignedBigInteger('adjusted_by')->nullable()->index();
                $table->text('admin_note')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('portfolio_daily_accruals', [
                'portfolio_performance_plan_id' => fn (Blueprint $t) => $t->unsignedBigInteger('portfolio_performance_plan_id')->nullable(),
                'accrual_date' => fn (Blueprint $t) => $t->date('accrual_date')->nullable(),
                'scheduled_at' => fn (Blueprint $t) => $t->timestamp('scheduled_at')->nullable(),
                'planned_amount' => fn (Blueprint $t) => $t->decimal('planned_amount', 18, 2)->default(0),
                'manual_adjustment' => fn (Blueprint $t) => $t->decimal('manual_adjustment', 18, 2)->default(0),
                'posted_amount' => fn (Blueprint $t) => $t->decimal('posted_amount', 18, 2)->nullable(),
                'posted_at' => fn (Blueprint $t) => $t->timestamp('posted_at')->nullable(),
                'adjusted_by' => fn (Blueprint $t) => $t->unsignedBigInteger('adjusted_by')->nullable(),
                'admin_note' => fn (Blueprint $t) => $t->text('admin_note')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->unsignedBigInteger('notifiable_id');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        } else {
            self::addMissing('notifications', [
                'type' => fn (Blueprint $t) => $t->string('type')->nullable(),
                'notifiable_type' => fn (Blueprint $t) => $t->string('notifiable_type')->nullable(),
                'notifiable_id' => fn (Blueprint $t) => $t->unsignedBigInteger('notifiable_id')->nullable(),
                'data' => fn (Blueprint $t) => $t->text('data')->nullable(),
                'read_at' => fn (Blueprint $t) => $t->timestamp('read_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }
    }

    /** @param array<string, callable(Blueprint): mixed> $definitions */
    private static function addMissing(string $table, array $definitions): void
    {
        $missing = [];
        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn($table, $column)) {
                $missing[$column] = $definition;
            }
        }

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
            foreach ($missing as $definition) {
                $definition($blueprint);
            }
        });
    }


    private static function ensureProductionTables(): void
    {
        if (! Schema::hasTable('email_delivery_logs')) {
            Schema::create('email_delivery_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event', 80)->default('general')->index();
                $table->string('recipient_email');
                $table->string('subject');
                $table->string('status', 30)->default('queued')->index();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('sent_at')->nullable()->index();
                $table->timestamps();
            });
        } else {
            self::addMissing('email_delivery_logs', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'event' => fn (Blueprint $t) => $t->string('event', 80)->default('general'),
                'recipient_email' => fn (Blueprint $t) => $t->string('recipient_email')->nullable(),
                'subject' => fn (Blueprint $t) => $t->string('subject')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('queued'),
                'error_message' => fn (Blueprint $t) => $t->text('error_message')->nullable(),
                'metadata' => fn (Blueprint $t) => $t->json('metadata')->nullable(),
                'sent_at' => fn (Blueprint $t) => $t->timestamp('sent_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('mobile_devices')) {
            Schema::create('mobile_devices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('device_uuid', 190);
                $table->string('platform', 30)->default('unknown')->index();
                $table->string('device_name')->nullable();
                $table->string('app_version', 50)->nullable();
                $table->string('os_version', 80)->nullable();
                $table->text('push_token')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
                $table->unique(['user_id','device_uuid']);
            });
        } else {
            self::addMissing('mobile_devices', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'device_uuid' => fn (Blueprint $t) => $t->string('device_uuid', 190)->nullable(),
                'platform' => fn (Blueprint $t) => $t->string('platform', 30)->default('unknown'),
                'device_name' => fn (Blueprint $t) => $t->string('device_name')->nullable(),
                'app_version' => fn (Blueprint $t) => $t->string('app_version', 50)->nullable(),
                'os_version' => fn (Blueprint $t) => $t->string('os_version', 80)->nullable(),
                'push_token' => fn (Blueprint $t) => $t->text('push_token')->nullable(),
                'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
                'last_seen_at' => fn (Blueprint $t) => $t->timestamp('last_seen_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('email');
                $table->string('subject', 180);
                $table->string('category', 60)->default('general')->index();
                $table->longText('message');
                $table->string('status', 30)->default('new')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->text('admin_notes')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('contact_messages', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'name' => fn (Blueprint $t) => $t->string('name', 120)->nullable(),
                'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
                'subject' => fn (Blueprint $t) => $t->string('subject', 180)->nullable(),
                'category' => fn (Blueprint $t) => $t->string('category', 60)->default('general'),
                'message' => fn (Blueprint $t) => $t->longText('message')->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('new'),
                'priority' => fn (Blueprint $t) => $t->string('priority', 20)->default('normal'),
                'admin_notes' => fn (Blueprint $t) => $t->text('admin_notes')->nullable(),
                'replied_at' => fn (Blueprint $t) => $t->timestamp('replied_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_admin_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('email');
                $table->string('subject', 180)->default('Pulse Support');
                $table->string('category', 50)->default('other')->index();
                $table->string('channel', 20)->default('web')->index();
                $table->string('status', 30)->default('waiting_support')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('last_customer_message_at')->nullable();
                $table->timestamp('last_admin_message_at')->nullable();
                $table->timestamp('assistant_handled_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('support_conversations', [
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'assigned_admin_id' => fn (Blueprint $t) => $t->unsignedBigInteger('assigned_admin_id')->nullable(),
                'name' => fn (Blueprint $t) => $t->string('name', 120)->nullable(),
                'email' => fn (Blueprint $t) => $t->string('email')->nullable(),
                'subject' => fn (Blueprint $t) => $t->string('subject', 180)->default('Pulse Support'),
                'category' => fn (Blueprint $t) => $t->string('category', 50)->default('other'),
                'channel' => fn (Blueprint $t) => $t->string('channel', 20)->default('web'),
                'status' => fn (Blueprint $t) => $t->string('status', 30)->default('waiting_support'),
                'priority' => fn (Blueprint $t) => $t->string('priority', 20)->default('normal'),
                'last_message_at' => fn (Blueprint $t) => $t->timestamp('last_message_at')->nullable(),
                'last_customer_message_at' => fn (Blueprint $t) => $t->timestamp('last_customer_message_at')->nullable(),
                'last_admin_message_at' => fn (Blueprint $t) => $t->timestamp('last_admin_message_at')->nullable(),
                'assistant_handled_at' => fn (Blueprint $t) => $t->timestamp('assistant_handled_at')->nullable(),
                'escalated_at' => fn (Blueprint $t) => $t->timestamp('escalated_at')->nullable(),
                'closed_at' => fn (Blueprint $t) => $t->timestamp('closed_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('support_conversation_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('sender_type', 20)->index();
                $table->string('message_type', 30)->default('text');
                $table->longText('body');
                $table->json('metadata')->nullable();
                $table->timestamp('read_by_customer_at')->nullable();
                $table->timestamp('read_by_admin_at')->nullable();
                $table->timestamps();
            });
        } else {
            self::addMissing('support_messages', [
                'support_conversation_id' => fn (Blueprint $t) => $t->unsignedBigInteger('support_conversation_id')->nullable(),
                'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
                'sender_type' => fn (Blueprint $t) => $t->string('sender_type', 20)->default('system'),
                'message_type' => fn (Blueprint $t) => $t->string('message_type', 30)->default('text'),
                'body' => fn (Blueprint $t) => $t->longText('body')->nullable(),
                'metadata' => fn (Blueprint $t) => $t->json('metadata')->nullable(),
                'read_by_customer_at' => fn (Blueprint $t) => $t->timestamp('read_by_customer_at')->nullable(),
                'read_by_admin_at' => fn (Blueprint $t) => $t->timestamp('read_by_admin_at')->nullable(),
                'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

    }

    private static function widenLegacyEnums(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $columns = [
            'users' => ['role' => "VARCHAR(40) NULL DEFAULT 'user'", 'status' => "VARCHAR(40) NULL DEFAULT 'active'"],
            'products' => ['status' => "VARCHAR(30) NULL DEFAULT 'live'"],
            'news_articles' => ['status' => "VARCHAR(30) NULL DEFAULT 'draft'"],
            'research_reports' => ['risk_level' => "VARCHAR(30) NULL DEFAULT 'not_rated'", 'status' => "VARCHAR(30) NULL DEFAULT 'draft'"],
            'learning_articles' => ['level' => "VARCHAR(30) NULL DEFAULT 'beginner'", 'status' => "VARCHAR(30) NULL DEFAULT 'draft'"],
            'economic_events' => ['impact' => "VARCHAR(30) NULL DEFAULT 'medium'"],
            'community_posts' => ['sentiment' => "VARCHAR(30) NULL DEFAULT 'neutral'", 'status' => "VARCHAR(30) NULL DEFAULT 'published'"],
            'community_comments' => ['status' => "VARCHAR(30) NULL DEFAULT 'published'"],
            'newsletter_subscribers' => ['status' => "VARCHAR(30) NULL DEFAULT 'active'"],
            'portfolio_transactions' => ['type' => "VARCHAR(30) NULL DEFAULT 'adjustment'"],
        ];

        foreach ($columns as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($definitions as $column => $definition) {
                if (Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
                }
            }
        }
    }

    private static function normalizeDefaults(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->whereNull('role')->update(['role' => 'user']);
            DB::table('users')->whereNull('status')->update(['status' => 'active']);
        }
        if (Schema::hasTable('products')) {
            DB::table('products')->whereNull('status')->update(['status' => 'live']);
            DB::table('products')->whereNull('sort_order')->update(['sort_order' => 0]);
            DB::table('products')->whereNull('is_featured')->update(['is_featured' => false]);
        }
    }
}
