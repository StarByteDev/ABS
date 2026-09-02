<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserServiceAccess extends Model
{
    /**
     * The legacy ABS/Pulse schema intentionally uses the singular table name.
     * Laravel would otherwise query `user_service_accesses`.
     */
    protected $table = 'user_service_access';

    protected $fillable = [
        'user_id', 'service', 'status', 'pulse_plan_id', 'approved_by',
        'starts_at', 'ends_at', 'trial_used_at', 'permissions', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_used_at' => 'datetime',
            'permissions' => 'array',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
