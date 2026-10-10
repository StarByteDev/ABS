<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PulseMacroDeviceDelivery extends Model
{
    protected $fillable = ['alert_id', 'mobile_device_id', 'status', 'attempts', 'error_code', 'sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
}
