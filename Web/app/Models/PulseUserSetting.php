<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseUserSetting extends Model
{
    protected $fillable = [
        'user_id', 'environment', 'execution_mode', 'auto_trade_enabled', 'emergency_stop',
        'default_leverage', 'margin_type', 'position_mode', 'risk_per_trade_percent',
        'sizing_mode', 'fixed_notional', 'fixed_quantity', 'minimum_signal_score',
        'default_order_type', 'take_profit_percent', 
        'stop_loss_percent', 'daily_loss_limit', 'max_open_positions',
        'selected_pairs', 'notification_preferences', 'pair_selection_saved_at', 'pair_selection_locked_until',
    ];

    protected function casts(): array
    {
        return [
            'auto_trade_enabled' => 'boolean',
            'emergency_stop' => 'boolean',
            'risk_per_trade_percent' => 'decimal:2',
            'fixed_notional' => 'decimal:8',
            'fixed_quantity' => 'decimal:12',
            'minimum_signal_score' => 'decimal:2',
            'take_profit_percent' => 'decimal:2',
            'stop_loss_percent' => 'decimal:2',
            'daily_loss_limit' => 'decimal:2',
            'selected_pairs' => 'array',
            'notification_preferences' => 'array',
            'pair_selection_saved_at' => 'datetime',
            'pair_selection_locked_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
