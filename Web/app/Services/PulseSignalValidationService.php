<?php

namespace App\Services;

use App\Models\PulseSignal;
use App\Models\PulseSignalValidation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PulseSignalValidationService
{
    public function __construct(private readonly PulseMarketDataService $market, private readonly PulseLearningService $learning) {}

    public function process(int $limit = 500): array
    {
        if (! Schema::hasTable('pulse_signal_validations') || ! Schema::hasTable('pulse_market_candles')) {
            return ['processed' => 0, 'resolved' => 0, 'schema_ready' => false];
        }
        $signals = PulseSignal::query()->whereIn('direction',['LONG','SHORT'])
            ->where(function ($q) { $q->whereIn('status',['active','expired'])->orWhereHas('trades'); })
            ->where('generated_at','>=',now()->subDays(2))->orderBy('generated_at')->limit(max(1,$limit))->get();
        $processed=0; $resolved=0; $dates=[];
        foreach ($signals as $signal) {
            $validation = $this->ensureValidation($signal);
            if ($validation->resolved_at) continue;
            if ($this->evaluate($signal,$validation)) { $resolved++; $dates[$validation->fresh()->resolved_at?->toDateString() ?? now()->toDateString()] = true; }
            $processed++;
        }
        foreach (array_keys($dates) as $date) $this->learning->rebuildDate(Carbon::parse($date));
        $this->pruneDetailed();
        return compact('processed','resolved');
    }

    public function ensureValidation(PulseSignal $signal): PulseSignalValidation
    {
        if (! Schema::hasTable('pulse_signal_validations')) {
            throw new RuntimeException('Pulse signal-validation schema is not ready. Run php artisan abs:repair --seed.');
        }
        $levels = $signal->take_profit_levels ?: array_values(array_filter([(float) $signal->take_profit]));
        return PulseSignalValidation::query()->firstOrCreate(['signal_id'=>$signal->id], [
            'user_id'=>$signal->user_id,'signal_fingerprint'=>$signal->signal_fingerprint ?: $this->fingerprint($signal),'symbol'=>$signal->symbol,
            'timeframe'=>$signal->timeframe,'direction'=>$signal->direction,'strategy_version'=>$signal->strategy_version ?: '1.0',
            'strategy_snapshot'=>$signal->strategy_snapshot ?: $signal->strategy_breakdown,'entry_price'=>$signal->entry_price,'stop_loss'=>$signal->stop_loss,
            'take_profit_levels'=>$levels,'technical_score'=>$signal->technical_score ?? $signal->score,'reliability_score'=>$signal->reliability_score,
            'confidence_score'=>$signal->confidence_score,'state'=>'waiting_entry','generated_at'=>$signal->generated_at ?: $signal->created_at,
            'market_regime'=>$this->signalMeta($signal)['market_regime'] ?? null,'context'=>['confidence_label'=>$signal->confidence_label],
        ]);
    }

    private function evaluate(PulseSignal $signal, PulseSignalValidation $v): bool
    {
        $generated = $v->generated_at ?: $signal->generated_at ?: $signal->created_at;
        $from = $v->last_checked_at ? $v->last_checked_at->copy()->subMinute() : $generated->copy();
        $candles = $this->market->minuteCandles($signal->symbol,$from,now());
        $entry=(float)$v->entry_price; $stop=(float)$v->stop_loss; $levels=array_values(array_map('floatval',(array)$v->take_profit_levels));
        $finalTp = $levels !== [] ? (float) end($levels) : (float) $signal->take_profit;
        $risk = abs($entry-$stop);
        $entryMinuteMs = $v->entry_hit_at ? $v->entry_hit_at->copy()->startOfMinute()->getTimestamp()*1000 : null;
        $mfe=(float)($v->mfe_price ?? $entry); $mae=(float)($v->mae_price ?? $entry); $highest=(int)$v->highest_tp_level_hit;

        foreach ($candles as $c) {
            $candleAt=Carbon::createFromTimestampUTC((int) floor($c->open_time_ms/1000));
            $high=(float)$c->high; $low=(float)$c->low;
            if (! $v->entry_hit_at) {
                if ($low <= $entry && $high >= $entry) {
                    $v->entry_hit_at=$candleAt; $v->entry_observed_price=$entry; $v->state='active'; $entryMinuteMs=(int)$c->open_time_ms;
                    // Entry candle is intentionally excluded from TP/SL validation because OHLC cannot prove event order inside the minute.
                    continue;
                }
                continue;
            }
            if ((int)$c->open_time_ms <= (int)$entryMinuteMs) continue;

            if ($v->direction === 'LONG') { $mfe=max($mfe,$high); $mae=min($mae,$low); }
            else { $mfe=min($mfe,$low); $mae=max($mae,$high); }
            foreach ($levels as $idx=>$tp) {
                $hit=$v->direction==='LONG' ? $high >= $tp : $low <= $tp;
                if ($hit) $highest=max($highest,$idx+1);
            }
            $tpHit=$finalTp>0 && ($v->direction==='LONG' ? $high >= $finalTp : $low <= $finalTp);
            $slHit=$stop>0 && ($v->direction==='LONG' ? $low <= $stop : $high >= $stop);
            if ($tpHit && $slHit) return $this->resolve($signal,$v,'ambiguous',$candleAt,$mfe,$mae,$risk,$highest,['reason'=>'same_minute_tp_sl']);
            if ($tpHit) return $this->resolve($signal,$v,'tp',$candleAt,$mfe,$mae,$risk,$highest);
            if ($slHit) return $this->resolve($signal,$v,'sl',$candleAt,$mfe,$mae,$risk,$highest);
        }

        if ($signal->expires_at && now()->greaterThanOrEqualTo($signal->expires_at)) {
            return $this->resolve($signal,$v,$v->entry_hit_at?'expired_after_entry':'expired_no_entry',$signal->expires_at,$mfe,$mae,$risk,$highest);
        }

        $v->mfe_price=$mfe; $v->mae_price=$mae; $v->highest_tp_level_hit=$highest; $v->last_checked_at=now();
        if ($v->entry_hit_at && $risk>0) [$v->mfe_r,$v->mae_r]=$this->rMetrics($v->direction,$entry,$mfe,$mae,$risk);
        $v->save(); return false;
    }

    private function resolve(PulseSignal $signal, PulseSignalValidation $v, string $outcome, Carbon $at, float $mfe, float $mae, float $risk, int $highest, array $meta=[]): bool
    {
        $v->state='resolved'; $v->outcome=$outcome; $v->resolved_at=$at; $v->last_checked_at=now(); $v->mfe_price=$mfe; $v->mae_price=$mae;
        $v->highest_tp_level_hit=$highest; $v->duration_seconds=$v->entry_hit_at ? max(0,$v->entry_hit_at->diffInSeconds($at)) : null;
        if ($v->entry_hit_at && $risk>0) [$v->mfe_r,$v->mae_r]=$this->rMetrics($v->direction,(float)$v->entry_price,$mfe,$mae,$risk);
        $v->meta=array_merge((array)$v->meta,$meta); $v->save();
        if ($signal->status==='active') $signal->update(['status'=>'expired']);
        return true;
    }

    private function rMetrics(string $direction,float $entry,float $mfe,float $mae,float $risk): array
    {
        return $direction==='LONG' ? [($mfe-$entry)/$risk,($entry-$mae)/$risk] : [($entry-$mfe)/$risk,($mae-$entry)/$risk];
    }

    private function signalMeta(PulseSignal $signal): array
    {
        foreach ((array) $signal->strategy_breakdown as $item) {
            if (is_array($item) && isset($item['_meta']) && is_array($item['_meta'])) return $item['_meta'];
        }
        return [];
    }

    private function fingerprint(PulseSignal $s): string
    {
        return hash('sha256',implode('|',[$s->symbol,$s->timeframe,$s->direction,$s->entry_price,$s->stop_loss,$s->take_profit,$s->strategy_version,$s->generated_at?->format('Y-m-d H:i')]));
    }

    private function pruneDetailed(): void
    {
        if (! Schema::hasTable('pulse_signal_validations')) return;
        $days=max(1,(int)config('pulse.validation.detailed_retention_days',7));
        PulseSignalValidation::query()->whereNotNull('resolved_at')->where('resolved_at','<',now()->subDays($days))->delete();
    }
}
