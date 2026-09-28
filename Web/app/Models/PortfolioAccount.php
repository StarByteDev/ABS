<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioAccount extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'account_name', 'currency', 'opening_value', 'current_value', 'net_contributions', 'total_profit', 'monthly_profit', 'opening_value_usd', 'current_value_usd', 'net_contributions_usd', 'total_profit_usd', 'monthly_profit_usd', 'realized_fx_gain_loss_usd', 'valuation_date', 'is_active', 'notes'];

    protected $hidden = ['opening_value_usd', 'current_value_usd', 'net_contributions_usd', 'total_profit_usd', 'monthly_profit_usd', 'realized_fx_gain_loss_usd'];

    protected function casts(): array
    {
        return [
            'opening_value' => 'decimal:2', 'current_value' => 'decimal:2', 'net_contributions' => 'decimal:2',
            'total_profit' => 'decimal:2', 'monthly_profit' => 'decimal:2', 'opening_value_usd' => 'decimal:2', 'current_value_usd' => 'decimal:2', 'net_contributions_usd' => 'decimal:2', 'total_profit_usd' => 'decimal:2', 'monthly_profit_usd' => 'decimal:2', 'realized_fx_gain_loss_usd' => 'decimal:2', 'valuation_date' => 'date', 'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PortfolioTransaction::class)->latest('transaction_date');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(MonthlyStatement::class)->latest('statement_month');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PortfolioRequest::class, 'portfolio_account_id')->latest();
    }

    public function performancePlans(): HasMany
    {
        return $this->hasMany(PortfolioPerformancePlan::class, 'portfolio_account_id')->latest('plan_month');
    }

    public function investmentTerm()
    {
        return $this->hasOne(PortfolioInvestmentTerm::class, 'portfolio_account_id');
    }
}
