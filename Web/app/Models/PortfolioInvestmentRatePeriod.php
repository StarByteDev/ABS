<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PortfolioInvestmentRatePeriod extends Model
{
    protected $fillable=['portfolio_account_id','effective_from','monthly_target_rate','profit_disposition','created_by','notes'];
    protected function casts(): array { return ['effective_from'=>'date','monthly_target_rate'=>'decimal:4']; }
    public function account(): BelongsTo { return $this->belongsTo(PortfolioAccount::class,'portfolio_account_id'); }
}
