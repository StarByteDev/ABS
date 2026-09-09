<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulsePointPurchase extends Model
{
    protected $fillable = [
        'user_id', 'pulse_point_pack_id', 'status', 'points_snapshot', 'bonus_points_snapshot', 'amount_usdt',
        'network', 'wallet_address_snapshot', 'payment_reference', 'payment_proof_path', 'user_notes', 'admin_notes',
        'reviewed_by', 'reviewed_at', 'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'points_snapshot' => 'integer',
            'bonus_points_snapshot' => 'integer',
            'amount_usdt' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function pack(): BelongsTo { return $this->belongsTo(PulsePointPack::class, 'pulse_point_pack_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isOpen(): bool
    {
        return in_array($this->status, ['submitted', 'under_review'], true);
    }

    public function totalPoints(): int
    {
        return max(0, (int) $this->points_snapshot) + max(0, (int) $this->bonus_points_snapshot);
    }
}
