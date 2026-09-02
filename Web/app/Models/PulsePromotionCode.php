<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PulsePromotionCode extends Model
{
    protected $fillable = [
        'code', 'label', 'type', 'discount_type', 'discount_value', 'applicable_plan_id', 'assigned_user_id',
        'access_days', 'max_uses', 'per_user_limit', 'valid_from', 'valid_until',
        'auto_activate', 'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'applicable_plan_id' => 'integer',
            'assigned_user_id' => 'integer',
            'access_days' => 'integer',
            'max_uses' => 'integer',
            'per_user_limit' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'auto_activate' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PulsePlan::class, 'applicable_plan_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PulsePromotionRedemption::class, 'promotion_code_id');
    }

    public function membershipRequests(): HasMany
    {
        return $this->hasMany(PulseMembershipRequest::class, 'promotion_code_id');
    }

    public function discountFor(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $value = max(0.0, (float) $this->discount_value);

        return round(match ($this->discount_type) {
            'percent' => min($amount, $amount * min(100, $value) / 100),
            'fixed' => min($amount, $value),
            'full' => $amount,
            default => 0.0,
        }, 2);
    }

    public function displayBenefit(): string
    {
        return match ($this->discount_type) {
            'percent' => rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'% off',
            'fixed' => number_format((float) $this->discount_value, 2).' off',
            'full' => 'Full membership value',
            default => 'Promotion',
        };
    }
}
