<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulsePublicSignalUnlock extends Model
{
    protected $fillable = [
        'visitor_hash', 'provider', 'provider_reference', 'ad_unit', 'signal_id',
        'signal_snapshot', 'status', 'claimed_at', 'view_expires_at', 'next_available_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'signal_snapshot' => 'array',
            'claimed_at' => 'datetime',
            'view_expires_at' => 'datetime',
            'next_available_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(PulseSignal::class, 'signal_id');
    }
}
