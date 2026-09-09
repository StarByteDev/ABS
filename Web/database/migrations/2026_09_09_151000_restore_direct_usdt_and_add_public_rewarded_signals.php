<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the useful signal-tooling columns introduced in earlier V15
        // builds while removing the retired credit/gamification economy.
        if (Schema::hasTable('pulse_scanner_runs') && ! Schema::hasColumn('pulse_scanner_runs', 'best_signal_id')) {
            Schema::table('pulse_scanner_runs', function (Blueprint $table): void {
                $table->unsignedBigInteger('best_signal_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('pulse_signals')) {
            Schema::table('pulse_signals', function (Blueprint $table): void {
                if (! Schema::hasColumn('pulse_signals', 'unlocked_at')) $table->timestamp('unlocked_at')->nullable()->index();
                if (! Schema::hasColumn('pulse_signals', 'ai_explanation')) $table->longText('ai_explanation')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'ai_explained_at')) $table->timestamp('ai_explained_at')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'share_count')) $table->unsignedInteger('share_count')->default(0);
            });
        }

        if (! Schema::hasTable('pulse_public_signal_unlocks')) {
            Schema::create('pulse_public_signal_unlocks', function (Blueprint $table): void {
                $table->id();
                $table->string('visitor_hash', 64)->index();
                $table->string('provider', 40)->default('google_ad_manager')->index();
                $table->string('provider_reference', 190)->unique();
                $table->string('ad_unit', 255)->nullable();
                $table->unsignedBigInteger('signal_id')->nullable()->index();
                $table->json('signal_snapshot');
                $table->string('status', 30)->default('granted')->index();
                $table->timestamp('claimed_at')->nullable()->index();
                $table->timestamp('view_expires_at')->nullable();
                $table->timestamp('next_available_at')->nullable()->index();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('pulse_plans')) {
            $legacyToDirect = [
                'spark-day-pass' => ['slug' => 'pulse-day-pass', 'name' => 'Pulse Day Pass', 'price' => 5.00],
                'spark-flex' => ['slug' => 'pulse-flex', 'name' => 'Pulse Flex', 'price' => 12.00],
                'spark-momentum' => ['slug' => 'pulse-momentum', 'name' => 'Pulse Momentum', 'price' => 25.00],
                'pulse-professional' => ['slug' => 'pulse-professional', 'name' => 'Pulse Professional', 'price' => 79.00],
            ];

            foreach ($legacyToDirect as $oldSlug => $plan) {
                $row = DB::table('pulse_plans')->where('slug', $oldSlug)->first();
                if (! $row && $oldSlug !== $plan['slug']) continue;
                $updates = [
                    'slug' => $plan['slug'],
                    'name' => $plan['name'],
                    'monthly_price' => ((float) ($row?->monthly_price ?? 0)) > 0 ? $row->monthly_price : $plan['price'],
                    'currency' => 'USDT',
                    'request_enabled' => 1,
                    'requires_payment' => 1,
                    'updated_at' => now(),
                ];
                DB::table('pulse_plans')->where('slug', $oldSlug)->update($updates);
            }

            DB::table('pulse_plans')->where('is_trial', 0)->update([
                'currency' => 'USDT',
                'request_enabled' => 1,
                'requires_payment' => 1,
                'updated_at' => now(),
            ]);
            DB::table('pulse_plans')->where('is_trial', 1)->update([
                'currency' => 'USDT',
                'request_enabled' => 0,
                'requires_payment' => 0,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('pulse_system_settings')) {
            $retiredKeys = [
                'point_purchases_enabled', 'best_signal_points_enabled', 'rewarded_ads_enabled',
                'rewarded_ad_points', 'rewarded_ad_xp', 'daily_checkin_points',
                'social_share_points', 'openai_signal_explanation_points',
                'v15_points_defaults_seeded', 'v1503_spark_packages_seeded',
                'rewarded_ads_test_mode', 'rewarded_ads_android_unit_id', 'rewarded_ads_ios_unit_id',
                'rewarded_ads_web_unit_path', 'rewarded_ads_daily_limit', 'rewarded_ads_cooldown_minutes',
            ];
            DB::table('pulse_system_settings')->whereIn('key', $retiredKeys)->delete();

            $settings = [
                ['membership_requests_enabled', '1', 'boolean', 'membership', 'Allow registered members to submit direct USDT package payments for Admin verification.'],
                ['promotion_codes_enabled', '0', 'boolean', 'membership', 'Optional direct-package promotions; disabled by default.'],
                ['public_rewarded_signals_enabled', '1', 'boolean', 'public_signal', 'Allow visitors without registration to watch a rewarded ad and reveal one random qualified Pulse signal.'],
                ['public_rewarded_signal_cooldown_minutes', '30', 'integer', 'public_signal', 'Minutes a browser must wait after a successful rewarded signal unlock.'],
                ['public_rewarded_signal_view_seconds', '30', 'integer', 'public_signal', 'Seconds a free rewarded signal remains visible after reward grant.'],
                ['public_rewarded_signal_min_claim_seconds', '5', 'integer', 'public_signal', 'Minimum time between issuing a web reward session and accepting its browser claim.'],
                ['rewarded_web_test_mode', '1', 'boolean', 'public_signal', 'Use Google rewarded-web test inventory until a production unit is configured.'],
                ['rewarded_web_ad_unit_input', '', 'text', 'public_signal', 'Google Ad Manager rewarded ad unit path or copied GPT snippet. ABS extracts only the ad unit path.'],
                ['rewarded_web_ad_unit_path', '', 'string', 'public_signal', 'Resolved Google Ad Manager rewarded-web ad unit path.'],
                ['public_rewarded_signal_badge', 'FREE SIGNAL · REWARDED ACCESS', 'string', 'public_signal', 'Badge displayed on the public rewarded-signal gateway.'],
                ['public_rewarded_signal_title', 'Watch one ad. Unlock one qualified Pulse signal.', 'string', 'public_signal', 'Headline displayed above the rewarded-ad opt-in.'],
                ['public_rewarded_signal_description', 'No registration required. Complete the rewarded ad and your signal appears for 30 seconds.', 'text', 'public_signal', 'Description displayed on the rewarded-ad opt-in.'],
                ['public_rewarded_signal_cta', 'Watch Ad & Reveal Signal', 'string', 'public_signal', 'Primary rewarded-ad call-to-action label.'],
            ];
            foreach ($settings as [$key, $value, $type, $group, $description]) {
                DB::table('pulse_system_settings')->updateOrInsert(['key' => $key], compact('value', 'type', 'group', 'description'));
            }
        }

        // Retire the previous wallet/gamification/reward tables. Membership and
        // payment history are not stored in these tables and are preserved.
        foreach ([
            'pulse_rewarded_ad_receipts', 'pulse_reward_claims',
            'pulse_user_achievements', 'pulse_achievements',
            'pulse_user_missions', 'pulse_missions',
            'pulse_point_purchases', 'pulse_point_ledger',
            'pulse_point_wallets', 'pulse_point_packs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('pulse_plans')) {
            $columns = array_values(array_filter(
                ['price_points', 'best_signal_cost_points', 'allow_points_activation'],
                fn (string $column): bool => Schema::hasColumn('pulse_plans', $column),
            ));
            if ($columns !== []) Schema::table('pulse_plans', fn (Blueprint $table) => $table->dropColumn($columns));
        }
        if (Schema::hasTable('pulse_scanner_runs') && Schema::hasColumn('pulse_scanner_runs', 'points_charged')) {
            Schema::table('pulse_scanner_runs', fn (Blueprint $table) => $table->dropColumn('points_charged'));
        }
        if (Schema::hasTable('pulse_signals') && Schema::hasColumn('pulse_signals', 'points_cost')) {
            Schema::table('pulse_signals', fn (Blueprint $table) => $table->dropColumn('points_cost'));
        }
        if (Schema::hasTable('users')) {
            $columns = array_values(array_filter(
                ['pulse_xp', 'pulse_level', 'pulse_streak_days', 'pulse_longest_streak', 'pulse_last_checkin_date'],
                fn (string $column): bool => Schema::hasColumn('users', $column),
            ));
            if ($columns !== []) Schema::table('users', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive. Direct-USDT payment history and anonymous
        // rewarded-signal audit rows are retained on rollback.
    }
};
