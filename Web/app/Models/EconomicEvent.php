<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EconomicEvent extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'country', 'currency', 'impact', 'event_at', 'previous_value', 'forecast_value', 'actual_value', 'source'];

    protected function casts(): array
    {
        return ['event_at' => 'datetime'];
    }
}
