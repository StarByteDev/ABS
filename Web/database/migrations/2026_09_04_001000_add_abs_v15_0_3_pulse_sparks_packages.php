<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_plans')) return;

        $all = ['scanner','signals','orders','trades','reports','binance','alerts','plan_view','settings','mobile_api','testnet_trading','manual_trading','live_trading','auto_trading'];
        $intelligence = ['scanner','signals','alerts','plan_view','settings','mobile_api'];
        $flex = [...$intelligence, 'reports'];
        $momentum = [...$flex, 'orders', 'trades', 'binance', 'testnet_trading', 'manual_trading'];
        $capabilities = static fn (array $enabled): string => json_encode(array_replace(array_fill_keys($all, false), array_fill_keys($enabled, true)), JSON_THROW_ON_ERROR);

        $plans = [
            [
                'name' => 'Spark Day Pass', 'slug' => 'spark-day-pass',
                'description' => 'A focused 24-hour pass for checking today’s strongest Admin-qualified market opportunity.',
                'price_points' => 75, 'best_signal_cost_points' => 10, 'access_days' => 1,
                'max_selected_pairs' => 10, 'manual_trades_per_day' => 0, 'auto_trades_per_day' => 0, 'max_open_trades' => 0,
                'allow_testnet_trading' => false, 'allow_manual_trading' => false, 'allow_live_trading' => false, 'allow_auto_trading' => false,
                'capabilities' => $capabilities($intelligence), 'badge' => '24-Hour Access', 'is_featured' => false, 'sort_order' => 10,
            ],
            [
                'name' => 'Spark Flex', 'slug' => 'spark-flex',
                'description' => 'Three days of broader market coverage with signals, alerts and performance reporting.',
                'price_points' => 180, 'best_signal_cost_points' => 8, 'access_days' => 3,
                'max_selected_pairs' => 40, 'manual_trades_per_day' => 0, 'auto_trades_per_day' => 0, 'max_open_trades' => 0,
                'allow_testnet_trading' => false, 'allow_manual_trading' => false, 'allow_live_trading' => false, 'allow_auto_trading' => false,
                'capabilities' => $capabilities($flex), 'badge' => 'Flexible Access', 'is_featured' => false, 'sort_order' => 20,
            ],
            [
                'name' => 'Spark Momentum', 'slug' => 'spark-momentum',
                'description' => 'A complete seven-day trading workflow with wider coverage, reports and protected Practice execution.',
                'price_points' => 380, 'best_signal_cost_points' => 5, 'access_days' => 7,
                'max_selected_pairs' => 150, 'manual_trades_per_day' => 25, 'auto_trades_per_day' => 0, 'max_open_trades' => 3,
                'allow_testnet_trading' => true, 'allow_manual_trading' => true, 'allow_live_trading' => false, 'allow_auto_trading' => false,
                'capabilities' => $capabilities($momentum), 'badge' => 'Most Popular', 'is_featured' => false, 'sort_order' => 30,
            ],
            [
                'name' => 'Spark Professional', 'slug' => 'pulse-professional',
                'description' => 'Thirty days of maximum Pulse coverage, the lowest Best Signal Spark cost and permission-ready trading workflows.',
                'price_points' => 1200, 'best_signal_cost_points' => 3, 'access_days' => 30,
                'max_selected_pairs' => 500, 'manual_trades_per_day' => 50, 'auto_trades_per_day' => 25, 'max_open_trades' => 5,
                'allow_testnet_trading' => true, 'allow_manual_trading' => true, 'allow_live_trading' => true, 'allow_auto_trading' => true,
                'capabilities' => $capabilities($all), 'badge' => 'Best Value', 'is_featured' => true, 'sort_order' => 40,
            ],
        ];

        foreach ($plans as $plan) {
            $plan += [
                'monthly_price' => 0, 'currency' => 'SPARKS', 'minimum_signal_score' => 70,
                'pair_access_mode' => 'all', 'allow_mobile_api' => true,
                'is_active' => true, 'is_trial' => false, 'is_public' => true,
                'request_enabled' => false, 'requires_payment' => false,
                'allow_points_activation' => true,
            ];

            $existing = DB::table('pulse_plans')->where('slug', $plan['slug'])->first();
            if ($existing) {
                DB::table('pulse_plans')->where('id', $existing->id)->update($plan + ['updated_at' => now()]);
            } else {
                DB::table('pulse_plans')->insert($plan + ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        // The old 30-day Intelligence card is replaced by the four duration-based
        // Spark packages. Historical access rows remain intact for audit purposes.
        DB::table('pulse_plans')->where('slug', 'pulse-intelligence')->update([
            'is_public' => false, 'allow_points_activation' => false,
            'request_enabled' => false, 'requires_payment' => false, 'updated_at' => now(),
        ]);

        if (Schema::hasTable('pulse_plan_strategies') && Schema::hasTable('pulse_strategies')) {
            $planIds = DB::table('pulse_plans')->whereIn('slug', collect($plans)->pluck('slug'))->pluck('id');
            $strategyIds = DB::table('pulse_strategies')->where('is_enabled', true)->pluck('id');
            foreach ($planIds as $planId) {
                foreach ($strategyIds as $strategyId) {
                    DB::table('pulse_plan_strategies')->insertOrIgnore([
                        'pulse_plan_id' => $planId, 'pulse_strategy_id' => $strategyId,
                        'is_enabled' => true, 'weight_override' => null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // Keep legacy table/column identifiers for upgrade safety while presenting
        // the economy consistently as Pulse Sparks everywhere customer-facing.
        if (Schema::hasTable('pulse_point_packs')) {
            $bundles = [
                'pulse-starter' => ['name' => 'Spark Starter', 'description' => 'A simple Pulse Sparks wallet top-up.'],
                'pulse-plus' => ['name' => 'Spark Boost', 'description' => 'A flexible Pulse Sparks bundle with bonus Sparks.'],
                'pulse-pro' => ['name' => 'Spark Vault', 'description' => 'The best-value Pulse Sparks bundle with extra bonus Sparks.'],
            ];
            foreach ($bundles as $slug => $values) {
                DB::table('pulse_point_packs')->where('slug', $slug)->update($values + ['updated_at' => now()]);
            }
        }

        if (Schema::hasTable('pulse_system_settings')) {
            $descriptions = [
                'best_signal_points_enabled' => 'Charge the active package Best Signal Spark cost only when a qualifying new signal is unlocked.',
                'point_purchases_enabled' => 'Allow authenticated users to submit Admin-verified USDT purchases for Pulse Sparks bundles.',
                'daily_checkin_points' => 'Pulse Sparks awarded for one daily check-in.',
                'social_share_points' => 'Pulse Sparks awarded once per qualifying signal share.',
                'rewarded_ad_points' => 'Pulse Sparks awarded for one provider-verified rewarded ad.',
                'openai_signal_explanation_points' => 'Optional Spark cost for generating a signal explanation.',
            ];
            foreach ($descriptions as $key => $description) {
                DB::table('pulse_system_settings')->where('key', $key)->update(['description' => $description]);
            }

            DB::table('pulse_system_settings')->updateOrInsert(['key' => 'v1503_spark_packages_seeded'], [
                'value' => '1', 'type' => 'boolean', 'group' => 'system',
                'description' => 'Internal marker: ABS V15.0.3 Pulse Sparks packages were initialized once.',
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: package/access history is retained.
    }
};
