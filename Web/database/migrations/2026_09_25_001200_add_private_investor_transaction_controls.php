<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portfolio_transactions')) return;

        Schema::table('portfolio_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('portfolio_transactions', 'status')) $table->string('status', 20)->default('posted')->index();
            if (! Schema::hasColumn('portfolio_transactions', 'created_by')) $table->unsignedBigInteger('created_by')->nullable()->index();
            if (! Schema::hasColumn('portfolio_transactions', 'current_value_effect')) $table->decimal('current_value_effect', 18, 2)->default(0);
            if (! Schema::hasColumn('portfolio_transactions', 'net_contributions_effect')) $table->decimal('net_contributions_effect', 18, 2)->default(0);
            if (! Schema::hasColumn('portfolio_transactions', 'profit_effect')) $table->decimal('profit_effect', 18, 2)->default(0);
            if (! Schema::hasColumn('portfolio_transactions', 'monthly_profit_effect')) $table->decimal('monthly_profit_effect', 18, 2)->default(0);
            if (! Schema::hasColumn('portfolio_transactions', 'posted_at')) $table->timestamp('posted_at')->nullable()->index();
            if (! Schema::hasColumn('portfolio_transactions', 'voided_at')) $table->timestamp('voided_at')->nullable()->index();
            if (! Schema::hasColumn('portfolio_transactions', 'voided_by')) $table->unsignedBigInteger('voided_by')->nullable()->index();
            if (! Schema::hasColumn('portfolio_transactions', 'void_reason')) $table->text('void_reason')->nullable();
        });

        // Backfill the exact balance effects used by historical ABS transaction logic.
        DB::table('portfolio_transactions')->whereNull('status')->update(['status' => 'posted']);
        DB::table('portfolio_transactions')->where('status', 'posted')->whereNull('posted_at')->update(['posted_at' => DB::raw('COALESCE(created_at, updated_at)')]);
        DB::statement("UPDATE portfolio_transactions SET current_value_effect = CASE type WHEN 'deposit' THEN amount WHEN 'withdrawal' THEN -amount WHEN 'profit' THEN amount WHEN 'loss' THEN -amount WHEN 'fee' THEN -amount ELSE 0 END WHERE current_value_effect = 0");
        DB::statement("UPDATE portfolio_transactions SET net_contributions_effect = CASE type WHEN 'deposit' THEN amount WHEN 'withdrawal' THEN -amount ELSE 0 END WHERE net_contributions_effect = 0");
        DB::statement("UPDATE portfolio_transactions SET profit_effect = CASE type WHEN 'profit' THEN amount WHEN 'loss' THEN -amount WHEN 'fee' THEN -amount ELSE 0 END WHERE profit_effect = 0");
        DB::statement("UPDATE portfolio_transactions SET monthly_profit_effect = CASE type WHEN 'profit' THEN amount WHEN 'loss' THEN -amount WHEN 'fee' THEN -amount ELSE 0 END WHERE monthly_profit_effect = 0");
    }

    public function down(): void
    {
        // Live ABS production rule: never remove investor financial audit data on rollback.
    }
};
