<?php

use App\Support\AbsSchemaRepair;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AbsSchemaRepair::repair();
    }

    public function down(): void
    {
        // Non-destructive by design for compatibility with existing ABS/Pulse databases.
        // Use `php artisan migrate:fresh` only against a dedicated database when a full reset is required.
    }
};
