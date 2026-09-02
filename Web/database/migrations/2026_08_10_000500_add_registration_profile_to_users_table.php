<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) return;

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'country_code')) $table->string('country_code', 8)->nullable();
            if (! Schema::hasColumn('users', 'phone')) $table->string('phone', 32)->nullable();
            if (! Schema::hasColumn('users', 'country')) $table->string('country', 80)->nullable();
        });
    }

    public function down(): void
    {
        // Non-destructive by design. Existing ABS identity data is preserved.
    }
};
