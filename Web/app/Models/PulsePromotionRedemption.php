<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulsePromotionRedemption extends Model
{
    protected $fillable = [
        'promotion_code_id', 'user_id', 'pulse_plan_id', 'membership_request_id',
        'discount_amount', 'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'redeemed_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(PulsePromotionCode::class, 'promotion_code_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PulsePlan::class, 'pulse_plan_id');
    }

    public function membershipRequest(): BelongsTo
    {
        return $this->belongsTo(PulseMembershipRequest::class, 'membership_request_id');
    }
}
