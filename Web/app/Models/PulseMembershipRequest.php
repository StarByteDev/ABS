<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseMembershipRequest extends Model
{
    protected $fillable = [
        'user_id', 'pulse_plan_id', 'status', 'base_amount', 'discount_amount', 'final_amount',
        'currency', 'network', 'wallet_address_snapshot', 'payment_reference', 'payment_proof_path',
        'promotion_code_id', 'promotion_code_snapshot', 'activation_days', 'user_notes', 'admin_notes',
        'reviewed_by', 'reviewed_at', 'activated_access_id',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'activation_days' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PulsePlan::class, 'pulse_plan_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(PulsePromotionCode::class, 'promotion_code_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function activatedAccess(): BelongsTo
    {
        return $this->belongsTo(UserServiceAccess::class, 'activated_access_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['submitted', 'under_review'], true);
    }
}
