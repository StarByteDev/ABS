<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('password');
            $table->enum('role', ['user', 'private_member', 'admin'])->default('user')->index();
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active')->index();
            $table->timestamp('private_member_approved_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive by design for compatibility with existing ABS/Pulse databases.
        // Use `php artisan migrate:fresh` only against a dedicated database when a full reset is required.
    }
};
