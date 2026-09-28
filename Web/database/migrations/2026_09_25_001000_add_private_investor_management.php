<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing production databases created the role as a MySQL ENUM. Widen it
        // non-destructively so the new Private Investor role can be assigned while
        // preserving every existing account and legacy private_member value.
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(40) NULL DEFAULT 'user'");
        }

        if (! Schema::hasTable('portfolio_requests')) {
            Schema::create('portfolio_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('portfolio_account_id')->nullable()->index();
                $table->string('type', 30)->index();
                $table->decimal('amount', 18, 2)->nullable();
                $table->string('currency', 10)->default('USD');
                $table->string('status', 30)->default('submitted')->index();
                $table->text('message')->nullable();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Production-safe by design: investor request history is retained.
    }
};
