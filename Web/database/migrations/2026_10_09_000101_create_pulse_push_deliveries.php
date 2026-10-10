<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (Schema::hasTable('pulse_push_deliveries')) return;
  Schema::create('pulse_push_deliveries', function(Blueprint $table){
    $table->id(); $table->foreignId('pulse_alert_id')->index();
    $table->foreignId('mobile_device_id')->index();
    $table->string('status',16)->default('pending')->index();
    $table->unsignedTinyInteger('attempts')->default(0);
    $table->string('error_code',120)->nullable(); $table->timestamp('sent_at')->nullable();
    $table->timestamps(); $table->unique(['pulse_alert_id','mobile_device_id'],'pulse_delivery_once');
  });
 }
 public function down(): void { /* Data-safe production rollback: never drop alert history. */ }
};
