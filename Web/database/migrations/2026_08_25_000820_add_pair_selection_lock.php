<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_user_settings')) {
            return;
        }

        Schema::table('pulse_user_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('pulse_user_settings', 'pair_selection_saved_at')) {
                $table->timestamp('pair_selection_saved_at')->nullable()->after('notification_preferences');
            }
            if (! Schema::hasColumn('pulse_user_settings', 'pair_selection_locked_until')) {
                $table->timestamp('pair_selection_locked_until')->nullable()->after('pair_selection_saved_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pulse_user_settings')) {
            return;
        }

        $columns = [];
        foreach (['pair_selection_saved_at', 'pair_selection_locked_until'] as $column) {
            if (Schema::hasColumn('pulse_user_settings', $column)) {
                $columns[] = $column;
            }
        }
        if ($columns !== []) {
            Schema::table('pulse_user_settings', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
