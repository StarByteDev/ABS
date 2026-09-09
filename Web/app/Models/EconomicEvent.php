<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EconomicEvent extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'country', 'currency', 'impact', 'event_at', 'previous_value', 'forecast_value', 'actual_value', 'source', 'provider_event_id', 'source_url', 'crypto_impact', 'easy_explanation', 'crypto_impact_summary', 'is_crypto_relevant', 'synced_at'];

    protected function casts(): array
    {
        return ['event_at' => 'datetime', 'synced_at' => 'datetime', 'is_crypto_relevant' => 'boolean'];
    }
}
