@extends('admin.layout')
@section('title',$account->user->name.' — Monthly Progress')
@section('heading',$account->user->name)
@section('description','Review automatically generated monthly targets and daily provisional performance.')
@section('page-actions')<a class="pi-btn" href="{{ route('admin.private-investors.investment-setup',$account) }}">Investment Setup</a>@endsection
@section('content')
<section class="pi-shell pi-v1570 pi-v1571">
    @include('admin.private-investors._account-tabs')
    @php
        $perf = $performance ?? [];
        $plan = $perf['plan'] ?? null;
        $term = $agreement['term'] ?? null;
        $mtd = (float) ($perf['mtd'] ?? 0);
        $target = (float) ($perf['target'] ?? 0);
        $progress = (float) ($perf['progress'] ?? 0);
        $daily = $perf['daily'] ?? collect();
        $maxDaily = 1.0;
        foreach ($daily as $dailyRow) {
            $dailyValue = $dailyRow->posted_at
                ? (float) $dailyRow->posted_amount
                : ((float) $dailyRow->planned_amount + (float) $dailyRow->manual_adjustment);
            $maxDaily = max($maxDaily, abs($dailyValue));
        }
    @endphp

    <div class="pi-v1570-hero compact">
        <div><span class="pi-kicker">Performance</span><h2>Monthly Progress</h2><p>ABS distributes the agreed target across varied daily amounts. The final month total is controlled against the configured target.</p></div>
        <div class="pi-v1570-status {{ $term&&$term->status==='active'?'good':'warn' }}"><i></i>{{ $term?number_format((float)$term->monthly_target_rate,2).'% agreement':'Agreement required' }}</div>
    </div>

    <div class="pi-v1570-kpis">
        <article><span>Current Target</span><strong>{{ $account->currency }} {{ number_format($target,2) }}</strong><small>{{ $plan?->plan_month?->format('F Y') ?? 'No active month' }}</small></article>
        <article><span>Posted MTD</span><strong class="pi-positive">{{ $account->currency }} {{ number_format($mtd,2) }}</strong><small>{{ number_format($progress,1) }}% of target</small></article>
        <article><span>Performance To Date</span><strong class="pi-positive">{{ $account->currency }} {{ number_format((float)($agreement['posted_total']??0),2) }}</strong><small>Across generated months</small></article>
        <article><span>Indicative Value</span><strong>{{ $account->currency }} {{ number_format((float)($agreement['indicative_value']??$account->current_value),2) }}</strong><small>Reported value + provisional progress</small></article>
    </div>

    @if(!$term)
        <article class="pi-v1570-panel pi-v1570-empty-action"><div><h3>No monthly agreement configured</h3><p>Set the agreed monthly percentage and investment start date once. ABS will create the historical/current schedule automatically.</p></div><a class="pi-btn primary" href="{{ route('admin.private-investors.investment-setup',$account) }}">Configure Investment</a></article>
    @else
        <div class="pi-v1570-main-grid">
            <article class="pi-v1570-panel">
                <div class="pi-v1570-panel-head"><div><h3>{{ $plan?->plan_month?->format('F Y') ?? now()->format('F Y') }} Progress</h3><p>Daily amounts vary, while the complete month remains aligned to the target.</p></div><b>{{ number_format($progress,1) }}%</b></div>
                <div class="pi-v1570-progress"><i style="width:{{ min(100,max(0,$progress)) }}%"></i></div>
                @if($daily->isNotEmpty())
                    <div class="pi-v1570-daily-bars">
                        @foreach($daily as $row)
                            @php $value=(float)($row->posted_at?$row->posted_amount:((float)$row->planned_amount+(float)$row->manual_adjustment)); @endphp
                            <div class="{{ $row->posted_at?'posted':'future' }}" title="{{ $row->accrual_date->format('d M') }} · {{ number_format($value,2) }}"><i style="height:{{ max(4,min(100,(abs($value)/$maxDaily)*100)) }}%"></i><span>{{ $row->accrual_date->format('d') }}</span></div>
                        @endforeach
                    </div>
                @else<div class="pi-empty compact">No daily schedule generated.</div>@endif
                <div class="pi-v1570-summary-strip three">
                    <div><span>Target</span><strong>{{ $account->currency }} {{ number_format($target,2) }}</strong></div>
                    <div><span>Days posted</span><strong>{{ $perf['days_posted']??0 }} / {{ $perf['days_total']??0 }}</strong></div>
                    <div><span>Remaining</span><strong>{{ $account->currency }} {{ number_format($target-$mtd,2) }}</strong></div>
                </div>
            </article>

            <aside class="pi-v1570-panel">
                <div class="pi-v1570-panel-head"><div><h3>Agreement</h3><p>Source of the automatic schedule.</p></div><a href="{{ route('admin.private-investors.investment-setup',$account) }}">Edit</a></div>
                <div class="pi-v1570-calc-row"><span>Monthly rate</span><b>{{ number_format((float)$term->monthly_target_rate,2) }}%</b></div>
                <div class="pi-v1570-calc-row"><span>Effective from</span><b>{{ $term->effective_from?->format('d M Y') }}</b></div>
                <div class="pi-v1570-calc-row"><span>Full-month target now</span><b>{{ $account->currency }} {{ number_format((float)($agreement['full_month_target']??0),2) }}</b></div>
                <div class="pi-v1570-calc-row"><span>Generated months</span><b>{{ number_format((int)($agreement['months']??0)) }}</b></div>
                <div class="pi-note">The first calendar month is prorated from the start date. Later months use the full agreed rate against invested capital, with mid-month capital changes weighted by active days.</div>
            </aside>
        </div>

        <article class="pi-v1570-panel">
            <div class="pi-v1570-panel-head"><div><h3>Monthly History</h3><p>Automatic target and posted provisional result for each generated month.</p></div></div>
            <div class="pi-table-wrap"><table class="pi-table pi-v1570-table"><thead><tr><th>Month</th><th>Active period</th><th>Equivalent capital</th><th>Rate</th><th>Target</th><th>Posted</th><th>Progress</th></tr></thead><tbody>
                @forelse($history as $row)
                    @php $p=$row['plan']; @endphp
                    <tr><td><b>{{ $p->plan_month->format('F Y') }}</b></td><td>{{ $p->accrual_start_date?->format('d M') }} – {{ $p->accrual_end_date?->format('d M') }}</td><td>{{ $account->currency }} {{ number_format((float)$p->base_amount,2) }}</td><td>{{ number_format((float)$p->target_rate,2) }}%</td><td>{{ $account->currency }} {{ number_format((float)$row['target'],2) }}</td><td class="pi-positive">{{ $account->currency }} {{ number_format((float)$row['posted'],2) }}</td><td>{{ number_format(min(100,max(0,(float)$row['progress'])),1) }}%</td></tr>
                @empty<tr><td colspan="7">No generated monthly history yet.</td></tr>@endforelse
            </tbody></table></div>
        </article>

        @if($plan)
        <article class="pi-v1570-panel">
            <details class="pi-v1570-advanced"><summary>Daily adjustments</summary>
                <p>Use only when a particular day needs a manual plus/minus correction. Remaining unposted days automatically rebalance to keep the month within target.</p>
                <div class="pi-daily-admin-grid">
                    @foreach($daily as $row)
                        @php $effective=$row->posted_at?(float)$row->posted_amount:((float)$row->planned_amount+(float)$row->manual_adjustment); @endphp
                        <form method="POST" action="{{ route('admin.private-investors.daily-accruals.update',[$account,$row]) }}" class="pi-daily-admin-day {{ $row->posted_at?'posted':'scheduled' }}">@csrf @method('PATCH')
                            <div class="pi-day-head"><b>{{ $row->accrual_date->format('d') }}</b><span>{{ $row->accrual_date->format('D') }}</span></div><strong class="{{ $effective>=0?'pi-positive':'pi-negative' }}">{{ $effective>=0?'+':'' }}{{ number_format($effective,2) }}</strong><small>{{ $row->posted_at?'Posted '.$row->posted_at->format('H:i'):'Scheduled '.$row->scheduled_at?->format('H:i') }}</small><label>Adjustment<input type="number" step="0.01" name="manual_adjustment" value="{{ $row->manual_adjustment }}"></label><input type="text" name="admin_note" value="{{ $row->admin_note }}" placeholder="Optional note"><button>Apply</button>
                        </form>
                    @endforeach
                </div>
            </details>
        </article>
        @endif
    @endif
</section>
@endsection
