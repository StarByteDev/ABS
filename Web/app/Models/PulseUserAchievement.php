<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseUserAchievement extends Model
{
    protected $fillable = ['user_id', 'pulse_achievement_id', 'unlocked_at', 'claimed_at'];

    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime', 'claimed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function achievement(): BelongsTo { return $this->belongsTo(PulseAchievement::class, 'pulse_achievement_id'); }
}
