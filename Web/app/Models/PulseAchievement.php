<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PulseAchievement extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'metric_key', 'target_value', 'reward_points', 'reward_xp', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'target_value' => 'integer', 'reward_points' => 'integer', 'reward_xp' => 'integer',
            'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(PulseUserAchievement::class);
    }
}
