<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PulseMarketPrice extends Model {
 protected $fillable=['symbol','price','change_percent_24h','high_24h','low_24h','volume_24h','source','observed_at'];
 protected function casts(): array { return ['price'=>'decimal:12','change_percent_24h'=>'decimal:6','high_24h'=>'decimal:12','low_24h'=>'decimal:12','volume_24h'=>'decimal:8','observed_at'=>'datetime']; }
}
