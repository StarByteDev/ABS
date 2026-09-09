<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PulsePointPack extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'points', 'bonus_points', 'price_usdt', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'bonus_points' => 'integer',
            'price_usdt' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PulsePointPurchase::class);
    }

    public function totalPoints(): int
    {
        return max(0, (int) $this->points) + max(0, (int) $this->bonus_points);
    }
}
