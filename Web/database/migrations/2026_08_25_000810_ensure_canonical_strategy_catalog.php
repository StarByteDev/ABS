<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_strategies')) {
            return;
        }

        $catalog = [
            ['Trend Alignment', 'trend-alignment', 'Checks whether price, EMA20 and EMA50 agree on a directional trend.', 1],
            ['EMA Crossover', 'ema-crossover', 'Compares EMA9 with EMA20 to identify short-term momentum alignment.', 2],
            ['RSI Recovery', 'rsi-recovery', 'Uses RSI recovery zones to avoid treating extreme readings as automatic entries.', 3],
            ['MACD Momentum', 'macd-momentum', 'Compares MACD with its signal line for directional momentum confirmation.', 4],
            ['Breakout Confirmation', 'breakout-confirmation', 'Looks for a close beyond the previous twenty-candle trading range.', 5],
            ['Volume Expansion', 'volume-expansion', 'Requires materially higher volume before awarding participation points.', 6],
            ['ATR Volatility Filter', 'atr-volatility', 'Keeps the setup inside a configurable volatility operating range.', 7],
            ['Market Structure', 'market-structure', 'Reviews recent highs and lows for directional structure.', 8],
            ['Trend Pullback', 'trend-pullback', 'Looks for price holding near EMA20 while the broader EMA trend remains aligned.', 9],
            ['Bollinger Reversion', 'bollinger-reversion', 'Identifies controlled mean-reversion conditions using Bollinger bands and RSI.', 10],
            ['Momentum Continuation', 'momentum-continuation', 'Checks five-candle momentum in the direction of the EMA20 trend.', 11],
            ['Range Compression', 'range-compression', 'Detects compressed Bollinger width that can precede directional expansion.', 12],
            ['Candle Strength', 'candle-strength', 'Measures whether the latest candle body is decisive relative to its full range.', 13],
            ['Swing Sequence', 'swing-sequence', 'Compares recent closing sequences for consistent directional progression.', 14],
            ['Risk/Reward Quality', 'risk-reward-quality', 'Confirms that ATR supports calculable stop-loss and take-profit distances.', 15],
        ];

        $now = now();
        foreach ($catalog as [$name, $slug, $description, $sortOrder]) {
            if (! DB::table('pulse_strategies')->where('slug', $slug)->exists()) {
                DB::table('pulse_strategies')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'timeframe' => '15m',
                    'weight' => 1.00,
                    'minimum_score' => 0,
                    'settings' => json_encode([]),
                    'is_enabled' => true,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Legacy upgraded plans occasionally have no pivot rows at all. In that
        // case attach the complete reviewed catalog once; administrators can then
        // reduce the included strategy set per package from Pulse Plan management.
        if (Schema::hasTable('pulse_plans') && Schema::hasTable('pulse_plan_strategies')) {
            $strategyIds = DB::table('pulse_strategies')
                ->whereIn('slug', array_column($catalog, 1))
                ->pluck('id')
                ->all();

            foreach (DB::table('pulse_plans')->pluck('id') as $planId) {
                if (DB::table('pulse_plan_strategies')->where('pulse_plan_id', $planId)->exists()) {
                    continue;
                }
                foreach ($strategyIds as $strategyId) {
                    DB::table('pulse_plan_strategies')->insert([
                        'pulse_plan_id' => $planId,
                        'pulse_strategy_id' => $strategyId,
                        'is_enabled' => true,
                        'weight_override' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: strategy history and plan assignments are production data.
    }
};
