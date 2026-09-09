<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseRewardClaim extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'reward_type', 'reward_key', 'points', 'xp', 'provider_reference', 'meta', 'claimed_at'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'xp' => 'integer', 'meta' => 'array', 'claimed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
