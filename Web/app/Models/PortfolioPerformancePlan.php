<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioPerformancePlan extends Model
{
    protected $fillable = [
        'portfolio_account_id', 'investment_term_id', 'plan_month', 'accrual_start_date', 'accrual_end_date',
        'base_amount', 'target_rate', 'target_amount', 'calculation_basis', 'status', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'plan_month' => 'date',
            'accrual_start_date' => 'date',
            'accrual_end_date' => 'date',
            'base_amount' => 'decimal:2',
            'target_rate' => 'decimal:4',
            'target_amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo { return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id'); }
    public function investmentTerm(): BelongsTo { return $this->belongsTo(PortfolioInvestmentTerm::class, 'investment_term_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function accruals(): HasMany { return $this->hasMany(PortfolioDailyAccrual::class)->orderBy('accrual_date'); }
}
