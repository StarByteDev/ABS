<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['portfolio_account_id', 'type', 'amount', 'transaction_date', 'reference', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PortfolioAccount::class, 'portfolio_account_id');
    }
}
