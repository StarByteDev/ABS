<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('economic_events')) return;
        Schema::table('economic_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('economic_events', 'provider_event_id')) $table->string('provider_event_id', 190)->nullable()->index()->after('source');
            if (! Schema::hasColumn('economic_events', 'source_url')) $table->string('source_url', 500)->nullable()->after('provider_event_id');
            if (! Schema::hasColumn('economic_events', 'crypto_impact')) $table->string('crypto_impact', 20)->nullable()->index()->after('source_url');
            if (! Schema::hasColumn('economic_events', 'easy_explanation')) $table->text('easy_explanation')->nullable()->after('crypto_impact');
            if (! Schema::hasColumn('economic_events', 'crypto_impact_summary')) $table->text('crypto_impact_summary')->nullable()->after('easy_explanation');
            if (! Schema::hasColumn('economic_events', 'is_crypto_relevant')) $table->boolean('is_crypto_relevant')->default(true)->index()->after('crypto_impact_summary');
            if (! Schema::hasColumn('economic_events', 'synced_at')) $table->timestamp('synced_at')->nullable()->index()->after('is_crypto_relevant');
        });
    }

    public function down(): void
    {
        // Non-destructive for ABS shared-hosting upgrades.
    }
};
