<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_system_settings')) return;

        // Upgrade only the old stock copy. Any wording customized by an Admin is preserved.
        $defaults = [
            'public_rewarded_signal_badge' => ['FREE SIGNAL · REWARDED ACCESS', 'FREE SIGNAL'],
            'public_rewarded_signal_title' => ['Watch one ad. Unlock one qualified Pulse signal.', 'Watch a short ad to unlock your Pulse signal.'],
            'public_rewarded_signal_description' => ['No registration required. Complete the rewarded ad and your signal appears for 30 seconds.', 'Your qualified signal appears immediately after the ad completes.'],
            'public_rewarded_signal_cta' => ['Watch Ad & Reveal Signal', 'Watch Ad & Unlock Signal'],
        ];

        foreach ($defaults as $key => [$from, $to]) {
            DB::table('pulse_system_settings')->where('key', $key)->where('value', $from)->update(['value' => $to, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Content-only usability migration; intentionally non-destructive on rollback.
    }
};
