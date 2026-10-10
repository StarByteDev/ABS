<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portfolio_investment_rate_periods')) {
            Schema::create('portfolio_investment_rate_periods', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('portfolio_account_id')->index();
                $table->date('effective_from')->index();
                $table->decimal('monthly_target_rate', 8, 4)->default(0);
                $table->string('profit_disposition',24)->default('payout');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['portfolio_account_id','effective_from'], 'portfolio_rate_period_unique');
            });
        }

        if (Schema::hasTable('portfolio_investment_terms')) {
            $needsProfitDisposition = ! Schema::hasColumn('portfolio_investment_terms','profit_disposition');
            if ($needsProfitDisposition) {
                Schema::table('portfolio_investment_terms', function (Blueprint $table): void {
                    $table->string('profit_disposition',24)->default('payout')->index();
                });
            }
            // Seed one historical rate row from every existing agreement without rewriting history.
            DB::table('portfolio_investment_terms')->orderBy('id')->chunkById(100, function ($terms): void {
                foreach ($terms as $term) {
                    if (! $term->effective_from) continue;
                    DB::table('portfolio_investment_rate_periods')->updateOrInsert(
                        ['portfolio_account_id'=>$term->portfolio_account_id,'effective_from'=>$term->effective_from],
                        ['monthly_target_rate'=>$term->monthly_target_rate,'profit_disposition'=>$term->profit_disposition ?? 'payout','created_by'=>$term->created_by,'notes'=>'Imported from existing V15.7/V15.8 investment agreement.','updated_at'=>now(),'created_at'=>now()]
                    );
                }
            });
        }


        if (Schema::hasTable('portfolio_performance_plans')) {
            $needsBaseUsd = ! Schema::hasColumn('portfolio_performance_plans','base_amount_usd');
            $needsTargetUsd = ! Schema::hasColumn('portfolio_performance_plans','target_amount_usd');
            if ($needsBaseUsd || $needsTargetUsd) {
                Schema::table('portfolio_performance_plans', function (Blueprint $table) use ($needsBaseUsd, $needsTargetUsd): void {
                    if ($needsBaseUsd) $table->decimal('base_amount_usd',18,2)->nullable();
                    if ($needsTargetUsd) $table->decimal('target_amount_usd',18,2)->nullable();
                });
            }
        }

        if (Schema::hasTable('monthly_statements')) {
            $needsProfitReinvested = ! Schema::hasColumn('monthly_statements','profit_reinvested');
            $needsProfitReinvestedUsd = ! Schema::hasColumn('monthly_statements','profit_reinvested_usd');
            if ($needsProfitReinvested || $needsProfitReinvestedUsd) {
                Schema::table('monthly_statements', function (Blueprint $table) use ($needsProfitReinvested, $needsProfitReinvestedUsd): void {
                    if ($needsProfitReinvested) $table->decimal('profit_reinvested',18,2)->default(0);
                    if ($needsProfitReinvestedUsd) $table->decimal('profit_reinvested_usd',18,2)->nullable();
                });
            }
        }

        if (! Schema::hasTable('admin_trading_ledger_entries')) {
            Schema::create('admin_trading_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->date('entry_date')->index();
                $table->string('type',32)->index(); // opening, deposit, withdrawal, pnl, fee, adjustment
                $table->decimal('amount',20,2);
                $table->string('currency',10)->default('USD');
                $table->string('exchange',80)->nullable()->index();
                $table->string('reference',190)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamp('voided_at')->nullable()->index();
                $table->unsignedBigInteger('voided_by')->nullable()->index();
                $table->text('void_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_trading_month_targets')) {
            Schema::create('admin_trading_month_targets', function (Blueprint $table): void {
                $table->id();
                $table->date('target_month')->unique();
                $table->decimal('target_amount',20,2)->default(0);
                $table->string('currency',10)->default('USD');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Production-safe rollback: financial history is intentionally retained.
    }
};
