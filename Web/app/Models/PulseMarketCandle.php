<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PulseMarketCandle extends Model {
 protected $fillable=['symbol','timeframe','open_time_ms','close_time_ms','open','high','low','close','volume','is_closed','source'];
 protected function casts(): array { return ['open'=>'decimal:12','high'=>'decimal:12','low'=>'decimal:12','close'=>'decimal:12','volume'=>'decimal:8','is_closed'=>'boolean']; }
}
