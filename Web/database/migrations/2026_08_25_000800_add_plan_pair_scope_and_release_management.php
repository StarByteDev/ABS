<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pulse_plans') && ! Schema::hasColumn('pulse_plans', 'pair_access_mode')) {
            Schema::table('pulse_plans', function (Blueprint $table) {
                $table->string('pair_access_mode', 16)->default('all')->after('max_selected_pairs');
            });
        }

        if (! Schema::hasTable('pulse_plan_pairs')) {
            Schema::create('pulse_plan_pairs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pulse_plan_id')->constrained('pulse_plans')->cascadeOnDelete();
                $table->foreignId('pulse_pair_id')->constrained('pulse_pairs')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->unique(['pulse_plan_id', 'pulse_pair_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_plan_pairs');
        if (Schema::hasTable('pulse_plans') && Schema::hasColumn('pulse_plans', 'pair_access_mode')) {
            Schema::table('pulse_plans', fn (Blueprint $table) => $table->dropColumn('pair_access_mode'));
        }
    }
};
