<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_rewarded_ad_receipts')) {
            Schema::create('pulse_rewarded_ad_receipts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('provider', 40)->index();
                $table->string('provider_reference', 190);
                $table->string('ad_unit', 255)->nullable();
                $table->unsignedInteger('reward_amount')->default(0);
                $table->string('reward_item', 100)->nullable();
                $table->string('status', 30)->default('verified')->index();
                $table->string('verification_mode', 60)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('rewarded_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_reference'], 'pulse_rewarded_ad_provider_reference_unique');
            });
        }

        $settings = [
            ['rewarded_ads_enabled', '0', 'boolean', 'gamification', 'Allow members to opt in to rewarded ads and receive Pulse Sparks after verification.'],
            ['rewarded_ad_points', '5', 'integer', 'gamification', 'Pulse Sparks credited for each verified rewarded ad.'],
            ['rewarded_ad_xp', '5', 'integer', 'gamification', 'Experience points credited for each verified rewarded ad.'],
            ['rewarded_ads_provider', 'google_ad_manager', 'string', 'gamification', 'Web rewarded-ad provider.'],
            ['rewarded_ads_daily_limit', '10', 'integer', 'gamification', 'Maximum rewarded ads credited to one member per day.'],
            ['rewarded_ads_cooldown_seconds', '120', 'integer', 'gamification', 'Minimum wait between credited rewarded ads.'],
            ['rewarded_web_ad_unit_path', '', 'string', 'gamification', 'Google Ad Manager rewarded web ad unit path, for example /1234567/abs_rewarded.'],
            ['rewarded_web_test_mode', '1', 'boolean', 'gamification', 'Keep web rewarded advertising in test/validation mode while configuring inventory.'],
            ['rewarded_mobile_test_mode', '1', 'boolean', 'gamification', 'Return Google test ad unit IDs to mobile clients until production is approved.'],
            ['rewarded_admob_android_ad_unit_id', '', 'string', 'gamification', 'Production Android AdMob rewarded ad unit ID.'],
            ['rewarded_admob_ios_ad_unit_id', '', 'string', 'gamification', 'Production iOS AdMob rewarded ad unit ID.'],
            ['rewarded_admob_ssv_enabled', '1', 'boolean', 'gamification', 'Verify AdMob rewarded callbacks with Google ECDSA public keys before crediting Sparks.'],
            ['rewarded_admob_max_callback_age_seconds', '3600', 'integer', 'gamification', 'Maximum accepted age of an AdMob SSV callback.'],
        ];
        foreach ($settings as [$key, $value, $type, $group, $description]) {
            DB::table('pulse_system_settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]);
        }

    }

    public function down(): void
    {
        // Keep reward receipts and settings on rollback to preserve auditability.
    }
};
