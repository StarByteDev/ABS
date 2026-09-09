@extends('admin.layout')
@section('title','ABS Admin · Executive Dashboard')
@section('heading','Executive Dashboard')
@section('hide-heading','1')
@section('description','Investor-grade visibility into member growth, Pulse adoption, signal quality, trading outcomes, verified package revenue and operational priorities.')
@section('page-actions')
<form class="abs-exec-date-form" method="GET" action="{{ route('admin.dashboard') }}">
    <label><span>▣</span><input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Report start date"><i>→</i><input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Report end date"></label>
    <select name="timeframe" aria-label="Signal timeframe"><option value="" {{ $timeframe===null?'selected':'' }}>All TF</option><option value="15m" {{ $timeframe==='15m'?'selected':'' }}>15M</option><option value="4h" {{ $timeframe==='4h'?'selected':'' }}>4H</option></select>
    <button type="submit">Apply</button>
    <button type="submit" class="report" formaction="{{ route('admin.dashboard.export') }}">Generate Report</button>
</form>
@endsection
@section('content')
@php
    $signals=(int)$tradingReport['Signals · 30d'];
    $entries=(int)$tradingReport['Entries · 30d'];
    $wins=(int)$tradingReport['TP outcomes · 30d'];
    $losses=(int)$tradingReport['SL outcomes · 30d'];
    $ambiguous=(int)$dashboardOutcomeMix['ambiguous'];
    $pending=(int)$dashboardOutcomeMix['pending'];
    $decisive=$wins+$losses;
    $winRate=$decisive>0?($wins/$decisive*100):null;
    $activePlans=(int)$stats['Active Pulse users'];
    $usdt=(float)($paymentStats['approved_usdt']??0);
    $approvedPayments=(int)($paymentStats['approved_count']??0);
    $pendingPayments=(int)($paymentStats['pending_count']??0);
    $pendingUsdt=(float)($paymentStats['pending_usdt']??0);
    $freeUnlocks=(int)($publicRewardedStats['selected_period']??0);
    $formatMoney=static fn($v)=>number_format((float)$v,2);
@endphp

<script type="application/json" id="dashboard-pulse-trend">{!! json_encode([
    'ariaLabel'=>'Daily signals and final outcomes for the selected reporting period',
    'labels'=>$dashboardTrend->pluck('label')->all(),
    'series'=>[
        ['name'=>'Signals','color'=>'#20c7ef','fill'=>true,'fillColor'=>'rgba(22,197,237,.10)','values'=>$dashboardTrend->pluck('signals')->all()],
        ['name'=>'Entry Hit','color'=>'#30d790','fill'=>false,'values'=>$dashboardTrend->pluck('entries')->all()],
        ['name'=>'TP','color'=>'#f0ad2f','fill'=>false,'values'=>$dashboardTrend->pluck('wins')->all()],
        ['name'=>'SL','color'=>'#ef5769','fill'=>false,'values'=>$dashboardTrend->pluck('losses')->all()],
    ],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="dashboard-outcome-mix">{!! json_encode([
    'ariaLabel'=>'Signal lifecycle distribution for the selected reporting period',
    'centerLabel'=>'TOTAL SIGNALS','centerValue'=>number_format($signals),
    'labels'=>['TP Hit','SL Hit','Ambiguous','Pending'],
    'values'=>[$wins,$losses,$ambiguous,$pending],
    'colors'=>['#2bd48b','#ef5769','#f0ad2f','#6d7b92'],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="dashboard-member-growth">{!! json_encode([
    'ariaLabel'=>'New member registrations across the selected reporting period',
    'labels'=>$memberGrowthTrend->pluck('label')->all(),
    'series'=>[['name'=>'New Members','color'=>'#20c7ef','render'=>'bar','fill'=>false,'values'=>$memberGrowthTrend->pluck('registrations')->all()]],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>

<div class="abs-exec-dashboard">
    <section class="abs-exec-kpis" aria-label="Executive indicators">
        @foreach([
            ['♙','cyan','Active Members',$stats['Active accounts'],'registered accounts'],
            ['▣','gold','Active Pulse Plans',$activePlans,'current entitlements'],
            ['⌁','cyan','Signals Today',$tradingReport['Signals today'],'generated today'],
            ['◎','green','Decisive Win Rate',$winRate===null?'—':number_format($winRate,1).'%',number_format($wins).' TP / '.number_format($losses).' SL'],
            ['◷','red','Plans Expiring Soon',$stats['Expiring in 7 days'],'within 7 days'],
            ['▶','purple','Free Signal Unlocks',$freeUnlocks,'rewarded ads · selected period'],
            ['$','green','USDT Approved',$formatMoney($usdt),'verified package payments'],
            ['!','red','Support Alerts',$operations['New support enquiries'],'new enquiries'],
        ] as [$icon,$tone,$label,$value,$note])
        <article class="abs-exec-kpi"><span class="{{ $tone }}">{{ $icon }}</span><div><small>{{ $label }}</small><strong>{{ is_numeric($value)?number_format((float)$value, is_float($value)?2:0):$value }}</strong><em>{{ $note }}</em></div></article>
        @endforeach
    </section>

    <section class="abs-exec-primary-grid">
        <article class="abs-exec-card abs-exec-intelligence">
            <header><div><h2><span>▥</span> Pulse Intelligence</h2><p>Signal production, entry confirmation and validated outcomes.</p></div><a href="{{ route('admin.pulse.intelligence',['from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">View intelligence →</a></header>
            <div class="abs-exec-chart" data-admin-chart data-chart-source="#dashboard-pulse-trend" data-chart-type="line"></div>
            <div class="abs-exec-intelligence-footer">
                <div><i class="green">♕</i><span>TP Hit<b>{{ number_format($wins) }}</b><small>{{ $signals?number_format($wins/$signals*100,1):'0.0' }}%</small></span></div>
                <div><i class="red">×</i><span>SL Hit<b>{{ number_format($losses) }}</b><small>{{ $signals?number_format($losses/$signals*100,1):'0.0' }}%</small></span></div>
                <div><i class="gold">?</i><span>Ambiguous<b>{{ number_format($ambiguous) }}</b><small>excluded from win rate</small></span></div>
                <div><i class="cyan">◷</i><span>Pending<b>{{ number_format($pending) }}</b><small>awaiting validation</small></span></div>
                <div><i class="gold">★</i><span>Avg Confidence<b>{{ $strategyConfidence->isNotEmpty()?number_format((float)$strategyConfidence->avg('confidence'),1).'%':'—' }}</b><small>learned reliability</small></span></div>
            </div>
        </article>

        <article class="abs-exec-card abs-exec-oversight">
            <header><div><h2><span>◎</span> Signal Oversight</h2><p>Operational status across the validation lifecycle.</p></div><a href="{{ route('admin.pulse.signals') }}">View all signals →</a></header>
            <div class="abs-exec-oversight-body">
                <div class="abs-exec-donut" data-admin-chart data-chart-source="#dashboard-outcome-mix" data-chart-type="donut"></div>
                <div class="abs-exec-status-list">
                    <div><i class="green"></i><span>TP Hit</span><b>{{ number_format($wins) }}</b></div>
                    <div><i class="red"></i><span>SL Hit</span><b>{{ number_format($losses) }}</b></div>
                    <div><i class="gold"></i><span>Ambiguous</span><b>{{ number_format($ambiguous) }}</b></div>
                    <div><i class="muted"></i><span>Pending</span><b>{{ number_format($pending) }}</b></div>
                    <div><i class="cyan"></i><span>Entry Hit</span><b>{{ number_format($entries) }}</b></div>
                    <div><i class="blue"></i><span>Generated</span><b>{{ number_format($signals) }}</b></div>
                </div>
            </div>
        </article>

        <aside class="abs-exec-card abs-exec-actions">
            <header><div><h2><span>ϟ</span> Quick Actions</h2><p>Frequently used administration controls.</p></div></header>
            <a class="primary" href="{{ route('admin.pulse.signals') }}"><i>▶</i><span>Review Signals</span></a>
            <a href="{{ route('admin.pulse.memberships') }}"><i>$</i><span>Verify Package Payments</span></a>
            <a href="{{ route('admin.pulse.trades') }}"><i>▥</i><span>Review Trades</span></a>
            <a href="{{ route('admin.pulse.plans') }}"><i>▣</i><span>Create / Edit Package</span></a>
            <a href="{{ route('admin.pulse.rewarded-signals') }}"><i>▶</i><span>Rewarded Signal Ads</span></a>
        </aside>
    </section>

    <section class="abs-exec-secondary-grid">
        <article class="abs-exec-card abs-exec-trades">
            <header><div><h2><span>↔</span> Latest Trades</h2><p>Most recent Pulse execution records.</p></div><a href="{{ route('admin.pulse.trades') }}">View all trades →</a></header>
            <div class="abs-exec-table-wrap"><table><thead><tr><th>Pair</th><th>Direction</th><th>Entry</th><th>Status</th><th>Result</th><th>Confidence</th><th>Opened</th></tr></thead><tbody>
                @forelse($latestTrades as $trade)
                @php($result=(float)($trade->realized_pnl ?? $trade->unrealized_pnl ?? 0))
                <tr><td><b>{{ $trade->symbol }}</b></td><td class="{{ strtoupper($trade->side)==='BUY'?'positive':'negative' }}">{{ strtoupper($trade->side)==='BUY'?'LONG':'SHORT' }}</td><td>{{ rtrim(rtrim(number_format((float)$trade->entry_price,8,'.',','),'0'),'.') }}</td><td><span class="abs-exec-pill {{ in_array($trade->status,['open','pending','submitting'])?'info':'good' }}">{{ ucwords(str_replace('_',' ',$trade->status)) }}</span></td><td class="{{ $result>0?'positive':($result<0?'negative':'') }}">{{ $result==0?'—':(($result>0?'+':'').number_format($result,2)) }}</td><td>{{ $trade->signal?->confidence_score!==null?number_format((float)$trade->signal->confidence_score,1).'%':'—' }}</td><td>{{ ($trade->opened_at??$trade->created_at)?->format('d M H:i') }}</td></tr>
                @empty<tr><td colspan="7" class="empty">No trade activity recorded yet.</td></tr>@endforelse
            </tbody></table></div>
        </article>

        <article class="abs-exec-card abs-exec-strategy">
            <header><div><h2><span>▥</span> Strategy Performance</h2><p>Current learned reliability and evidence.</p></div><a href="{{ route('admin.pulse.intelligence') }}">View details →</a></header>
            <div class="abs-exec-strategy-list">
                @forelse($strategyConfidence as $i=>$row)
                <div><span class="rank">{{ $i+1 }}</span><b>{{ $row['name'] }}</b><span class="meter"><i style="width:{{ min(100,max(0,$row['confidence'])) }}%"></i></span><em>{{ number_format($row['confidence'],1) }}%</em><small>{{ number_format($row['signals']) }} signals</small></div>
                @empty<p class="empty">Strategy evidence is still being collected.</p>@endforelse
            </div>
        </article>

        <article class="abs-exec-card abs-exec-growth">
            <header><div><h2><span>♙</span> Member Growth</h2><p>New registrations in the selected reporting period.</p></div><a href="{{ route('admin.users') }}">View members →</a></header>
            <div class="abs-exec-growth-total"><span>Total Members</span><b>{{ number_format($stats['Total users']) }}</b></div>
            <div class="abs-exec-chart small" data-admin-chart data-chart-source="#dashboard-member-growth" data-chart-type="bar"></div>
        </article>
    </section>

    <section class="abs-exec-bottom-grid">
        <article class="abs-exec-card abs-exec-expiry">
            <header><div><h2><span>◷</span> Plan Expiry Alerts</h2><p>Members whose Pulse access needs renewal attention.</p></div><a href="{{ route('admin.pulse.access') }}">View all →</a></header>
            <div class="abs-exec-expiry-list">
                @forelse($expiryQueue->take(5) as $access)
                @php($days=max(0,(int) ceil(now()->diffInDays($access->ends_at,false))))
                <div><span><b>{{ $access->user?->name ?? 'Member' }}</b><small>{{ $access->plan?->name ?? 'Pulse plan' }}</small></span><em>{{ $access->ends_at?->format('d M Y') }}</em><strong class="{{ $days<=3?'danger':'warn' }}">{{ $days }} days left</strong><a href="{{ $access->user ? route('admin.users.show',$access->user) : route('admin.pulse.access') }}">Open</a></div>
                @empty<p class="empty">No plans expire in the next 30 days.</p>@endforelse
            </div>
        </article>

        <article class="abs-exec-card abs-exec-commerce">
            <header><div><h2><span>$</span> Package Revenue</h2><p>Direct USDT package payments verified by Admin.</p></div><a href="{{ route('admin.pulse.memberships') }}">View payments →</a></header>
            <div class="abs-exec-commerce-grid">
                <div class="abs-exec-commerce-ring"><div><b>${{ number_format($usdt,2) }}</b><small>USDT approved</small></div></div>
                <div class="abs-exec-commerce-stats"><div><span>Approved Payments</span><b>{{ number_format($approvedPayments) }}</b></div><div><span>Awaiting Review</span><b>{{ number_format($pendingPayments) }}</b></div><div><span>Pending Value</span><b>${{ number_format($pendingUsdt,2) }}</b></div><div><span>Free Signal Unlocks</span><b>{{ number_format($freeUnlocks) }}</b></div></div>
            </div>
        </article>

        <article class="abs-exec-card abs-exec-best">
            <header><div><h2><span>♜</span> Best Signal Summary</h2><p>Highest-confidence signal in the selected range.</p></div><a href="{{ route('admin.pulse.signals') }}">View signals →</a></header>
            @if($bestSignal)
            <div class="abs-exec-best-symbol"><span>{{ str_replace('/USDT','',str_replace('USDT','',$bestSignal->symbol)) }}</span><div><b>{{ $bestSignal->symbol }}</b><small>{{ strtoupper($bestSignal->direction) }} · {{ strtoupper($bestSignal->timeframe) }} · {{ $bestSignal->generated_at?->format('d M Y') }}</small></div><strong>{{ number_format((float)($bestSignal->confidence_score ?? $bestSignal->score),1) }}%</strong></div>
            <div class="abs-exec-best-metrics"><div><span>Score</span><b>{{ number_format((float)$bestSignal->score,1) }}</b></div><div><span>Outcome</span><b>{{ strtoupper($bestSignal->validation?->outcome ?? 'Pending') }}</b></div><div><span>Shares</span><b>{{ number_format((int)$bestSignal->share_count) }}</b></div></div>
            @else<p class="empty">No qualifying signal exists in this reporting period.</p>@endif
        </article>
    </section>

    <section class="abs-exec-healthbar">
        @foreach($controlStatus as $control)<a href="{{ $control['route'] }}"><i class="{{ $control['state'] }}"></i><span>{{ $control['label'] }}</span><b>{{ $control['value'] }}</b></a>@endforeach
    </section>

    <footer class="abs-exec-footer"><span>© {{ now()->year }} Alpha Block Solutions. All rights reserved.</span><span><i></i> ABS Pulse · Intelligence · Opportunity · Control</span></footer>
</div>
@endsection
