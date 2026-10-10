<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_scanner_runs') || ! Schema::hasColumn('pulse_scanner_runs', 'started_at')) {
            return;
        }

        try {
            if (! Schema::hasIndex('pulse_scanner_runs', ['started_at', 'id'])) {
                Schema::table('pulse_scanner_runs', function (Blueprint $table): void {
                    $table->index(['started_at', 'id'], 'pulse_scanner_runs_started_at_id_idx');
                });
            }
        } catch (\Throwable $e) {
            // The controller also uses primary-key-first pagination, so an index creation
            // limitation on shared hosting must never make the production upgrade fail.
            report($e);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Keeping this performance index is safe for live data.
    }
};
