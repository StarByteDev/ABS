<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyStatement extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_account_id', 'statement_month', 'currency', 'fx_rate_to_usd', 'opening_balance', 'contributions', 'withdrawals', 'profit_loss', 'profit_paid', 'closing_balance', 'opening_balance_usd', 'contributions_usd', 'withdrawals_usd', 'profit_loss_usd', 'profit_paid_usd', 'closing_balance_usd', 'payment_date', 'auto_generated', 'notes', 'pdf_path', 'published_at'];

    protected $hidden = ['fx_rate_to_usd', 'opening_balance_usd', 'contributions_usd', 'withdrawals_usd', 'profit_loss_usd', 'profit_paid_usd', 'closing_balance_usd'];

    protected function casts(): array
    {
        return [
            'statement_month' => 'date', 'opening_balance' => 'decimal:2', 'contributions' => 'decimal:2',
            'withdrawals' => 'decimal:2', 'profit_loss' => 'decimal:2', 'profit_paid' => 'decimal:2', 'closing_balance' => 'decimal:2', 'fx_rate_to_usd' => 'decimal:8', 'opening_balance_usd' => 'decimal:2', 'contributions_usd' => 'decimal:2', 'withdrawals_usd' => 'decimal:2', 'profit_loss_usd' => 'decimal:2', 'profit_paid_usd' => 'decimal:2', 'closing_balance_usd' => 'decimal:2', 'payment_date' => 'date', 'auto_generated' => 'boolean', 'published_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id');
    }
}
