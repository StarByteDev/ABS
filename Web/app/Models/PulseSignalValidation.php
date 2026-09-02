<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PulseSignalValidation extends Model {
 protected $fillable=['signal_id','user_id','signal_fingerprint','symbol','timeframe','direction','strategy_version','strategy_snapshot','entry_price','stop_loss','take_profit_levels','technical_score','reliability_score','confidence_score','state','outcome','generated_at','entry_hit_at','resolved_at','last_checked_at','entry_observed_price','mfe_price','mae_price','mfe_r','mae_r','duration_seconds','highest_tp_level_hit','market_regime','context','meta'];
 protected function casts(): array { return ['strategy_snapshot'=>'array','take_profit_levels'=>'array','context'=>'array','meta'=>'array','generated_at'=>'datetime','entry_hit_at'=>'datetime','resolved_at'=>'datetime','last_checked_at'=>'datetime','entry_price'=>'decimal:12','stop_loss'=>'decimal:12','technical_score'=>'decimal:2','reliability_score'=>'decimal:2','confidence_score'=>'decimal:2','entry_observed_price'=>'decimal:12','mfe_price'=>'decimal:12','mae_price'=>'decimal:12','mfe_r'=>'decimal:6','mae_r'=>'decimal:6']; }
 public function signal(): BelongsTo { return $this->belongsTo(PulseSignal::class,'signal_id'); }
}
