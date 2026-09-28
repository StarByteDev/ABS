<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 120);
                $table->string('email');
                $table->string('subject', 180)->default('Pulse Support');
                $table->string('category', 50)->default('other')->index();
                $table->string('channel', 20)->default('web')->index();
                $table->string('status', 30)->default('waiting_support')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('last_customer_message_at')->nullable();
                $table->timestamp('last_admin_message_at')->nullable();
                $table->timestamp('assistant_handled_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['status', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_conversation_id')->constrained('support_conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('sender_type', 20)->index();
                $table->string('message_type', 30)->default('text');
                $table->longText('body');
                $table->json('metadata')->nullable();
                $table->timestamp('read_by_customer_at')->nullable();
                $table->timestamp('read_by_admin_at')->nullable();
                $table->timestamps();
                $table->index(['support_conversation_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Production-safe migration: support history is intentionally retained.
    }
};
