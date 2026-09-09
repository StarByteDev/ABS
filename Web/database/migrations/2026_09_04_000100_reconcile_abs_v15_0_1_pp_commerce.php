<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pulse_system_settings')) {
            DB::table('pulse_system_settings')->insertOrIgnore([
                'key' => 'point_purchases_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'points',
                'description' => 'Allow authenticated users to submit Admin-verified USDT purchases for Pulse Points packs.',
            ]);

            foreach ([
                'membership_requests_enabled' => 'Legacy direct membership requests are disabled in ABS V15.0.1. Plans activate with Pulse Points.',
                'promotion_codes_enabled' => 'Legacy direct membership checkout promotions are disabled in the V15 Pulse Points commerce model.',
            ] as $key => $description) {
                DB::table('pulse_system_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => '0', 'type' => 'boolean', 'group' => 'membership', 'description' => $description],
                );
            }
        }

        if (Schema::hasTable('pulse_plans')) {
            $updates = [];
            if (Schema::hasColumn('pulse_plans', 'request_enabled')) $updates['request_enabled'] = false;
            if (Schema::hasColumn('pulse_plans', 'requires_payment')) $updates['requires_payment'] = false;
            if ($updates !== []) DB::table('pulse_plans')->update($updates);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. V15.0.1 does not reactivate legacy
        // direct-USDT plan commerce during rollback.
    }
};
