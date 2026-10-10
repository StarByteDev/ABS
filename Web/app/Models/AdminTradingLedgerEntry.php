<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminTradingLedgerEntry extends Model
{
    protected $fillable=['entry_date','type','amount','currency','exchange','reference','notes','created_by','voided_at','voided_by','void_reason'];
    protected function casts(): array { return ['entry_date'=>'date','amount'=>'decimal:2','voided_at'=>'datetime']; }
}
