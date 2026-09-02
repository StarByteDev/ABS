<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PulseScannerRun extends Model
{
    protected $fillable = [
        'user_id', 'status', 'timeframe', 'pairs_scanned', 'signals_created',
        'started_at', 'completed_at', 'summary', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(PulseSignal::class, 'scanner_run_id');
    }
}
