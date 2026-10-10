<?php
namespace App\Services;
use App\Models\AdminTradingLedgerEntry;
use App\Models\AdminTradingMonthTarget;
use App\Models\PortfolioPerformancePlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
class AdminTradingLedgerService
{
    public function month(Carbon $month): array
    {
        $start=$month->copy()->startOfMonth(); $end=$month->copy()->endOfMonth();
        $before=AdminTradingLedgerEntry::query()->whereNull('voided_at')->whereDate('entry_date','<',$start->toDateString())->get();
        $rows=AdminTradingLedgerEntry::query()->whereNull('voided_at')->whereBetween('entry_date',[$start->toDateString(),$end->toDateString()])->orderBy('entry_date')->orderBy('id')->get();
        $opening=$this->balanceEffect($before);
        $closing=round($opening+$this->balanceEffect($rows),2);
        $tradingPnl=round((float)$rows->where('type','pnl')->sum('amount')-(float)$rows->where('type','fee')->sum('amount'),2);
        $cashFlow=round((float)$rows->where('type','deposit')->sum('amount')-(float)$rows->where('type','withdrawal')->sum('amount'),2);
        $target=AdminTradingMonthTarget::query()->whereDate('target_month',$start->toDateString())->first();
        $investorTarget=$this->investorTargetUsd($start);
        $targetAmount=(float)($target?->target_amount ?? 0);
        $targetProgress=$targetAmount>0 ? ($tradingPnl/$targetAmount)*100 : 0.0;
        $coverage=$investorTarget>0 ? ($tradingPnl/$investorTarget)*100 : 0.0;
        return compact('start','end','rows','opening','closing','tradingPnl','cashFlow','target','investorTarget','targetProgress','coverage');
    }

    public function history(int $months=12): Collection
    {
        $out=collect();
        for($i=0;$i<$months;$i++) {
            $m=now()->startOfMonth()->subMonths($i);
            $row=$this->month($m);
            $out->push(['month'=>$m->copy(),'opening'=>$row['opening'],'closing'=>$row['closing'],'trading_pnl'=>$row['tradingPnl'],'target'=>(float)($row['target']?->target_amount ?? 0),'investor_target'=>$row['investorTarget'],'cash_flow'=>$row['cashFlow']]);
        }
        return $out;
    }

    public function balanceEffect(Collection $rows): float
    {
        return round((float)$rows->sum(function($r){ return match($r->type){
            'opening','deposit'=>(float)$r->amount,
            'withdrawal','fee'=>-(float)$r->amount,
            'pnl','adjustment'=>(float)$r->amount,
            default=>0
        }; }),2);
    }

    private function investorTargetUsd(Carbon $month): float
    {
        return round((float)PortfolioPerformancePlan::query()->with('account')->whereDate('plan_month',$month->toDateString())->get()->sum(function($plan){
            if ($plan->target_amount_usd !== null) return (float)$plan->target_amount_usd;
            $account=$plan->account; if(!$account) return 0;
            if(strtoupper((string)$account->currency)==='USD') return (float)$plan->target_amount;
            $local=(float)$account->net_contributions; $usd=(float)($account->net_contributions_usd ?? 0);
            if($local<=0 || $usd<=0) return 0;
            return (float)$plan->target_amount * ($usd/$local);
        }),2);
    }
}
