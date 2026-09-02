<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pulse_plans')) {
            Schema::table('pulse_plans', function (Blueprint $table): void {
                if (! Schema::hasColumn('pulse_plans', 'is_trial')) $table->boolean('is_trial')->default(false);
                if (! Schema::hasColumn('pulse_plans', 'is_public')) $table->boolean('is_public')->default(true);
                if (! Schema::hasColumn('pulse_plans', 'request_enabled')) $table->boolean('request_enabled')->default(true);
                if (! Schema::hasColumn('pulse_plans', 'requires_payment')) $table->boolean('requires_payment')->default(true);
                if (! Schema::hasColumn('pulse_plans', 'access_days')) $table->unsignedInteger('access_days')->default(30);
                if (! Schema::hasColumn('pulse_plans', 'badge')) $table->string('badge', 50)->nullable();
                if (! Schema::hasColumn('pulse_plans', 'is_featured')) $table->boolean('is_featured')->default(false);
            });
        }

        if (Schema::hasTable('user_service_access') && ! Schema::hasColumn('user_service_access', 'trial_used_at')) {
            Schema::table('user_service_access', fn (Blueprint $table) => $table->timestamp('trial_used_at')->nullable());
        }

        if (! Schema::hasTable('pulse_promotion_codes')) {
            Schema::create('pulse_promotion_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('label', 120)->nullable();
                $table->string('type', 30)->default('coupon');
                $table->string('discount_type', 30)->default('percent');
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->unsignedBigInteger('applicable_plan_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->unsignedInteger('access_days')->nullable();
                $table->unsignedInteger('max_uses')->default(0);
                $table->unsignedInteger('per_user_limit')->default(1);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->boolean('auto_activate')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_membership_requests')) {
            Schema::create('pulse_membership_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('pulse_plan_id')->index();
                $table->string('status', 30)->default('submitted')->index();
                $table->decimal('base_amount', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('final_amount', 12, 2)->default(0);
                $table->string('currency', 12)->default('USDT');
                $table->string('network', 80)->nullable();
                $table->text('wallet_address_snapshot')->nullable();
                $table->string('payment_reference', 190)->nullable()->unique();
                $table->string('payment_proof_path', 255)->nullable();
                $table->unsignedBigInteger('promotion_code_id')->nullable()->index();
                $table->string('promotion_code_snapshot', 80)->nullable();
                $table->unsignedInteger('activation_days')->default(30);
                $table->text('user_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('activated_access_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pulse_promotion_redemptions')) {
            Schema::create('pulse_promotion_redemptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('promotion_code_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('pulse_plan_id')->index();
                $table->unsignedBigInteger('membership_request_id')->nullable()->index();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamp('redeemed_at')->nullable();
                $table->timestamps();
                $table->unique(['promotion_code_id', 'membership_request_id'], 'pulse_promo_request_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_promotion_redemptions');
        Schema::dropIfExists('pulse_membership_requests');
        Schema::dropIfExists('pulse_promotion_codes');
    }
};
