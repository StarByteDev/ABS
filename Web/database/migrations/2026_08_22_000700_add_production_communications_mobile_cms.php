<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_delivery_logs')) {
            Schema::create('email_delivery_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event', 80)->default('general')->index();
                $table->string('recipient_email');
                $table->string('subject');
                $table->string('status', 30)->default('queued')->index();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('sent_at')->nullable()->index();
                $table->timestamps();
                $table->index(['recipient_email', 'created_at']);
            });
        }

        if (! Schema::hasTable('mobile_devices')) {
            Schema::create('mobile_devices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('device_uuid', 190);
                $table->string('platform', 30)->default('unknown')->index();
                $table->string('device_name')->nullable();
                $table->string('app_version', 50)->nullable();
                $table->string('os_version', 80)->nullable();
                $table->text('push_token')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
                $table->unique(['user_id', 'device_uuid']);
            });
        }

        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('email');
                $table->string('subject', 180);
                $table->string('category', 60)->default('general')->index();
                $table->longText('message');
                $table->string('status', 30)->default('new')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->text('admin_notes')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->timestamps();
                $table->index(['email', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Non-destructive production upgrade. Tables are intentionally retained.
    }
};
