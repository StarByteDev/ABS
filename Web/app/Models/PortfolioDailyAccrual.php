<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioDailyAccrual extends Model
{
    protected $fillable = [
        'portfolio_performance_plan_id', 'accrual_date', 'scheduled_at', 'planned_amount',
        'manual_adjustment', 'posted_amount', 'posted_at', 'adjusted_by', 'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'scheduled_at' => 'datetime',
            'planned_amount' => 'decimal:2',
            'manual_adjustment' => 'decimal:2',
            'posted_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo { return $this->belongsTo(PortfolioPerformancePlan::class, 'portfolio_performance_plan_id'); }
    public function adjustedBy(): BelongsTo { return $this->belongsTo(User::class, 'adjusted_by'); }
}
