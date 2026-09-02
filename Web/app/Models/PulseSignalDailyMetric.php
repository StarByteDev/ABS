<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PulseSignalDailyMetric extends Model {
 protected $fillable=['metric_date','user_id','timeframe','direction','signals','entries','wins','losses','ambiguous','expired_no_entry','expired_after_entry','avg_mfe_r','avg_mae_r','avg_duration_seconds'];
 protected function casts(): array { return ['metric_date'=>'date','avg_mfe_r'=>'decimal:6','avg_mae_r'=>'decimal:6']; }
}
