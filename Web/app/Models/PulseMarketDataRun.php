<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PulseMarketDataRun extends Model {
 protected $fillable=['status','prices_updated','candle_symbols_updated','validation_symbols_updated','summary','error_message','started_at','completed_at'];
 protected function casts(): array { return ['summary'=>'array','started_at'=>'datetime','completed_at'=>'datetime']; }
}
