<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_posts')) {
            Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title');
            $table->text('body');
            $table->string('category')->default('general')->index();
            $table->enum('sentiment', ['bullish', 'neutral', 'bearish'])->default('neutral');
            $table->enum('status', ['pending', 'published', 'hidden'])->default('published')->index();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('community_comments')) {
            Schema::create('community_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_post_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->enum('status', ['published', 'hidden'])->default('published');
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->json('preferences')->nullable();
            $table->enum('status', ['active', 'unsubscribed'])->default('active');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('watchlists')) {
            Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('symbol', 30);
            $table->string('display_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'symbol']);
            });
        }

        if (! Schema::hasTable('portfolio_accounts')) {
            Schema::create('portfolio_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('account_name')->default('Private Member Account');
            $table->string('currency', 10)->default('USD');
            $table->decimal('opening_value', 18, 2)->default(0);
            $table->decimal('current_value', 18, 2)->default(0);
            $table->decimal('net_contributions', 18, 2)->default(0);
            $table->decimal('total_profit', 18, 2)->default(0);
            $table->decimal('monthly_profit', 18, 2)->default(0);
            $table->date('valuation_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('portfolio_transactions')) {
            Schema::create('portfolio_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_account_id')->index();
            $table->enum('type', ['deposit', 'withdrawal', 'profit', 'loss', 'fee', 'adjustment']);
            $table->decimal('amount', 18, 2);
            $table->date('transaction_date')->index();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('monthly_statements')) {
            Schema::create('monthly_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_account_id')->index();
            $table->date('statement_month')->index();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('contributions', 18, 2)->default(0);
            $table->decimal('withdrawals', 18, 2)->default(0);
            $table->decimal('profit_loss', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['portfolio_account_id', 'statement_month']);
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive by design for compatibility with existing ABS/Pulse databases.
        // Use `php artisan migrate:fresh` only against a dedicated database when a full reset is required.
    }
};
