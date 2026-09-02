<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('tagline');
            $table->text('description');
            $table->string('icon', 20)->default('◈');
            $table->string('accent', 30)->default('violet');
            $table->json('features')->nullable();
            $table->enum('status', ['draft', 'live', 'archived'])->default('live')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('news_articles')) {
            Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->longText('body');
            $table->string('category')->index();
            $table->string('image_url')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('author_name')->default('ABS Editorial');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('research_reports')) {
            Schema::create('research_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('body');
            $table->string('category')->index();
            $table->string('asset_symbol', 20)->nullable()->index();
            $table->enum('risk_level', ['low', 'medium', 'high', 'not_rated'])->default('not_rated');
            $table->string('image_url')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('learning_articles')) {
            Schema::create('learning_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->longText('body');
            $table->string('category')->index();
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->unsignedInteger('duration_minutes')->default(5);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (! Schema::hasTable('economic_events')) {
            Schema::create('economic_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('country', 80)->nullable();
            $table->string('currency', 10)->nullable()->index();
            $table->enum('impact', ['low', 'medium', 'high'])->default('medium')->index();
            $table->timestamp('event_at')->index();
            $table->string('previous_value')->nullable();
            $table->string('forecast_value')->nullable();
            $table->string('actual_value')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general')->index();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive by design for compatibility with existing ABS/Pulse databases.
        // Use `php artisan migrate:fresh` only against a dedicated database when a full reset is required.
    }
};
