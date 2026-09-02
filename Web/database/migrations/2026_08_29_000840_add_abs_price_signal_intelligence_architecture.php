<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_market_prices')) {
            Schema::create('pulse_market_prices', function (Blueprint $table): void {
                $table->id();
                $table->string('symbol', 30)->unique();
                $table->decimal('price', 24, 12);
                $table->decimal('change_percent_24h', 12, 6)->nullable();
                $table->decimal('high_24h', 24, 12)->nullable();
                $table->decimal('low_24h', 24, 12)->nullable();
                $table->decimal('volume_24h', 32, 8)->nullable();
                $table->string('source', 30)->default('binance_futures');
                $table->timestamp('observed_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_market_candles')) {
            Schema::create('pulse_market_candles', function (Blueprint $table): void {
                $table->id();
                $table->string('symbol', 30)->index();
                $table->string('timeframe', 8)->index();
                $table->unsignedBigInteger('open_time_ms');
                $table->unsignedBigInteger('close_time_ms')->nullable();
                $table->decimal('open', 24, 12);
                $table->decimal('high', 24, 12);
                $table->decimal('low', 24, 12);
                $table->decimal('close', 24, 12);
                $table->decimal('volume', 32, 8)->default(0);
                $table->boolean('is_closed')->default(true);
                $table->string('source', 30)->default('binance_futures');
                $table->timestamps();
                $table->unique(['symbol', 'timeframe', 'open_time_ms'], 'pulse_candle_symbol_tf_open_unique');
                $table->index(['timeframe', 'open_time_ms'], 'pulse_candle_tf_open_idx');
            });
        }

        if (! Schema::hasTable('pulse_market_data_runs')) {
            Schema::create('pulse_market_data_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('status', 20)->default('running')->index();
                $table->unsignedInteger('prices_updated')->default(0);
                $table->unsignedInteger('candle_symbols_updated')->default(0);
                $table->unsignedInteger('validation_symbols_updated')->default(0);
                $table->json('summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_signal_validations')) {
            Schema::create('pulse_signal_validations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('signal_id')->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('signal_fingerprint', 64)->index();
                $table->string('symbol', 30)->index();
                $table->string('timeframe', 8)->index();
                $table->string('direction', 12)->index();
                $table->string('strategy_version', 40)->default('1.0')->index();
                $table->json('strategy_snapshot')->nullable();
                $table->decimal('entry_price', 24, 12);
                $table->decimal('stop_loss', 24, 12)->nullable();
                $table->json('take_profit_levels')->nullable();
                $table->decimal('technical_score', 6, 2)->default(0);
                $table->decimal('reliability_score', 6, 2)->nullable();
                $table->decimal('confidence_score', 6, 2)->nullable();
                $table->string('state', 30)->default('waiting_entry')->index();
                $table->string('outcome', 30)->nullable()->index();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('entry_hit_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->decimal('entry_observed_price', 24, 12)->nullable();
                $table->decimal('mfe_price', 24, 12)->nullable();
                $table->decimal('mae_price', 24, 12)->nullable();
                $table->decimal('mfe_r', 12, 6)->nullable();
                $table->decimal('mae_r', 12, 6)->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedTinyInteger('highest_tp_level_hit')->default(0);
                $table->string('market_regime', 30)->nullable()->index();
                $table->json('context')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_signal_daily_metrics')) {
            Schema::create('pulse_signal_daily_metrics', function (Blueprint $table): void {
                $table->id();
                $table->date('metric_date')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('timeframe', 8)->index();
                $table->string('direction', 12)->index();
                $table->unsignedInteger('signals')->default(0);
                $table->unsignedInteger('entries')->default(0);
                $table->unsignedInteger('wins')->default(0);
                $table->unsignedInteger('losses')->default(0);
                $table->unsignedInteger('ambiguous')->default(0);
                $table->unsignedInteger('expired_no_entry')->default(0);
                $table->unsignedInteger('expired_after_entry')->default(0);
                $table->decimal('avg_mfe_r', 12, 6)->nullable();
                $table->decimal('avg_mae_r', 12, 6)->nullable();
                $table->unsignedInteger('avg_duration_seconds')->nullable();
                $table->timestamps();
                $table->unique(['metric_date', 'user_id', 'timeframe', 'direction'], 'pulse_signal_daily_user_tf_dir_unique');
            });
        }

        if (! Schema::hasTable('pulse_strategy_daily_metrics')) {
            Schema::create('pulse_strategy_daily_metrics', function (Blueprint $table): void {
                $table->id();
                $table->date('metric_date')->index();
                $table->string('strategy_slug', 100)->index();
                $table->string('strategy_version', 40)->default('1.0')->index();
                $table->string('timeframe', 8)->index();
                $table->string('direction', 12)->index();
                $table->string('market_regime', 30)->default('ALL')->index();
                $table->unsignedInteger('sample_count')->default(0);
                $table->unsignedInteger('entries')->default(0);
                $table->unsignedInteger('wins')->default(0);
                $table->unsignedInteger('losses')->default(0);
                $table->unsignedInteger('ambiguous')->default(0);
                $table->unsignedInteger('expired_no_entry')->default(0);
                $table->decimal('avg_mfe_r', 12, 6)->nullable();
                $table->decimal('avg_mae_r', 12, 6)->nullable();
                $table->unsignedInteger('avg_duration_seconds')->nullable();
                $table->timestamps();
                $table->unique(['metric_date', 'strategy_slug', 'strategy_version', 'timeframe', 'direction', 'market_regime'], 'pulse_strategy_daily_metric_unique');
            });
        }

        if (! Schema::hasTable('pulse_strategy_learning_states')) {
            Schema::create('pulse_strategy_learning_states', function (Blueprint $table): void {
                $table->id();
                $table->string('strategy_slug', 100)->index();
                $table->string('strategy_version', 40)->default('1.0')->index();
                $table->string('timeframe', 8)->index();
                $table->string('direction', 12)->index();
                $table->string('market_regime', 30)->default('ALL')->index();
                $table->unsignedInteger('sample_size')->default(0);
                $table->decimal('win_rate', 8, 4)->nullable();
                $table->decimal('ambiguous_rate', 8, 4)->nullable();
                $table->decimal('reliability_score', 6, 2)->default(50);
                $table->decimal('recency_weighted_score', 6, 2)->default(50);
                $table->string('evidence_level', 20)->default('insufficient');
                $table->json('meta')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
                $table->unique(['strategy_slug', 'strategy_version', 'timeframe', 'direction', 'market_regime'], 'pulse_strategy_learning_unique');
            });
        }

        if (Schema::hasTable('pulse_strategies') && ! Schema::hasColumn('pulse_strategies', 'version')) {
            Schema::table('pulse_strategies', fn (Blueprint $table) => $table->string('version', 40)->default('1.0')->after('slug'));
        }

        if (Schema::hasTable('pulse_signals')) {
            Schema::table('pulse_signals', function (Blueprint $table): void {
                if (! Schema::hasColumn('pulse_signals', 'signal_fingerprint')) $table->string('signal_fingerprint', 64)->nullable()->index();
                if (! Schema::hasColumn('pulse_signals', 'strategy_version')) $table->string('strategy_version', 40)->default('1.0');
                if (! Schema::hasColumn('pulse_signals', 'strategy_snapshot')) $table->json('strategy_snapshot')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'take_profit_levels')) $table->json('take_profit_levels')->nullable();
                if (! Schema::hasColumn('pulse_signals', 'technical_score')) $table->decimal('technical_score', 6, 2)->nullable();
                if (! Schema::hasColumn('pulse_signals', 'reliability_score')) $table->decimal('reliability_score', 6, 2)->nullable();
                if (! Schema::hasColumn('pulse_signals', 'confidence_score')) $table->decimal('confidence_score', 6, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_strategy_learning_states');
        Schema::dropIfExists('pulse_strategy_daily_metrics');
        Schema::dropIfExists('pulse_signal_daily_metrics');
        Schema::dropIfExists('pulse_signal_validations');
        Schema::dropIfExists('pulse_market_data_runs');
        Schema::dropIfExists('pulse_market_candles');
        Schema::dropIfExists('pulse_market_prices');
    }
};
