<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portfolio_accounts')) {
            $columns = [
                'opening_value_usd' => fn (Blueprint $t) => $t->decimal('opening_value_usd', 18, 2)->nullable(),
                'current_value_usd' => fn (Blueprint $t) => $t->decimal('current_value_usd', 18, 2)->nullable(),
                'net_contributions_usd' => fn (Blueprint $t) => $t->decimal('net_contributions_usd', 18, 2)->nullable(),
                'total_profit_usd' => fn (Blueprint $t) => $t->decimal('total_profit_usd', 18, 2)->nullable(),
                'monthly_profit_usd' => fn (Blueprint $t) => $t->decimal('monthly_profit_usd', 18, 2)->nullable(),
            ];
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('portfolio_accounts', $name)) {
                    Schema::table('portfolio_accounts', fn (Blueprint $table) => $add($table));
                }
            }
            DB::statement("UPDATE portfolio_accounts SET opening_value_usd = opening_value, current_value_usd = current_value, net_contributions_usd = net_contributions, total_profit_usd = total_profit, monthly_profit_usd = monthly_profit WHERE UPPER(currency) = 'USD'");
        }

        if (Schema::hasTable('portfolio_transactions')) {
            $columns = [
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->nullable(),
                'fx_rate_to_usd' => fn (Blueprint $t) => $t->decimal('fx_rate_to_usd', 18, 8)->nullable(),
                'usd_amount' => fn (Blueprint $t) => $t->decimal('usd_amount', 18, 2)->nullable(),
                'usd_current_value_effect' => fn (Blueprint $t) => $t->decimal('usd_current_value_effect', 18, 2)->nullable(),
                'usd_net_contributions_effect' => fn (Blueprint $t) => $t->decimal('usd_net_contributions_effect', 18, 2)->nullable(),
                'usd_profit_effect' => fn (Blueprint $t) => $t->decimal('usd_profit_effect', 18, 2)->nullable(),
                'usd_monthly_profit_effect' => fn (Blueprint $t) => $t->decimal('usd_monthly_profit_effect', 18, 2)->nullable(),
                'fx_source' => fn (Blueprint $t) => $t->string('fx_source', 40)->nullable(),
                'fx_locked_at' => fn (Blueprint $t) => $t->timestamp('fx_locked_at')->nullable(),
            ];
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('portfolio_transactions', $name)) {
                    Schema::table('portfolio_transactions', fn (Blueprint $table) => $add($table));
                }
            }
            DB::table('portfolio_transactions')->whereNull('currency')->orderBy('id')->chunkById(250, function ($rows): void {
                $accounts = DB::table('portfolio_accounts')->whereIn('id', $rows->pluck('portfolio_account_id')->filter()->unique()->values())->get()->keyBy('id');
                foreach ($rows as $row) {
                    $account = $accounts->get($row->portfolio_account_id);
                    if (! $account) continue;
                    $currency = strtoupper((string) ($account->currency ?: 'USD'));
                    $update = ['currency' => $currency];
                    if ($currency === 'USD') {
                        $update += [
                            'fx_rate_to_usd' => 1,
                            'usd_amount' => $row->amount,
                            'usd_current_value_effect' => $row->current_value_effect,
                            'usd_net_contributions_effect' => $row->net_contributions_effect,
                            'usd_profit_effect' => $row->profit_effect,
                            'usd_monthly_profit_effect' => $row->monthly_profit_effect,
                            'fx_source' => 'native_usd',
                            'fx_locked_at' => $row->posted_at ?: $row->created_at,
                        ];
                    }
                    DB::table('portfolio_transactions')->where('id', $row->id)->update($update);
                }
            });
        }

        if (Schema::hasTable('monthly_statements')) {
            $columns = [
                'currency' => fn (Blueprint $t) => $t->string('currency', 10)->nullable(),
                'fx_rate_to_usd' => fn (Blueprint $t) => $t->decimal('fx_rate_to_usd', 18, 8)->nullable(),
                'opening_balance_usd' => fn (Blueprint $t) => $t->decimal('opening_balance_usd', 18, 2)->nullable(),
                'contributions_usd' => fn (Blueprint $t) => $t->decimal('contributions_usd', 18, 2)->nullable(),
                'withdrawals_usd' => fn (Blueprint $t) => $t->decimal('withdrawals_usd', 18, 2)->nullable(),
                'profit_loss_usd' => fn (Blueprint $t) => $t->decimal('profit_loss_usd', 18, 2)->nullable(),
                'closing_balance_usd' => fn (Blueprint $t) => $t->decimal('closing_balance_usd', 18, 2)->nullable(),
            ];
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('monthly_statements', $name)) {
                    Schema::table('monthly_statements', fn (Blueprint $table) => $add($table));
                }
            }
            DB::table('monthly_statements')->whereNull('currency')->orderBy('id')->chunkById(250, function ($rows): void {
                $accounts = DB::table('portfolio_accounts')->whereIn('id', $rows->pluck('portfolio_account_id')->filter()->unique()->values())->get()->keyBy('id');
                foreach ($rows as $row) {
                    $account = $accounts->get($row->portfolio_account_id);
                    if (! $account) continue;
                    $currency = strtoupper((string) ($account->currency ?: 'USD'));
                    $update = ['currency' => $currency];
                    if ($currency === 'USD') {
                        $update += [
                            'fx_rate_to_usd' => 1,
                            'opening_balance_usd' => $row->opening_balance,
                            'contributions_usd' => $row->contributions,
                            'withdrawals_usd' => $row->withdrawals,
                            'profit_loss_usd' => $row->profit_loss,
                            'closing_balance_usd' => $row->closing_balance,
                        ];
                    }
                    DB::table('monthly_statements')->where('id', $row->id)->update($update);
                }
            });
        }
    }

    public function down(): void
    {
        // Live ABS rule: currency audit fields are retained on code rollback.
    }
};
