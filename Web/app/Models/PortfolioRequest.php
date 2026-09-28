<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioRequest extends Model
{
    protected $fillable = [
        'user_id', 'portfolio_account_id', 'type', 'amount', 'currency', 'status',
        'message', 'admin_note', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function account(): BelongsTo { return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function isOpen(): bool
    {
        return in_array($this->status, ['submitted', 'under_review'], true);
    }
}
