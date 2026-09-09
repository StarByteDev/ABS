<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PulseMission extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'event_key', 'period', 'target_count', 'reward_points', 'reward_xp', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'target_count' => 'integer', 'reward_points' => 'integer', 'reward_xp' => 'integer',
            'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PulseUserMission::class);
    }
}
