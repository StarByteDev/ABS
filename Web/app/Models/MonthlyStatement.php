<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyStatement extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_account_id', 'statement_month', 'opening_balance', 'contributions', 'withdrawals', 'profit_loss', 'closing_balance', 'notes', 'pdf_path', 'published_at'];

    protected function casts(): array
    {
        return [
            'statement_month' => 'date', 'opening_balance' => 'decimal:2', 'contributions' => 'decimal:2',
            'withdrawals' => 'decimal:2', 'profit_loss' => 'decimal:2', 'closing_balance' => 'decimal:2', 'published_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id');
    }
}
