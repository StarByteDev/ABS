<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portfolio_investment_terms')) {
            Schema::create('portfolio_investment_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->unique();
                $table->date('effective_from')->index();
                $table->decimal('monthly_target_rate', 8, 4)->default(0);
                $table->string('status', 24)->default('active')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('portfolio_performance_plans')) {
            // Resolve existing columns before entering the schema callback. This is safer
            // across shared-hosting/MySQL combinations than introspecting from inside it.
            $needsStart = ! Schema::hasColumn('portfolio_performance_plans', 'accrual_start_date');
            $needsEnd = ! Schema::hasColumn('portfolio_performance_plans', 'accrual_end_date');
            $needsBasis = ! Schema::hasColumn('portfolio_performance_plans', 'calculation_basis');
            $needsTerm = ! Schema::hasColumn('portfolio_performance_plans', 'investment_term_id');

            if ($needsStart || $needsEnd || $needsBasis || $needsTerm) {
                Schema::table('portfolio_performance_plans', function (Blueprint $table) use ($needsStart, $needsEnd, $needsBasis, $needsTerm): void {
                    if ($needsStart) {
                        $table->date('accrual_start_date')->nullable()->after('plan_month')->index();
                    }
                    if ($needsEnd) {
                        $table->date('accrual_end_date')->nullable()->after('accrual_start_date');
                    }
                    if ($needsBasis) {
                        $table->string('calculation_basis', 40)->default('calendar_month')->after('target_amount');
                    }
                    if ($needsTerm) {
                        $table->unsignedBigInteger('investment_term_id')->nullable()->after('portfolio_account_id')->index();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Production-safe by design. Investment agreement and performance history are retained.
    }
};
