<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioInvestmentTerm extends Model
{
    protected $fillable = [
        'portfolio_account_id', 'effective_from', 'monthly_target_rate', 'status',
        'auto_payout', 'payout_day', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'monthly_target_rate' => 'decimal:4',
            'auto_payout' => 'boolean',
            'payout_day' => 'integer',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
