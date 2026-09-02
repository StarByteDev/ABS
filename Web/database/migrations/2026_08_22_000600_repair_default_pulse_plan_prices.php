<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pulse_plans')) {
            return;
        }

        foreach (['pulse-intelligence' => 29.00, 'pulse-professional' => 79.00] as $slug => $price) {
            DB::table('pulse_plans')
                ->where('slug', $slug)
                ->where(function ($query) {
                    $query->whereNull('monthly_price')->orWhere('monthly_price', '<=', 0);
                })
                ->update([
                    'monthly_price' => $price,
                    'currency' => 'USDT',
                    'requires_payment' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Non-destructive repair migration: do not restore an invalid zero paid-plan price.
    }
};
