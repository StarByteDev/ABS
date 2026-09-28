@extends('admin.layout')
@section('title',$account->user->name.' — Private Investor')
@section('heading',$account->user->name)
@section('description','Private Investor account summary and reporting status.')
@section('page-actions')
<a class="pi-btn" href="{{ route('admin.users.show',$account->user) }}">Member Account</a>
@if($supportConversation)
<a class="pi-btn" href="{{ route('admin.support.show',$supportConversation) }}">Open Support</a>
@else
<form method="POST" action="{{ route('admin.support.start',$account->user) }}" style="display:inline">@csrf<button class="pi-btn" type="submit">Message Investor</button></form>
@endif
<a class="pi-btn" href="{{ route('admin.private-investors.investors') }}">All Investors</a>
@endsection
@section('content')
<section class="pi-shell pi-v1570 pi-v1571">
    @include('admin.private-investors._account-tabs')

    @php
        $perf = $performance ?? [];
        $plan = $perf['plan'] ?? null;
        $mtd = (float) ($perf['mtd'] ?? 0);
        $target = (float) ($perf['target'] ?? 0);
        $progress = (float) ($perf['progress'] ?? 0);
        $returnPct = (float) $account->net_contributions > 0 ? ((float) $account->total_profit / (float) $account->net_contributions) * 100 : 0;
        $term = $agreement['term'] ?? null;
        $provisionalTotal = (float)($agreement['posted_total'] ?? 0);
        $flowValues = array_merge(
            $activityChart['deposits'] ?? [0],
            $activityChart['withdrawals'] ?? [0],
            array_map('abs', $activityChart['performance'] ?? [0])
        );
        $flowMax = max(1, max($flowValues ?: [1]));
    @endphp

    <div class="pi-investor-banner">
        <div>
            <span class="pi-kicker">Private Investor</span>
            <h2>{{ $account->account_name }}</h2>
            <p>{{ $account->user->email }} · {{ $account->currency }} · {{ $account->is_active ? 'Active' : 'Paused' }}</p>
        </div>
        <div class="pi-banner-actions">
            <a class="pi-btn primary" href="{{ route('admin.private-investors.investment-setup',$account) }}">Investment Setup</a>
            <a class="pi-btn" href="{{ route('admin.private-investors.account-transactions',$account) }}">Add Transaction</a>
        </div>
    </div>

    <div class="pi-grid pi-grid-5">
        <article class="pi-stat"><small>Reported Capital</small><strong>{{ $account->currency }} {{ number_format((float)$account->current_value,2) }}</strong><em>{{ optional($account->valuation_date)->format('d M Y') ?: 'No valuation date' }}</em></article>
        <article class="pi-stat"><small>Investor Principal</small><strong>{{ $account->currency }} {{ number_format((float)$account->net_contributions,2) }}</strong><em>Current confirmed capital</em></article>
        <article class="pi-stat"><small>Profit Paid / Net P/L</small><strong class="{{ $account->total_profit>=0?'pi-positive':'pi-negative' }}">{{ $account->currency }} {{ number_format((float)$account->total_profit,2) }}</strong><em>{{ number_format($returnPct,2) }}% paid/net return</em></article>
        <article class="pi-stat"><small>Monthly Agreement</small><strong>{{ $term ? number_format((float)$term->monthly_target_rate,2).'%' : 'Not set' }}</strong><em>{{ $term?->effective_from?->format('d M Y') ?: 'Configure investment setup' }}</em></article>
        <article class="pi-stat"><small>Performance To Date</small><strong class="pi-positive">{{ $account->currency }} {{ number_format($provisionalTotal,2) }}</strong><em>{{ $plan ? number_format($progress,1).'% current-month progress' : 'No schedule yet' }}</em></article>
    </div>

    <div class="pi-report-grid pi-report-grid-main">
        <article class="pi-card">
            <div class="pi-card-head"><div><h2>Capital & Performance Activity</h2><span>Last 12 months of posted activity</span></div><a class="pi-link" href="{{ route('admin.private-investors.account-transactions',$account) }}">Transactions →</a></div>
            <div class="pi-flow-chart">
                @foreach($activityChart['labels'] as $i => $label)
                    <div class="pi-flow-column" title="{{ $label }}">
                        <div class="pi-flow-bars">
                            <i class="deposit" style="height:{{ max(2,(($activityChart['deposits'][$i] ?? 0)/$flowMax)*100) }}%"></i>
                            <i class="withdrawal" style="height:{{ max(2,(($activityChart['withdrawals'][$i] ?? 0)/$flowMax)*100) }}%"></i>
                            <i class="performance {{ ($activityChart['performance'][$i] ?? 0)<0?'negative':'' }}" style="height:{{ max(2,(abs($activityChart['performance'][$i] ?? 0)/$flowMax)*100) }}%"></i>
                        </div>
                        <small>{{ explode(' ',$label)[0] }}</small>
                    </div>
                @endforeach
            </div>
            <div class="pi-legend"><span><i class="deposit"></i>Investment</span><span><i class="withdrawal"></i>Capital withdrawn</span><span><i class="performance"></i>Paid / net performance</span></div>
        </article>

        <article class="pi-card">
            <div class="pi-card-head"><div><h2>Account Activity</h2><span>Posted ledger totals</span></div></div>
            <div class="pi-brief-row"><span>Investment entries</span><b>{{ $account->currency }} {{ number_format($activityMetrics['deposits'],2) }}</b></div>
            <div class="pi-brief-row"><span>Capital withdrawn</span><b>{{ $account->currency }} {{ number_format($activityMetrics['withdrawals'],2) }}</b></div>
            <div class="pi-brief-row"><span>Profit paid</span><b class="pi-positive">{{ $account->currency }} {{ number_format($activityMetrics['profit_paid'],2) }}</b></div>
            <div class="pi-brief-row"><span>Admin USD principal basis</span><b>USD {{ number_format($activityMetrics['principal_usd'],2) }}</b></div>
            <div class="pi-brief-row"><span>Admin USD profit paid / net P&amp;L</span><b>USD {{ number_format($activityMetrics['profit_paid_usd'],2) }}</b></div>
            <div class="pi-brief-row"><span>Losses & fees</span><b class="pi-negative">{{ $account->currency }} {{ number_format($activityMetrics['losses'],2) }}</b></div>
            <div class="pi-brief-row"><span>Draft entries</span><b>{{ $activityMetrics['drafts'] }}</b></div>
            <div class="pi-brief-row"><span>Corrected entries</span><b>{{ $activityMetrics['voided'] }}</b></div>
        </article>
    </div>

    <div class="pi-task-grid">
        <a class="pi-task-card" href="{{ route('admin.private-investors.investment-setup',$account) }}"><span>◎</span><div><b>Investment Setup</b><small>Set capital start date and the agreed monthly percentage.</small></div></a>
        <a class="pi-task-card" href="{{ route('admin.private-investors.account-performance',$account) }}"><span>↗</span><div><b>Monthly Progress</b><small>Review automatic daily progress and month-end target.</small></div></a>
        <a class="pi-task-card" href="{{ route('admin.private-investors.account-transactions',$account) }}"><span>⇄</span><div><b>Transactions</b><small>Record capital movements and exceptional manual financial corrections; monthly profit payout is automatic.</small></div></a>
        <a class="pi-task-card" href="{{ route('admin.private-investors.account-statements',$account) }}"><span>▥</span><div><b>Statements</b><small>Review automatically generated monthly investor statements and reconcile when required.</small></div></a>
        <a class="pi-task-card" href="{{ route('admin.private-investors.account-requests',$account) }}"><span>◌</span><div><b>Requests</b><small>Review investment, withdrawal and portfolio requests.</small></div></a>
        <a class="pi-task-card" href="{{ route('admin.private-investors.portfolio-values',$account) }}"><span>⚙</span><div><b>Account Controls</b><small>Reconcile account values only when correcting an identified financial-entry error.</small></div></a>
    </div>

    <div class="pi-layout-2">
        <article class="pi-card">
            <div class="pi-card-head"><h2>Recent Activity</h2><a class="pi-btn" href="{{ route('admin.private-investors.account-transactions',$account) }}">Manage</a></div>
            @if($transactions->isEmpty())
                <div class="pi-empty compact">No transactions recorded.</div>
            @else
                @foreach($transactions->take(6) as $tx)
                    <div class="pi-admin-investor {{ $tx->status==='voided'?'is-voided':'' }}">
                        <div><b>{{ match($tx->type){'deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit Paid','loss'=>'Loss','fee'=>'Fee','adjustment'=>'Adjustment',default=>ucfirst($tx->type)} }}</b><small>{{ optional($tx->transaction_date)->format('d M Y') }} · {{ $tx->reference ?: 'No reference' }} · {{ ucfirst($tx->status) }}</small></div>
                        <strong class="{{ in_array($tx->type,['profit','deposit'])?'pi-positive':(in_array($tx->type,['loss','fee','withdrawal'])?'pi-negative':'') }}">{{ $account->currency }} {{ number_format((float)$tx->amount,2) }}</strong>
                    </div>
                @endforeach
            @endif
        </article>

        <article class="pi-card">
            <div class="pi-card-head"><h2>Recent Requests</h2><a class="pi-btn" href="{{ route('admin.private-investors.account-requests',$account) }}">Review</a></div>
            @if($requests->isEmpty())
                <div class="pi-empty compact">No requests.</div>
            @else
                @foreach($requests->take(6) as $r)
                    <div class="pi-admin-investor"><div><b>{{ ucwords(str_replace('_',' ',$r->type)) }}</b><small>{{ $r->created_at->format('d M Y · H:i') }} @if($r->amount) · {{ $r->currency }} {{ number_format((float)$r->amount,2) }}@endif</small></div><span class="pi-status {{ $r->status }}">{{ str_replace('_',' ',$r->status) }}</span></div>
                @endforeach
            @endif
        </article>
    </div>
</section>
@endsection
