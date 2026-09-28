<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_account_id', 'type', 'amount', 'currency', 'fx_rate_to_usd', 'usd_amount', 'transaction_date', 'reference', 'description', 'status', 'created_by', 'current_value_effect', 'net_contributions_effect', 'profit_effect', 'monthly_profit_effect', 'usd_current_value_effect', 'usd_net_contributions_effect', 'usd_profit_effect', 'usd_monthly_profit_effect', 'fx_source', 'fx_locked_at', 'principal_usd_basis', 'settlement_usd_amount', 'fx_gain_loss_usd', 'entry_source', 'performance_month', 'posted_at', 'voided_at', 'voided_by', 'void_reason'];

    protected $hidden = ['created_by', 'voided_by', 'current_value_effect', 'net_contributions_effect', 'profit_effect', 'monthly_profit_effect', 'fx_rate_to_usd', 'usd_amount', 'usd_current_value_effect', 'usd_net_contributions_effect', 'usd_profit_effect', 'usd_monthly_profit_effect', 'fx_source', 'fx_locked_at', 'principal_usd_basis', 'settlement_usd_amount', 'fx_gain_loss_usd'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fx_rate_to_usd' => 'decimal:8', 'usd_amount' => 'decimal:2', 'principal_usd_basis' => 'decimal:2', 'settlement_usd_amount' => 'decimal:2', 'fx_gain_loss_usd' => 'decimal:2', 'current_value_effect' => 'decimal:2', 'net_contributions_effect' => 'decimal:2', 'profit_effect' => 'decimal:2', 'monthly_profit_effect' => 'decimal:2', 'usd_current_value_effect' => 'decimal:2', 'usd_net_contributions_effect' => 'decimal:2', 'usd_profit_effect' => 'decimal:2', 'usd_monthly_profit_effect' => 'decimal:2', 'transaction_date' => 'date', 'performance_month' => 'date', 'fx_locked_at' => 'datetime', 'posted_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isPosted(): bool { return $this->status === 'posted'; }
    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isVoided(): bool { return $this->status === 'voided'; }
}
