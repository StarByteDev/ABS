<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PulsePair extends Model
{
    protected $fillable = [
        'symbol', 'base_asset', 'quote_asset', 'is_enabled', 'sort_order',
        'price_precision', 'quantity_precision', 'tick_size', 'step_size',
        'minimum_quantity', 'minimum_notional', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'tick_size' => 'decimal:12',
            'step_size' => 'decimal:12',
            'minimum_quantity' => 'decimal:12',
            'minimum_notional' => 'decimal:8',
            'last_synced_at' => 'datetime',
        ];
    }
}
