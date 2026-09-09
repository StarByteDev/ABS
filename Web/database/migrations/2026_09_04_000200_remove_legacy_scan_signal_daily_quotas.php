<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_plans')) {
            return;
        }

        $drop = [];
        if (Schema::hasColumn('pulse_plans', 'scanner_runs_per_day')) $drop[] = 'scanner_runs_per_day';
        if (Schema::hasColumn('pulse_plans', 'signals_per_day')) $drop[] = 'signals_per_day';

        if ($drop !== []) {
            Schema::table('pulse_plans', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pulse_plans')) {
            return;
        }

        Schema::table('pulse_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('pulse_plans', 'scanner_runs_per_day')) {
                $table->unsignedInteger('scanner_runs_per_day')->default(0);
            }
            if (! Schema::hasColumn('pulse_plans', 'signals_per_day')) {
                $table->unsignedInteger('signals_per_day')->default(0);
            }
        });
    }
};
