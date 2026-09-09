<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseUserMission extends Model
{
    protected $fillable = ['user_id', 'pulse_mission_id', 'period_key', 'progress', 'completed_at', 'claimed_at'];

    protected function casts(): array
    {
        return ['progress' => 'integer', 'completed_at' => 'datetime', 'claimed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function mission(): BelongsTo { return $this->belongsTo(PulseMission::class, 'pulse_mission_id'); }
}
