<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pulse push notifications: an outbox/audit table (one row per push, unique
 * dedupe key) plus Admin-editable controls in pulse_system_settings.
 * Additive and data-preserving; existing setting values are never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_notification_logs')) {
            Schema::create('push_notification_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('dedupe_key', 191)->nullable()->unique();
                $table->string('channel', 16); // topic | user
                $table->string('target', 191); // topic name or user id
                $table->string('type', 64)->index();
                $table->string('subject', 191)->nullable()->index(); // e.g. BTCUSDT
                $table->string('title', 191);
                $table->text('body');
                $table->json('data')->nullable();
                $table->string('status', 16)->default('queued')->index(); // queued|sent|skipped|failed
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedSmallInteger('delivered')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['type', 'created_at']);
            });
        }

        if (! Schema::hasTable('pulse_system_settings')) return;

        foreach (self::defaults() as [$key, $value, $type, $description]) {
            if (! DB::table('pulse_system_settings')->where('key', $key)->exists()) {
                DB::table('pulse_system_settings')->insert([
                    'key' => $key, 'value' => $value, 'type' => $type,
                    'group' => 'push_notifications', 'description' => $description,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: the push audit trail and Admin
        // notification controls are retained on rollback.
    }

    /** @return list<array{0:string,1:string,2:string,3:string}> */
    public static function defaults(): array
    {
        return [
            ['notifications_enabled', 'true', 'boolean', 'Master switch for all Pulse push notifications.'],
            ['market_fast_move_enabled', 'true', 'boolean', 'Rapid BTC/ETH price-move alerts (topic abs_market_alerts).'],
            ['market_fast_move_default_percent', '3.0', 'float', 'Fallback absolute % move threshold.'],
            ['market_fast_move_window_minutes', '5', 'integer', 'Price-move detection window in minutes.'],
            ['market_fast_move_cooldown_minutes', '30', 'integer', 'Per-symbol cooldown between fast-move alerts.'],
            ['btc_fast_move_enabled', 'true', 'boolean', 'BTCUSDT fast-move alerts.'],
            ['btc_fast_move_percent', '3.0', 'float', 'BTCUSDT absolute % move threshold.'],
            ['eth_fast_move_enabled', 'true', 'boolean', 'ETHUSDT fast-move alerts.'],
            ['eth_fast_move_percent', '4.0', 'float', 'ETHUSDT absolute % move threshold.'],
            ['max_market_alerts_per_hour', '3', 'integer', 'Global cap on market-move pushes per hour.'],
            ['breaking_news_enabled', 'true', 'boolean', 'Push featured/breaking ABS News articles when published.'],
            ['qualified_signal_notifications_enabled', 'true', 'boolean', 'Private device push when a member receives a qualified Pulse signal.'],
            ['entry_watch_notifications_enabled', 'false', 'boolean', 'Reserved: no persisted Entry Watch event source exists yet.'],
            ['macro_alerts_enabled', 'true', 'boolean', 'High-impact economic-calendar alerts before the event.'],
            ['macro_alert_lead_minutes', '30', 'integer', 'Minutes before a high-impact event to alert.'],
            ['daily_brief_enabled', 'true', 'boolean', 'Daily Pulse market brief push (after 08:15 server time).'],
            ['free_signal_ready_notifications_enabled', 'false', 'boolean', 'Reserved: Free Signal cooldowns are per-visitor with no device mapping.'],
            ['package_expiry_notifications_enabled', 'true', 'boolean', 'Private device push for Pulse package expiry reminders.'],
        ];
    }
};
