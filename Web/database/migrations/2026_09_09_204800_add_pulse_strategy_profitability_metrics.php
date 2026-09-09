<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pulse_signal_daily_metrics', 'pulse_strategy_daily_metrics'] as $table) {
            if (! Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table): void {
                if (! Schema::hasColumn($table, 'model_trades')) $t->unsignedInteger('model_trades')->default(0);
                if (! Schema::hasColumn($table, 'model_net_r')) $t->decimal('model_net_r', 16, 6)->default(0);
                if (! Schema::hasColumn($table, 'model_gross_profit_r')) $t->decimal('model_gross_profit_r', 16, 6)->default(0);
                if (! Schema::hasColumn($table, 'model_gross_loss_r')) $t->decimal('model_gross_loss_r', 16, 6)->default(0);
                if (! Schema::hasColumn($table, 'model_return_pct')) $t->decimal('model_return_pct', 18, 8)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['pulse_signal_daily_metrics', 'pulse_strategy_daily_metrics'] as $table) {
            if (! Schema::hasTable($table)) continue;
            $columns = collect(['model_trades','model_net_r','model_gross_profit_r','model_gross_loss_r','model_return_pct'])
                ->filter(fn ($column) => Schema::hasColumn($table, $column))->all();
            if ($columns !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
            }
        }
    }
};
