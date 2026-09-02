<?php

use App\Support\PulseSchemaRepair;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PulseSchemaRepair::repair();
    }

    public function down(): void
    {
        // Intentionally non-destructive. Pulse tables contain user access,
        // exchange connection references, signals, trades and audit history.
    }
};
