<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portfolio_performance_plans')) {
            Schema::create('portfolio_performance_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->index();
                $table->date('plan_month')->index();
                $table->decimal('base_amount', 18, 2)->default(0);
                $table->decimal('target_rate', 8, 4)->default(0);
                $table->decimal('target_amount', 18, 2)->default(0);
                $table->string('status', 24)->default('active')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['portfolio_account_id', 'plan_month'], 'portfolio_performance_plan_month_unique');
            });
        }

        if (! Schema::hasTable('portfolio_daily_accruals')) {
            Schema::create('portfolio_daily_accruals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_performance_plan_id')->index();
                $table->date('accrual_date')->index();
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->decimal('planned_amount', 18, 2)->default(0);
                $table->decimal('manual_adjustment', 18, 2)->default(0);
                $table->decimal('posted_amount', 18, 2)->nullable();
                $table->timestamp('posted_at')->nullable()->index();
                $table->unsignedBigInteger('adjusted_by')->nullable()->index();
                $table->text('admin_note')->nullable();
                $table->timestamps();
                $table->unique(['portfolio_performance_plan_id', 'accrual_date'], 'portfolio_daily_accrual_unique');
            });
        }
    }

    public function down(): void
    {
        // Production-safe by design. Investor performance records are retained.
    }
};
