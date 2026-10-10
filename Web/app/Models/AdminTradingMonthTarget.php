<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminTradingMonthTarget extends Model
{
    protected $fillable=['target_month','target_amount','currency','notes','created_by'];
    protected function casts(): array { return ['target_month'=>'date','target_amount'=>'decimal:2']; }
}
