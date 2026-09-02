<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PulseSignal extends Model
{
    protected $fillable = [
        'user_id', 'scanner_run_id', 'symbol', 'timeframe', 'direction', 'entry_price',
        'stop_loss', 'take_profit', 'score', 'confidence_label', 'status',
        'strategy_breakdown', 'generated_at', 'expires_at', 'signal_fingerprint', 'strategy_version',
        'strategy_snapshot', 'take_profit_levels', 'technical_score', 'reliability_score', 'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'entry_price' => 'decimal:12',
            'stop_loss' => 'decimal:12',
            'take_profit' => 'decimal:12',
            'score' => 'decimal:2',
            'strategy_breakdown' => 'array',
            'strategy_snapshot' => 'array',
            'take_profit_levels' => 'array',
            'technical_score' => 'decimal:2',
            'reliability_score' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scannerRun(): BelongsTo
    {
        return $this->belongsTo(PulseScannerRun::class, 'scanner_run_id');
    }

    public function validation(): HasOne
    {
        return $this->hasOne(PulseSignalValidation::class, 'signal_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(PulseTrade::class, 'signal_id');
    }

    public function isActionable(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && in_array($this->direction, ['LONG', 'SHORT'], true);
    }
}
