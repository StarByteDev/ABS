<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portfolio_investment_terms')) {
            Schema::table('portfolio_investment_terms', function (Blueprint $table): void {
                if (! Schema::hasColumn('portfolio_investment_terms', 'auto_payout')) $table->boolean('auto_payout')->default(true);
                if (! Schema::hasColumn('portfolio_investment_terms', 'payout_day')) $table->unsignedTinyInteger('payout_day')->nullable();
            });
        }

        if (Schema::hasTable('portfolio_transactions')) {
            Schema::table('portfolio_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('portfolio_transactions', 'entry_source')) $table->string('entry_source', 40)->default('admin_manual')->index();
                if (! Schema::hasColumn('portfolio_transactions', 'performance_month')) $table->date('performance_month')->nullable()->index();
            });

            // V15.7.4 accounting correction: "profit" is money paid to the investor, not retained capital.
            // Preserve the transaction and profit history; only remove the old balance-increase side effect.
            DB::table('portfolio_transactions')->where('type', 'profit')->update([
                'current_value_effect' => 0,
                'usd_current_value_effect' => 0,
            ]);

            DB::table('portfolio_transactions')->whereNull('entry_source')->orWhere('entry_source', '')->update(['entry_source' => 'admin_manual']);

            // Historical mapping: payouts posted in the first five days are treated as the prior
            // month's performance payment; month-end/other entries stay with their transaction month.
            DB::table('portfolio_transactions')->where('type', 'profit')->whereNull('performance_month')
                ->orderBy('id')->chunkById(250, function ($rows): void {
                    foreach ($rows as $row) {
                        $date = Carbon::parse($row->transaction_date ?: $row->created_at ?: now())->startOfDay();
                        $month = $date->day <= 5 ? $date->copy()->subMonthNoOverflow()->startOfMonth() : $date->copy()->startOfMonth();
                        DB::table('portfolio_transactions')->where('id', $row->id)->update(['performance_month' => $month->toDateString()]);
                    }
                });
        }

        if (Schema::hasTable('monthly_statements')) {
            Schema::table('monthly_statements', function (Blueprint $table): void {
                if (! Schema::hasColumn('monthly_statements', 'profit_paid')) $table->decimal('profit_paid', 18, 2)->default(0);
                if (! Schema::hasColumn('monthly_statements', 'profit_paid_usd')) $table->decimal('profit_paid_usd', 18, 2)->nullable();
                if (! Schema::hasColumn('monthly_statements', 'payment_date')) $table->date('payment_date')->nullable();
                if (! Schema::hasColumn('monthly_statements', 'auto_generated')) $table->boolean('auto_generated')->default(false)->index();
            });
        }

        // Reconcile account rollups to the corrected ledger without deleting any financial record.
        if (Schema::hasTable('portfolio_accounts') && Schema::hasTable('portfolio_transactions')) {
            DB::table('portfolio_accounts')->orderBy('id')->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $posted = DB::table('portfolio_transactions')->where('portfolio_account_id', $account->id)->where('status', 'posted');
                    $openingLocal = (float) ($account->opening_value ?? 0);
                    $openingUsd = (float) ($account->opening_value_usd ?? (strtoupper((string)($account->currency ?? 'USD')) === 'USD' ? $openingLocal : 0));
                    $currentValue = max(0, round($openingLocal + (float) (clone $posted)->sum('current_value_effect'), 2));
                    $netCapital = max(0, round($openingLocal + (float) (clone $posted)->sum('net_contributions_effect'), 2));
                    $profit = round((float) (clone $posted)->sum('profit_effect'), 2);
                    $currentUsd = max(0, round($openingUsd + (float) (clone $posted)->sum('usd_current_value_effect'), 2));
                    $netUsd = max(0, round($openingUsd + (float) (clone $posted)->sum('usd_net_contributions_effect'), 2));
                    $profitUsd = round((float) (clone $posted)->sum('usd_profit_effect'), 2);
                    $fx = round((float) (clone $posted)->sum('fx_gain_loss_usd'), 2);
                    $monthStart = now()->startOfMonth()->toDateString();
                    $monthlyProfit = round((float) DB::table('portfolio_transactions')->where('portfolio_account_id',$account->id)->where('status','posted')->where('type','profit')->whereDate('performance_month',$monthStart)->sum('amount'), 2);
                    $monthlyProfitUsd = round((float) DB::table('portfolio_transactions')->where('portfolio_account_id',$account->id)->where('status','posted')->where('type','profit')->whereDate('performance_month',$monthStart)->sum('usd_amount'), 2);
                    $latestDate = DB::table('portfolio_transactions')->where('portfolio_account_id',$account->id)->where('status','posted')->max('transaction_date');
                    DB::table('portfolio_accounts')->where('id',$account->id)->update([
                        'current_value'=>$currentValue,
                        'net_contributions'=>$netCapital,
                        'total_profit'=>$profit,
                        'monthly_profit'=>$monthlyProfit,
                        'current_value_usd'=>$currentUsd,
                        'net_contributions_usd'=>$netUsd,
                        'total_profit_usd'=>$profitUsd,
                        'monthly_profit_usd'=>$monthlyProfitUsd,
                        'realized_fx_gain_loss_usd'=>$fx,
                        'valuation_date'=>$latestDate ?: ($account->valuation_date ?? today()->toDateString()),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // ABS live-production rule: finance/audit fields and corrected ledger effects are retained.
        // Rolling code back must never delete investor records or re-introduce paid profit into capital.
    }
};
