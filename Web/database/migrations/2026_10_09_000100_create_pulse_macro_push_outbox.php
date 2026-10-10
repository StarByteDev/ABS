<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additive only: no user, investor, signal, transaction or history changes.
        if (! Schema::hasTable('pulse_macro_release_alerts')) {
            Schema::create('pulse_macro_release_alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('economic_event_id')->unique()->constrained('economic_events')->cascadeOnDelete();
                $table->string('actual_hash', 64);
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('pulse_macro_device_deliveries')) {
            Schema::create('pulse_macro_device_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('alert_id')->constrained('pulse_macro_release_alerts')->cascadeOnDelete();
                $table->foreignId('mobile_device_id')->constrained('mobile_devices')->cascadeOnDelete();
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('error_code', 100)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['alert_id', 'mobile_device_id'], 'macro_alert_device_once');
            });
        }
    }
    // Intentional: never drop delivery history or production tables on rollback.
    public function down(): void {}
};
