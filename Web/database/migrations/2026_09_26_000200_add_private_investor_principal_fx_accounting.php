<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portfolio_accounts') && ! Schema::hasColumn('portfolio_accounts', 'realized_fx_gain_loss_usd')) {
            Schema::table('portfolio_accounts', function (Blueprint $table): void {
                $table->decimal('realized_fx_gain_loss_usd', 18, 2)->default(0)->after('monthly_profit_usd');
            });
        }

        if (Schema::hasTable('portfolio_transactions')) {
            $columns = [
                'principal_usd_basis' => fn (Blueprint $t) => $t->decimal('principal_usd_basis', 18, 2)->nullable(),
                'settlement_usd_amount' => fn (Blueprint $t) => $t->decimal('settlement_usd_amount', 18, 2)->nullable(),
                'fx_gain_loss_usd' => fn (Blueprint $t) => $t->decimal('fx_gain_loss_usd', 18, 2)->default(0),
            ];
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('portfolio_transactions', $name)) {
                    Schema::table('portfolio_transactions', fn (Blueprint $table) => $add($table));
                }
            }

            // Preserve every historical row. V15.7.1 rows keep their previously locked USD values;
            // the new fields are backfilled without deleting or replacing any transaction.
            DB::table('portfolio_transactions')->whereNull('settlement_usd_amount')->update([
                'settlement_usd_amount' => DB::raw('usd_amount'),
            ]);
            DB::table('portfolio_transactions')->where('type', 'deposit')->whereNull('principal_usd_basis')->update([
                'principal_usd_basis' => DB::raw('usd_amount'),
            ]);
            DB::table('portfolio_transactions')->whereRaw("UPPER(COALESCE(currency,'USD')) = 'USD'")->whereNull('principal_usd_basis')->whereIn('type', ['deposit','withdrawal'])->update([
                'principal_usd_basis' => DB::raw('amount'),
            ]);
        }

        // Existing V15.7.1 rows remain financially unchanged. New/edited withdrawals use the V15.7.2
        // principal-basis + settlement model and begin accumulating Admin-only realized FX P/L.
        if (Schema::hasTable('portfolio_accounts') && Schema::hasColumn('portfolio_accounts', 'realized_fx_gain_loss_usd')) {
            DB::table('portfolio_accounts')->whereNull('realized_fx_gain_loss_usd')->update(['realized_fx_gain_loss_usd' => 0]);
        }
    }

    public function down(): void
    {
        // ABS live-production rule: additive finance/audit fields are intentionally retained on code rollback.
    }
};
