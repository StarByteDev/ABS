<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulseAuditLog;
use App\Models\PulseMarketDataRun;
use App\Models\PulseMarketPrice;
use App\Models\PulsePair;
use App\Models\PulseScannerRun;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategy;
use App\Models\PulseStrategyLearningState;
use App\Services\PulseMarketDataService;
use App\Services\PulseRuntimeCadenceService;
use App\Services\PulseStrategyAnalyticsService;
use App\Services\PulseStrategyCycleService;
use App\Services\PulseStrategyResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminStrategyWorkflowController extends Controller
{
    public function overview(
        Request $request,
        PulseMarketDataService $market,
        PulseRuntimeCadenceService $cadence,
        PulseStrategyResearchService $research,
        PulseStrategyCycleService $cycle,
        PulseStrategyAnalyticsService $analytics,
    ) {
        [$range, $from, $to] = $this->range($request);
        $validations = $this->validations($from, $to);
        $outcomes = $this->outcomeSummary($validations);
        $strategyRows = $this->strategyRows($validations, $from, $to, $analytics);

        $scanBase = PulseScannerRun::query()->whereNull('user_id')->whereBetween('created_at', [$from, $to]);
        $scanCount = (clone $scanBase)->count();
        $signals = (int) (clone $scanBase)->sum('signals_created');
        $latestScan = PulseScannerRun::query()->with('bestSignal.validation')->whereNull('user_id')->latest('id')->first();
        $latestMarketRun = Schema::hasTable('pulse_market_data_runs') ? PulseMarketDataRun::query()->latest('id')->first() : null;
        $marketHealth = $market->health();
        $profile = $cadence->profile();
        $researchState = $research->settings();
        $lastCycle = $cycle->lastCycle();
        $topStrategy = $strategyRows->first();
        $latestSignalSet = $latestScan
            ? collect((array) $latestScan->summary)->first(fn($item)=>is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set')
            : null;
        $latestQualified = (int) data_get($latestSignalSet,'qualified_count',$latestScan?->best_signal_id ? 1 : 0);
        $latestNewSignals = (int) data_get($latestSignalSet,'new_signals_created',$latestScan?->signals_created ?? 0);

        $stages = [
            ['icon'=>'⌁','title'=>'Price Source & Schedule','route'=>'admin.pulse.price-source','metric'=>$profile['mode_label'],'sub'=>$profile['automatic_cycles'] ? $profile['effective_seconds'].' sec cadence' : 'Manual runs only','state'=>$profile['automatic_cycles']?'good':'neutral'],
            ['icon'=>'◉','title'=>'Latest Market Prices','route'=>'admin.pulse.latest-prices','metric'=>number_format((int)($marketHealth['latest_price_symbols'] ?? 0)).' markets','sub'=>isset($marketHealth['price_age_seconds']) ? number_format((int)$marketHealth['price_age_seconds']).' sec old' : 'No price data yet','state'=>($marketHealth['feed_status'] ?? '')==='healthy'?'good':'warn'],
            ['icon'=>'↻','title'=>'Price Sync History','route'=>'admin.pulse.price-history','metric'=>$latestMarketRun ? 'Sync #'.$latestMarketRun->id : 'No sync yet','sub'=>$latestMarketRun?->completed_at?->diffForHumans() ?? 'Waiting for first sync','state'=>$latestMarketRun?->status==='completed'?'good':($latestMarketRun?'warn':'neutral')],
            ['icon'=>'⌁','title'=>'Scan & Signals','route'=>'admin.pulse.scan-signals','metric'=>$latestScan ? number_format($latestQualified).' latest signals' : 'No scan yet','sub'=>$latestScan ? number_format($latestNewSignals).' new · '.number_format($scanCount).' scans in period' : 'Waiting for first scan','state'=>$latestScan?->status==='completed'?'good':($latestScan?'warn':'neutral')],
            ['icon'=>'↗','title'=>'Paper Trades','route'=>'admin.pulse.paper-trades','metric'=>number_format($outcomes['entries']).' entered','sub'=>number_format($outcomes['waiting']).' waiting · '.number_format($outcomes['open_after_entry']).' open','state'=>$outcomes['entries']>0?'good':'neutral'],
            ['icon'=>'◎','title'=>'Trade Results','route'=>'admin.pulse.trade-results','metric'=>$outcomes['win_rate']===null?'Collecting results':number_format($outcomes['win_rate'],1).'% win rate','sub'=>number_format($outcomes['tp']).' TP · '.number_format($outcomes['sl']).' SL · '.$this->formatR($outcomes['net_r']),'state'=>($outcomes['tp']+$outcomes['sl'])>0?($outcomes['net_r']>=0?'good':'warn'):'neutral'],
            ['icon'=>'◆','title'=>'Strategy Performance','route'=>'admin.pulse.strategy-performance','metric'=>$topStrategy ? $topStrategy['name'] : 'Collecting evidence','sub'=>$topStrategy ? (($topStrategy['win_rate']===null?'—':number_format($topStrategy['win_rate'],1).'%').' win · '.$this->formatR($topStrategy['net_r'])) : 'No resolved evidence yet','state'=>$topStrategy && $topStrategy['net_r']>0?'good':'neutral'],
            ['icon'=>'≡','title'=>'Audit & History','route'=>'admin.pulse.scan-audit','metric'=>ucfirst((string)($lastCycle['status'] ?? 'never')),'sub'=>!empty($lastCycle['completed_at']) ? Carbon::parse($lastCycle['completed_at'])->diffForHumans() : 'Trace begins after first cycle','state'=>($lastCycle['status'] ?? '')==='completed'?'good':(($lastCycle['status'] ?? '')==='failed'?'warn':'neutral')],
        ];

        return view('admin.pulse.workflow.overview', compact(
            'range','from','to','outcomes','strategyRows','scanCount','signals','latestScan','latestMarketRun',
            'marketHealth','profile','researchState','lastCycle','stages','topStrategy','latestQualified','latestNewSignals'
        ));
    }

    public function priceSource(
        PulseMarketDataService $market,
        PulseRuntimeCadenceService $cadence,
        PulseStrategyResearchService $research,
        PulseStrategyCycleService $cycle,
    ) {
        $enabledPairs = Schema::hasTable('pulse_pairs') ? PulsePair::query()->where('is_enabled', true)->count() : 0;
        $totalPairs = Schema::hasTable('pulse_pairs') ? PulsePair::query()->count() : 0;
        return view('admin.pulse.workflow.price-source', [
            'profile'=>$cadence->profile(),
            'research'=>$research->settings(),
            'health'=>$market->health(),
            'lastRun'=>Schema::hasTable('pulse_market_data_runs') ? PulseMarketDataRun::query()->latest('id')->first() : null,
            'lastCycle'=>$cycle->lastCycle(),
            'enabledPairs'=>$enabledPairs,
            'totalPairs'=>$totalPairs,
            'timeframes'=>array_values((array) config('pulse.market_data.scanner_timeframes', ['15m','4h'])),
        ]);
    }

    public function latestPrices(Request $request, PulseMarketDataService $market)
    {
        $data = $request->validate(['q'=>['nullable','string','max:30']]);
        $q = strtoupper(trim((string)($data['q'] ?? '')));
        $prices = PulseMarketPrice::query()
            ->when($q !== '', fn($query) => $query->where('symbol','like','%'.$q.'%'))
            ->orderBy('symbol')->paginate(100)->withQueryString();

        $lastRun = Schema::hasTable('pulse_market_data_runs') ? PulseMarketDataRun::query()->latest('id')->first() : null;
        return view('admin.pulse.workflow.latest-prices', [
            'prices'=>$prices,'q'=>$q,'health'=>$market->health(),'lastRun'=>$lastRun,
        ]);
    }

    public function priceHistory(Request $request)
    {
        $data = $request->validate(['status'=>['nullable',Rule::in(['completed','failed','running'])]]);
        $status = (string)($data['status'] ?? '');
        $base = PulseMarketDataRun::query()->when($status !== '', fn($q) => $q->where('status',$status));
        $runs = $base->orderByDesc('id')->paginate(30)->withQueryString();
        $summary = [
            'total'=>PulseMarketDataRun::query()->count(),
            'completed'=>PulseMarketDataRun::query()->where('status','completed')->count(),
            'failed'=>PulseMarketDataRun::query()->where('status','failed')->count(),
            'prices'=>(int)PulseMarketDataRun::query()->where('status','completed')->sum('prices_updated'),
        ];
        return view('admin.pulse.workflow.price-history', compact('runs','status','summary'));
    }

    public function priceHistoryShow(PulseMarketDataRun $marketRun)
    {
        $snapshot = collect((array) data_get($marketRun->summary, 'price_snapshot', []))
            ->sortBy('symbol')->values();
        return view('admin.pulse.workflow.price-history-show', compact('marketRun','snapshot'));
    }

    public function scanSignals(Request $request)
    {
        $data = $request->validate([
            'status'=>['nullable',Rule::in(['completed','failed','running'])],
            'signal'=>['nullable',Rule::in(['yes','no'])],
        ]);
        $status = (string)($data['status'] ?? '');
        $signal = (string)($data['signal'] ?? '');
        $query = PulseScannerRun::query()->with(['signals.validation','bestSignal.validation'])->whereNull('user_id')
            ->when($status !== '', fn($q)=>$q->where('status',$status))
            ->when($signal === 'yes', fn($q)=>$q->whereNotNull('best_signal_id'))
            ->when($signal === 'no', fn($q)=>$q->whereNull('best_signal_id'));
        $runs = $query->orderByDesc('id')->paginate(30)->withQueryString();
        $pageIds = $runs->getCollection()->pluck('id');
        $audit = collect();
        if (Schema::hasTable('pulse_audit_logs') && $pageIds->isNotEmpty()) {
            $audit = PulseAuditLog::query()->where('entity_type','PulseScannerRun')->whereIn('entity_id',$pageIds)
                ->whereIn('action',['scanner.completed','scanner.completed_with_warning','scanner.failed'])->orderBy('id')->get()
                ->groupBy('entity_id')->map(fn(Collection $rows)=>$rows->last());
        }

        $referencedIds = $runs->getCollection()->flatMap(function (PulseScannerRun $run) {
            $set = collect((array) $run->summary)->first(fn($item)=>is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set');
            $ids = collect((array) data_get($set,'signals',[]))->pluck('signal_id');
            return $ids->merge($run->signals->pluck('id'))->push($run->best_signal_id)->filter();
        })->unique()->values();
        $signalIndex = $referencedIds->isEmpty()
            ? collect()
            : \App\Models\PulseSignal::query()->with('validation')->whereIn('id',$referencedIds)->get()->keyBy('id');

        $runs->getCollection()->transform(function(PulseScannerRun $run) use ($audit,$signalIndex){
            $summaryRows = collect((array)$run->summary);
            $evaluations = $summaryRows->filter(fn($i)=>is_array($i) && isset($i['symbol']));
            $context = (array)($audit->get($run->id)?->context ?? []);
            $set = $summaryRows->first(fn($i)=>is_array($i) && ($i['type'] ?? '') === 'qualified_signal_set');
            $refs = collect((array) data_get($set,'signals',[]));
            if ($refs->isEmpty()) {
                $refs = $run->signals->map(fn($s)=>['signal_id'=>$s->id,'rank'=>$s->id===$run->best_signal_id?1:null,'persistence'=>'created']);
                if ($run->bestSignal && ! $refs->contains(fn($r)=>(int)($r['signal_id']??0)===(int)$run->bestSignal->id)) {
                    $refs->prepend(['signal_id'=>$run->bestSignal->id,'rank'=>1,'persistence'=>'reused']);
                }
            }
            $signals = $refs->map(function($ref) use($signalIndex,$run){
                $model = $signalIndex->get((int)($ref['signal_id'] ?? 0));
                if (! $model) return null;
                $v = $model->validation;
                $state = $v?->outcome ?: ($v?->entry_hit_at ? 'open' : 'waiting_entry');
                return [
                    'model'=>$model,
                    'rank'=>(int)($ref['rank'] ?? ($model->id===$run->best_signal_id?1:0)),
                    'persistence'=>(string)($ref['persistence'] ?? ($model->scanner_run_id===$run->id?'created':'reused')),
                    'state'=>$state,
                ];
            })->filter()->sortBy(fn($r)=>$r['rank'] ?: 999)->values();
            $entered = $signals->filter(fn($r)=>$r['model']->validation?->entry_hit_at)->count();
            $resolved = $signals->filter(fn($r)=>$r['model']->validation?->resolved_at)->count();
            $waiting = $signals->filter(fn($r)=>!$r['model']->validation?->entry_hit_at && !$r['model']->validation?->resolved_at)->count();
            $qualified = (int)($context['qualified_candidates'] ?? data_get($set,'qualified_count', $run->best_signal_id ? 1 : 0));
            return [
                'run'=>$run,
                'markets'=>max((int)$run->pairs_scanned,$evaluations->pluck('symbol')->filter()->unique()->count()),
                'evaluations'=>(int)($context['market_timeframes_evaluated'] ?? $evaluations->count()),
                'qualified'=>$qualified,
                'new_signals'=>(int)$run->signals_created,
                'reused_signals'=>(int)data_get($set,'reused_open_setups',max(0,$signals->count()-(int)$run->signals_created)),
                'signals'=>$signals,
                'legacy_unstored'=>$set === null ? max(0,$qualified-$signals->count()) : 0,
                'entered'=>$entered,
                'resolved'=>$resolved,
                'waiting'=>$waiting,
                'duration'=>$run->started_at && $run->completed_at ? $run->started_at->diffInSeconds($run->completed_at) : null,
            ];
        });

        $validationBase = PulseSignalValidation::query()->whereNull('user_id');
        $summary = [
            'total'=>PulseScannerRun::query()->whereNull('user_id')->count(),
            'completed'=>PulseScannerRun::query()->whereNull('user_id')->where('status','completed')->count(),
            'signals'=>\App\Models\PulseSignal::query()->whereNull('user_id')->count(),
            'waiting'=>(clone $validationBase)->whereNull('entry_hit_at')->whereNull('resolved_at')->count(),
            'entered'=>(clone $validationBase)->whereNotNull('entry_hit_at')->count(),
        ];
        return view('admin.pulse.workflow.scan-signals', compact('runs','status','signal','summary'));
    }

    public function paperTrades(Request $request, PulseRuntimeCadenceService $cadence)
    {
        $data = $request->validate([
            'state'=>['nullable',Rule::in(['open','closed'])],
            'direction'=>['nullable',Rule::in(['LONG','SHORT'])],
        ]);
        $state = (string)($data['state'] ?? '');
        $direction = (string)($data['direction'] ?? '');
        $query = PulseSignalValidation::query()->with('signal')->whereNull('user_id')->whereNotNull('entry_hit_at')
            ->when($state==='open', fn($q)=>$q->whereNull('resolved_at'))
            ->when($state==='closed', fn($q)=>$q->whereNotNull('resolved_at'))
            ->when($direction!=='', fn($q)=>$q->where('direction',$direction));
        $trades = $query->orderByDesc('entry_hit_at')->paginate(40)->withQueryString();
        $base = PulseSignalValidation::query()->whereNull('user_id')->whereNotNull('entry_hit_at');
        $waiting = PulseSignalValidation::query()->whereNull('user_id')->whereNull('entry_hit_at')->whereNull('resolved_at')->count();
        $summary = [
            'entered'=>(clone $base)->count(),
            'open'=>(clone $base)->whereNull('resolved_at')->count(),
            'closed'=>(clone $base)->whereNotNull('resolved_at')->count(),
            'long'=>(clone $base)->where('direction','LONG')->count(),
            'short'=>(clone $base)->where('direction','SHORT')->count(),
            'waiting'=>(int)$waiting,
        ];
        $profile = $cadence->profile();
        return view('admin.pulse.workflow.paper-trades', compact('trades','state','direction','summary','profile'));
    }

    public function tradeResults(Request $request, PulseStrategyAnalyticsService $analytics)
    {
        [$range,$from,$to] = $this->range($request, '30d');
        $validations = $this->validations($from,$to);
        $outcomes = $this->outcomeSummary($validations);
        $trend = $this->dailyTrend($validations,$from,$to);
        $simulation = $analytics->simulation($from,$to,[]);
        $recent = $validations->whereNotNull('resolved_at')->sortByDesc('resolved_at')->take(20)->values();
        return view('admin.pulse.workflow.trade-results', compact('range','from','to','outcomes','trend','simulation','recent'));
    }

    public function strategyPerformance(Request $request, PulseStrategyAnalyticsService $analytics)
    {
        [$range,$from,$to] = $this->range($request, '30d');
        $validations = $this->validations($from,$to);
        $rows = $this->strategyRows($validations,$from,$to,$analytics);
        $top = $rows->first();
        $positive = $rows->filter(fn($r)=>(int)$r['decisive']>=5 && (float)$r['net_r']>0)->count();
        $review = $rows->filter(fn($r)=>(int)$r['decisive']>=5 && (float)$r['net_r']<=0)->count();
        return view('admin.pulse.workflow.strategy-performance', compact('range','from','to','rows','top','positive','review'));
    }

    private function range(Request $request, string $default='7d'): array
    {
        $data = $request->validate(['range'=>['nullable',Rule::in(['1d','7d','30d','90d'])]]);
        $range=(string)($data['range'] ?? $default); $to=now()->endOfDay();
        $from=match($range){'1d'=>now()->startOfDay(),'30d'=>now()->subDays(29)->startOfDay(),'90d'=>now()->subDays(89)->startOfDay(),default=>now()->subDays(6)->startOfDay()};
        return [$range,$from,$to];
    }

    private function validations(Carbon $from, Carbon $to): Collection
    {
        if (!Schema::hasTable('pulse_signal_validations')) return collect();
        return PulseSignalValidation::query()->with('signal')->whereNull('user_id')->whereBetween('generated_at',[$from,$to])->orderBy('generated_at')->get();
    }

    private function outcomeSummary(Collection $rows): array
    {
        $tp=$rows->where('outcome','tp')->count(); $sl=$rows->where('outcome','sl')->count();
        $amb=$rows->where('outcome','ambiguous')->count(); $expNo=$rows->where('outcome','expired_no_entry')->count(); $expAfter=$rows->where('outcome','expired_after_entry')->count();
        $entries=$rows->whereNotNull('entry_hit_at')->count(); $openAfter=$rows->whereNotNull('entry_hit_at')->whereNull('resolved_at')->count();
        $waiting=$rows->whereNull('entry_hit_at')->whereNull('resolved_at')->count(); $decisive=$tp+$sl;
        $gross=0.0; foreach($rows->where('outcome','tp') as $row) $gross += $this->tpR($row); $net=$gross-(float)$sl;
        return ['signals'=>$rows->count(),'entries'=>$entries,'waiting'=>$waiting,'open_after_entry'=>$openAfter,'tp'=>$tp,'sl'=>$sl,'ambiguous'=>$amb,'expired_no_entry'=>$expNo,'expired_after_entry'=>$expAfter,'void'=>$amb+$expNo+$expAfter,'win_rate'=>$decisive?($tp/$decisive)*100:null,'net_r'=>$net,'profit_factor'=>$sl>0?$gross/$sl:($gross>0?INF:null),'expectancy_r'=>$decisive?$net/$decisive:null];
    }

    private function strategyRows(Collection $validations, Carbon $from, Carbon $to, PulseStrategyAnalyticsService $analytics): Collection
    {
        $catalog=Schema::hasTable('pulse_strategies')?PulseStrategy::query()->orderBy('sort_order')->get():collect(); $rows=[];
        foreach($catalog as $s) $rows[$s->slug]=['slug'=>$s->slug,'name'=>$s->name,'enabled'=>(bool)$s->is_enabled,'signals'=>0,'entries'=>0,'tp'=>0,'sl'=>0,'void'=>0,'gross'=>0.0];
        foreach($validations as $v){
            $contributors=collect((array)$v->strategy_snapshot)->filter(function($item) use($v){if(!is_array($item)||isset($item['_meta'])||(float)($item['points']??0)<=0)return false;$bias=strtoupper((string)($item['bias']??'NEUTRAL'));return in_array($bias,[strtoupper((string)$v->direction),'NEUTRAL'],true);})->pluck('slug')->filter()->unique();
            foreach($contributors as $slug){ if(!isset($rows[$slug]))$rows[$slug]=['slug'=>$slug,'name'=>str($slug)->replace('-',' ')->title()->toString(),'enabled'=>true,'signals'=>0,'entries'=>0,'tp'=>0,'sl'=>0,'void'=>0,'gross'=>0.0]; $rows[$slug]['signals']++; if($v->entry_hit_at)$rows[$slug]['entries']++; if($v->outcome==='tp'){ $rows[$slug]['tp']++; $rows[$slug]['gross']+=$this->tpR($v);} elseif($v->outcome==='sl')$rows[$slug]['sl']++; elseif(in_array($v->outcome,['ambiguous','expired_no_entry','expired_after_entry'],true))$rows[$slug]['void']++; }
        }
        $profit=$analytics->strategyProfitability($from,$to,[])->keyBy('strategy_slug');
        $learning=Schema::hasTable('pulse_strategy_learning_states')?PulseStrategyLearningState::query()->where('market_regime','ALL')->get()->groupBy('strategy_slug'):collect();
        return collect(array_values($rows))->map(function($r) use($profit,$learning){$d=$r['tp']+$r['sl'];$net=$r['gross']-$r['sl'];$states=$learning->get($r['slug'],collect());$samples=(int)$states->sum('sample_size');$den=max(1,$states->sum(fn($s)=>max(1,(int)$s->sample_size)));$rel=$states->isEmpty()?null:$states->sum(fn($s)=>(float)$s->reliability_score*max(1,(int)$s->sample_size))/$den;$p=$profit->get($r['slug'],[]);return $r+['decisive'=>$d,'win_rate'=>$d?($r['tp']/$d)*100:null,'net_r'=>$net,'reliability'=>$rel,'samples'=>$samples,'model_return_pct'=>isset($p['model_return_pct'])?(float)$p['model_return_pct']:null,'expectancy_r'=>isset($p['model_expectancy_r'])?(float)$p['model_expectancy_r']:($d?$net/$d:null)];})->sortByDesc(fn($r)=>((float)$r['net_r']*100000)+(float)($r['win_rate']??0))->values();
    }

    private function dailyTrend(Collection $validations, Carbon $from, Carbon $to): Collection
    {
        $days=[];$c=$from->copy()->startOfDay();$end=$to->copy()->startOfDay();while($c->lte($end)&&count($days)<120){$days[$c->toDateString()]=['date'=>$c->toDateString(),'label'=>$c->format('d M'),'tp'=>0,'sl'=>0,'void'=>0,'net_r'=>0.0];$c->addDay();}
        foreach($validations as $v){$d=($v->generated_at?:$v->created_at)?->toDateString();if(!$d||!isset($days[$d]))continue;if($v->outcome==='tp'){$days[$d]['tp']++;$days[$d]['net_r']+=$this->tpR($v);}elseif($v->outcome==='sl'){$days[$d]['sl']++;$days[$d]['net_r']-=1;}elseif(in_array($v->outcome,['ambiguous','expired_no_entry','expired_after_entry'],true))$days[$d]['void']++;}
        $cum=0.0;return collect(array_values($days))->map(function($r) use(&$cum){$cum+=$r['net_r'];$r['net_r']=round($r['net_r'],4);$r['cumulative_r']=round($cum,4);return $r;});
    }

    private function tpR(PulseSignalValidation $row): float
    {
        $entry=(float)$row->entry_price;$stop=(float)$row->stop_loss;$risk=abs($entry-$stop);if($risk<=0)return 0.0;$levels=array_values(array_filter(array_map('floatval',(array)$row->take_profit_levels),fn($v)=>$v>0));$target=$levels!==[]?(float)end($levels):0.0;return $target>0?abs($target-$entry)/$risk:0.0;
    }

    private function formatR(float $r): string { return ($r>=0?'+':'').number_format($r,2).'R'; }
}
