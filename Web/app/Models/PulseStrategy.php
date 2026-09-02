<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PulseStrategy extends Model
{
    protected $fillable = [
        'name', 'slug', 'version', 'description', 'timeframe', 'weight',
        'minimum_score', 'settings', 'is_enabled', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'minimum_score' => 'decimal:2',
            'settings' => 'array',
            'is_enabled' => 'boolean',
        ];
    }
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(PulsePlan::class, 'pulse_plan_strategies')
            ->withPivot(['is_enabled', 'weight_override'])
            ->withTimestamps();
    }

}
