<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseTrade extends Model
{
    protected $fillable = [
        'user_id', 'signal_id', 'symbol', 'side', 'environment', 'order_type',
        'leverage', 'quantity', 'entry_price', 'current_price', 'stop_loss',
        'take_profit', 'exchange_order_id', 'exchange_tp_order_id',
        'exchange_sl_order_id', 'exchange_close_order_id', 'exchange_position_side',
        'status', 'protection_status', 'realized_pnl', 'unrealized_pnl', 'fees',
        'commission_asset', 'opened_at', 'closed_at', 'last_synced_at', 'close_reason', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:12',
            'entry_price' => 'decimal:12',
            'current_price' => 'decimal:12',
            'stop_loss' => 'decimal:12',
            'take_profit' => 'decimal:12',
            'realized_pnl' => 'decimal:8',
            'unrealized_pnl' => 'decimal:8',
            'fees' => 'decimal:8',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(PulseSignal::class, 'signal_id');
    }
}
