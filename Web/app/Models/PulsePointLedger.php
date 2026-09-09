<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PulsePointLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pulse_point_ledger';

    protected $fillable = [
        'user_id', 'amount', 'balance_after', 'type', 'source', 'reference_type', 'reference_id',
        'idempotency_key', 'description', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'reference_id' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Pulse Points ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Pulse Points ledger entries are immutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
