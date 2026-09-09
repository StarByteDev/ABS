<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'pulse_xp')) $table->unsignedBigInteger('pulse_xp')->default(0);
                if (! Schema::hasColumn('users', 'pulse_level')) $table->unsignedInteger('pulse_level')->default(1);
                if (! Schema::hasColumn('users', 'pulse_streak_days')) $table->unsignedInteger('pulse_streak_days')->default(0);
                if (! Schema::hasColumn('users', 'pulse_longest_streak')) $table->unsignedInteger('pulse_longest_streak')->default(0);
                if (! Schema::hasColumn('users', 'pulse_last_checkin_date')) $table->date('pulse_last_checkin_date')->nullable();
            });
        }

        if (Schema::hasTable('pulse_plans')) {
            Schema::table('pulse_plans', function (Blueprint $table): void {
                if (! Schema::hasColumn('pulse_plans', 'price_points')) $table->unsignedInteger('price_points')->default(0);
                if (! Schema::hasColumn('pulse_plans', 'best_signal_cost_points')) $table->unsignedInteger('best_signal_cost_points')->default(0);
                if (! Schema::hasColumn('pulse_plans', 'allow_points_activation')) $table->boolean('allow_points_activation')->default(false);
            });
        }

        if (Schema::hasTable('pulse_scanner_runs')) {
            Schema::table('pulse_scanner_runs', function (Blueprint $table): void {
                if (! Schema::hasColumn('pulse_scanner_runs', 'best_signal_id')) $table->unsignedBigInteger('best_signal_id')->nullable();
                if (! Schema::hasColumn('pulse_scanner_runs', 'points_charged')) $table->unsignedInteger('points_charged')->default(0);
            });
        }

        if (Schema::hasTable('pulse_signals')) {
            Schema::table('pulse_signals', function (Blueprint $table): void {
                if (! Schema::hasColumn('pulse_signals', 'points_cost')) $table->unsignedInteger('points_cost')->default(0);
                if (! Schema::hasColumn('pulse_signals', 'unlocked_at')) $table->timestamp('unlocked_at')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'ai_explanation')) $table->longText('ai_explanation')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'ai_explained_at')) $table->timestamp('ai_explained_at')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'share_count')) $table->unsignedInteger('share_count')->default(0);
            });
        }

        if (! Schema::hasTable('pulse_point_wallets')) {
            Schema::create('pulse_point_wallets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->bigInteger('balance')->default(0);
                $table->unsignedBigInteger('lifetime_earned')->default(0);
                $table->unsignedBigInteger('lifetime_spent')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_point_ledger')) {
            Schema::create('pulse_point_ledger', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->bigInteger('amount');
                $table->bigInteger('balance_after');
                $table->string('type', 40)->index();
                $table->string('source', 80)->index();
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('idempotency_key', 190)->unique();
                $table->string('description', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (! Schema::hasTable('pulse_point_packs')) {
            Schema::create('pulse_point_packs', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('points');
                $table->unsignedInteger('bonus_points')->default(0);
                $table->decimal('price_usdt', 12, 2);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_point_purchases')) {
            Schema::create('pulse_point_purchases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pulse_point_pack_id')->nullable()->constrained('pulse_point_packs')->nullOnDelete();
                $table->string('status', 30)->default('submitted')->index();
                $table->unsignedInteger('points_snapshot');
                $table->unsignedInteger('bonus_points_snapshot')->default(0);
                $table->decimal('amount_usdt', 12, 2);
                $table->string('network', 80)->nullable();
                $table->string('wallet_address_snapshot', 500)->nullable();
                $table->string('payment_reference', 190)->nullable()->unique();
                $table->string('payment_proof_path', 500)->nullable();
                $table->text('user_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('credited_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('pulse_missions')) {
            Schema::create('pulse_missions', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->text('description')->nullable();
                $table->string('event_key', 80)->index();
                $table->string('period', 20)->default('daily');
                $table->unsignedInteger('target_count')->default(1);
                $table->unsignedInteger('reward_points')->default(0);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_user_missions')) {
            Schema::create('pulse_user_missions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pulse_mission_id')->constrained('pulse_missions')->cascadeOnDelete();
                $table->string('period_key', 40);
                $table->unsignedInteger('progress')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'pulse_mission_id', 'period_key'], 'pulse_user_mission_period_unique');
            });
        }

        if (! Schema::hasTable('pulse_achievements')) {
            Schema::create('pulse_achievements', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 120)->unique();
                $table->text('description')->nullable();
                $table->string('metric_key', 80)->index();
                $table->unsignedBigInteger('target_value')->default(1);
                $table->unsignedInteger('reward_points')->default(0);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_user_achievements')) {
            Schema::create('pulse_user_achievements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pulse_achievement_id')->constrained('pulse_achievements')->cascadeOnDelete();
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'pulse_achievement_id']);
            });
        }

        if (! Schema::hasTable('pulse_reward_claims')) {
            Schema::create('pulse_reward_claims', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('reward_type', 60)->index();
                $table->string('reward_key', 190);
                $table->unsignedInteger('points')->default(0);
                $table->unsignedInteger('xp')->default(0);
                $table->string('provider_reference', 190)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('claimed_at')->useCurrent();
                $table->unique(['user_id', 'reward_type', 'reward_key'], 'pulse_reward_claim_unique');
            });
        }

        $settings = [
            ['best_signal_points_enabled', '1', 'boolean', 'points', 'Charge the active plan Best Signal PP cost only when a qualifying signal is unlocked.'],
            ['point_purchases_enabled', '1', 'boolean', 'points', 'Allow authenticated users to submit Admin-verified USDT purchases for Pulse Points packs.'],
            ['daily_checkin_points', '5', 'integer', 'gamification', 'Pulse Points awarded for one daily check-in.'],
            ['daily_checkin_xp', '10', 'integer', 'gamification', 'XP awarded for one daily check-in.'],
            ['social_share_points', '3', 'integer', 'gamification', 'Pulse Points awarded once per qualifying signal share.'],
            ['social_share_xp', '5', 'integer', 'gamification', 'XP awarded once per qualifying signal share.'],
            ['rewarded_ads_enabled', '0', 'boolean', 'gamification', 'Rewarded-ad points remain disabled until a server-verified provider callback is configured.'],
            ['rewarded_ad_points', '5', 'integer', 'gamification', 'Pulse Points awarded for one provider-verified rewarded ad.'],
            ['rewarded_ad_xp', '5', 'integer', 'gamification', 'XP awarded for one provider-verified rewarded ad.'],
            ['openai_signal_explanations_enabled', '0', 'boolean', 'ai', 'Allow optional OpenAI-generated plain-language signal explanations.'],
            ['openai_signal_explanation_points', '0', 'integer', 'ai', 'Optional PP cost for generating a signal explanation.'],
        ];

        $seedPlanDefaults = false;
        if (Schema::hasTable('pulse_system_settings')) {
            $seedPlanDefaults = ! DB::table('pulse_system_settings')->where('key', 'v15_points_defaults_seeded')->exists();
            foreach ($settings as [$key, $value, $type, $group, $description]) {
                DB::table('pulse_system_settings')->insertOrIgnore([
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'group' => $group,
                    'description' => $description,
                ]);
            }
        }

        // Existing administrators may later intentionally set a PP price to zero
        // or disable PP plan activation. Seed these defaults once only; protected
        // schema repair must never reset an administrator's later choice.
        if ($seedPlanDefaults && Schema::hasTable('pulse_plans')) {
            $planDefaults = [
                'pulse-intelligence' => ['price_points' => 500, 'best_signal_cost_points' => 10, 'allow_points_activation' => true],
                'pulse-professional' => ['price_points' => 1200, 'best_signal_cost_points' => 5, 'allow_points_activation' => true],
            ];
            foreach ($planDefaults as $slug => $defaults) {
                DB::table('pulse_plans')->where('slug', $slug)->update($defaults);
            }
        }

        if (Schema::hasTable('pulse_point_packs')) {
            $packs = [
                ['name' => 'Pulse Starter', 'slug' => 'pulse-starter', 'description' => 'Entry Pulse Points pack.', 'points' => 100, 'bonus_points' => 0, 'price_usdt' => 10, 'sort_order' => 10],
                ['name' => 'Pulse Plus', 'slug' => 'pulse-plus', 'description' => 'Mid-size Pulse Points pack with bonus PP.', 'points' => 500, 'bonus_points' => 50, 'price_usdt' => 45, 'sort_order' => 20],
                ['name' => 'Pulse Pro', 'slug' => 'pulse-pro', 'description' => 'Large Pulse Points pack with bonus PP.', 'points' => 1200, 'bonus_points' => 200, 'price_usdt' => 99, 'sort_order' => 30],
            ];
            foreach ($packs as $pack) {
                DB::table('pulse_point_packs')->insertOrIgnore(array_merge($pack, ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]));
            }
        }

        if (Schema::hasTable('pulse_missions')) {
            $missions = [
                ['name' => 'Daily Pulse', 'slug' => 'daily-checkin', 'description' => 'Check in to Pulse once today.', 'event_key' => 'daily_checkin', 'period' => 'daily', 'target_count' => 1, 'reward_points' => 0, 'reward_xp' => 10, 'sort_order' => 10],
                ['name' => 'Signal Hunter', 'slug' => 'find-best-signal', 'description' => 'Find one qualified Best Signal.', 'event_key' => 'best_signal_unlocked', 'period' => 'daily', 'target_count' => 1, 'reward_points' => 2, 'reward_xp' => 15, 'sort_order' => 20],
                ['name' => 'Market Routine', 'slug' => 'three-best-signals-week', 'description' => 'Unlock three Best Signals this week.', 'event_key' => 'best_signal_unlocked', 'period' => 'weekly', 'target_count' => 3, 'reward_points' => 5, 'reward_xp' => 30, 'sort_order' => 30],
            ];
            foreach ($missions as $mission) {
                DB::table('pulse_missions')->insertOrIgnore(array_merge($mission, ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]));
            }
        }

        if (Schema::hasTable('pulse_achievements')) {
            $achievements = [
                ['name' => 'First Pulse', 'slug' => 'first-best-signal', 'description' => 'Unlock your first qualified Best Signal.', 'metric_key' => 'best_signals', 'target_value' => 1, 'reward_points' => 5, 'reward_xp' => 25, 'sort_order' => 10],
                ['name' => 'Pulse Regular', 'slug' => 'seven-day-streak', 'description' => 'Reach a 7-day Pulse check-in streak.', 'metric_key' => 'streak_days', 'target_value' => 7, 'reward_points' => 10, 'reward_xp' => 75, 'sort_order' => 20],
                ['name' => 'Signal Explorer', 'slug' => 'twenty-five-best-signals', 'description' => 'Unlock 25 qualified Best Signals.', 'metric_key' => 'best_signals', 'target_value' => 25, 'reward_points' => 25, 'reward_xp' => 150, 'sort_order' => 30],
            ];
            foreach ($achievements as $achievement) {
                DB::table('pulse_achievements')->insertOrIgnore(array_merge($achievement, ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]));
            }
        }

        if ($seedPlanDefaults && Schema::hasTable('pulse_system_settings')) {
            DB::table('pulse_system_settings')->insertOrIgnore([
                'key' => 'v15_points_defaults_seeded',
                'value' => now()->toIso8601String(),
                'type' => 'string',
                'group' => 'system',
                'description' => 'Internal marker: ABS V15 Pulse Points commercial defaults were initialized once.',
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. ABS production upgrades retain PP ledgers,
        // purchases, rewards, progression and signal unlock history on rollback.
    }
};
