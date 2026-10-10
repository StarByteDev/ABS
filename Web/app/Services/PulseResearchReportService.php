<?php
namespace App\Services;

use App\Models\PulseSignalValidation;
use App\Models\PulseStrategy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** Frozen strategy attribution: one research signal may credit multiple contributors. */
class PulseResearchReportService
{
    public function __construct(
        private readonly PulseStrategyAnalyticsService $analytics,
        private readonly PulseAdaptiveStrategyService $adaptive
    ) {}

    public function report(int $days=30, ?array $allowedSlugs=null): array
    {
        $days=in_array($days,[1,7,30,90],true)?$days:30;
        $from=now()->subDays($days-1)->startOfDay();
        $to=now()->endOfDay();
        $catalog=Schema::hasTable('pulse_strategies')
            ? PulseStrategy::query()->orderBy('sort_order')->get()
            : collect();
        $strategies=[];
        foreach($catalog as $strategy) {
            if ($allowedSlugs!==null && !in_array((string)$strategy->slug,$allowedSlugs,true)) continue;
            $strategies[(string)$strategy->slug]=[
                'strategy_slug'=>(string)$strategy->slug,'name'=>(string)$strategy->name,
                'enabled'=>(bool)$strategy->is_enabled,'signals'=>0,'entries'=>0,
                'wins'=>0,'losses'=>0,'tp1_hits'=>0,'tp2_hits'=>0,'tp3_hits'=>0,
                'gross_profit_usd'=>0.0,'gross_loss_usd'=>0.0,'net_pnl_usd'=>0.0,
                'adaptive'=>$this->adaptive->summaryForStrategy($strategy),
            ];
        }
        $global=['resolved_trades'=>0,'wins'=>0,'losses'=>0,'gross_profit_usd'=>0.0,'gross_loss_usd'=>0.0,'net_pnl_usd'=>0.0];
        if (Schema::hasTable('pulse_signal_validations')) {
            // Stream validations to prevent 90-day reports exhausting HostGator PHP memory.
            PulseSignalValidation::query()->whereNull('user_id')->whereBetween('generated_at',[$from,$to])
                ->orderBy('id')->chunkById(300, function($validations) use (&$strategies,&$global,$allowedSlugs) {
                    foreach ($validations as $v) {
                        $pnl=$this->analytics->virtualPnlForValidation($v);
                        if ($pnl!==null) {
                            $global['resolved_trades']++;
                            $global[$pnl>=0?'wins':'losses']++;
                            $global[$pnl>=0?'gross_profit_usd':'gross_loss_usd']+=abs($pnl);
                            $global['net_pnl_usd']+=$pnl;
                        }
                        $contributors=[];
                        foreach((array)$v->strategy_snapshot as $item) {
                            if(!is_array($item)||isset($item['_meta'])||(float)($item['points']??0)<=0)continue;
                            $slug=(string)($item['slug']??'');
                            $bias=strtoupper((string)($item['bias']??'NEUTRAL'));
                            if($slug==='' || isset($contributors[$slug]) || !in_array($bias,[strtoupper((string)$v->direction),'NEUTRAL'],true))continue;
                            if($allowedSlugs!==null && !in_array($slug,$allowedSlugs,true))continue;
                            if(!isset($strategies[$slug]))continue;
                            $contributors[$slug]=true;
                            $strategies[$slug]['signals']++;
                            if ($v->entry_hit_at) $strategies[$slug]['entries']++;
                            foreach([1,2,3] as $tp) if((int)$v->highest_tp_level_hit>=$tp)$strategies[$slug]['tp'.$tp.'_hits']++;
                            if ($pnl!==null) {
                                $strategies[$slug][$pnl>=0?'wins':'losses']++;
                                $strategies[$slug][$pnl>=0?'gross_profit_usd':'gross_loss_usd']+=abs($pnl);
                                $strategies[$slug]['net_pnl_usd']+=$pnl;
                            }
                        }
                    }
                });
        }
        $out=[];
        foreach($strategies as $row) {
            $decisive=$row['wins']+$row['losses'];
            $row['resolved_trades']=$decisive;
            $row['win_rate_pct']=$decisive>0?round(100*$row['wins']/$decisive,2):null;
            $row['virtual_capital_tested_usd']=$decisive*100;
            $row['roi_pct']=$decisive>0?round(100*$row['net_pnl_usd']/($decisive*100),4):null;
            foreach(['gross_profit_usd','gross_loss_usd','net_pnl_usd'] as $key)$row[$key]=round($row[$key],2);
            $out[]=$row;
        }
        usort($out,fn($a,$b)=>$b['net_pnl_usd']<=>$a['net_pnl_usd']);
        $global['capital_tested_usd']=$global['resolved_trades']*100;
        $global['roi_pct']=$global['resolved_trades']>0?round($global['net_pnl_usd']/$global['capital_tested_usd']*100,4):null;
        foreach(['gross_profit_usd','gross_loss_usd','net_pnl_usd'] as $key)$global[$key]=round($global[$key],2);
        return [
            'period'=>['from'=>$from->toDateString(),'to'=>$to->toDateString(),'days'=>$days],
            'overall'=>$global,'strategies'=>$out,
            'methodology'=>'Research-only, unleveraged $100 per resolved TP/SL; no fees/funding/slippage. Per-strategy attribution overlaps; NEVER sum strategy rows for overall P/L.',
            'adaptive'=>'Evidence-updated after each resolved validation cycle; bounded weight/RR changes, short-horizon loss throttle, no guaranteed profitability.',
        ];
    }
}
