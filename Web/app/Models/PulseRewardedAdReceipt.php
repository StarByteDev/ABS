<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseRewardedAdReceipt extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_reference', 'ad_unit', 'reward_amount', 'reward_item',
        'status', 'verification_mode', 'meta', 'verified_at', 'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_amount' => 'integer',
            'meta' => 'array',
            'verified_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
