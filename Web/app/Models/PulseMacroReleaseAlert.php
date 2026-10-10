<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PulseMacroReleaseAlert extends Model
{
    protected $fillable = ['economic_event_id', 'actual_hash', 'status', 'sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function event(): BelongsTo { return $this->belongsTo(EconomicEvent::class, 'economic_event_id'); }
}
