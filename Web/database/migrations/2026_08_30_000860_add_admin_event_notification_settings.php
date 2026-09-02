<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) return;

        $defaults = [
            ['key' => 'admin_notification_email', 'value' => 'i@armansabir.com', 'type' => 'string', 'group' => 'communications'],
            ['key' => 'admin_notify_new_registration', 'value' => '1', 'type' => 'boolean', 'group' => 'communications'],
            ['key' => 'admin_notify_new_subscription', 'value' => '1', 'type' => 'boolean', 'group' => 'communications'],
        ];

        foreach ($defaults as $setting) {
            if (! DB::table('site_settings')->where('key', $setting['key'])->exists()) {
                DB::table('site_settings')->insert($setting);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: administrator communication preferences
        // are retained on rollback rather than deleting a configured recipient.
    }
};
