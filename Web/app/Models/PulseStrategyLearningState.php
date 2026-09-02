<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PulseStrategyLearningState extends Model {
 protected $fillable=['strategy_slug','strategy_version','timeframe','direction','market_regime','sample_size','win_rate','ambiguous_rate','reliability_score','recency_weighted_score','evidence_level','meta','calculated_at'];
 protected function casts(): array { return ['win_rate'=>'decimal:4','ambiguous_rate'=>'decimal:4','reliability_score'=>'decimal:2','recency_weighted_score'=>'decimal:2','meta'=>'array','calculated_at'=>'datetime']; }
}
