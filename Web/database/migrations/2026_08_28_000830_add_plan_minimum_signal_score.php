<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pulse_plans') && ! Schema::hasColumn('pulse_plans', 'minimum_signal_score')) {
            Schema::table('pulse_plans', function (Blueprint $table): void {
                $table->decimal('minimum_signal_score', 6, 2)->default(70)->after('signals_per_day');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pulse_plans') && Schema::hasColumn('pulse_plans', 'minimum_signal_score')) {
            Schema::table('pulse_plans', fn (Blueprint $table) => $table->dropColumn('minimum_signal_score'));
        }
    }
};
