@extends('admin.layout')
@section('title','Pulse Strategy Overview — ABS Admin')
@section('heading','Pulse Strategy Overview')
@section('description','A clear view of market data, strategy scans, paper trades and validation results.')
@section('content')
@php
    $fmtR=fn($v)=>(($v??0)>=0?'+':'').number_format((float)($v??0),2).'R';
    $engineGood=($marketHealth['feed_status']??'')==='healthy' && in_array(strtolower((string)($researchState['last_status']??'')),['completed','running','never'],true);
    $decisive=(int)$outcomes['tp']+(int)$outcomes['sl'];
    $entryRate=(int)$outcomes['signals']>0 ? ((int)$outcomes['entries']/(int)$outcomes['signals'])*100 : null;
@endphp

<section class="sv-hero">
    <div class="sv-hero-copy">
        <span class="wf-kicker">ABS PULSE · VALIDATION ENGINE</span>
        <h1>Pulse Strategy Overview</h1>
        <p>Follow the complete validation flow from synchronized market prices to strategy performance.</p>
    </div>
    <div class="sv-hero-actions">
        <form method="POST" action="{{ route('admin.pulse.strategy-dashboard.run-now') }}">@csrf<button class="button button-primary">Run Full Cycle</button></form>
        <a class="button button-ghost" href="{{ route('admin.pulse.price-source') }}">Engine Setup</a>
    </div>
</section>

<section class="sv-status-row">
    <article><span>ENGINE STATUS</span><strong class="{{ $engineGood?'good':'warn' }}">{{ $engineGood?'HEALTHY':'CHECK' }}</strong><small>{{ $profile['mode_label'] }}</small></article>
    <article><span>LATEST SCAN</span><strong>{{ $latestScan ? number_format($latestQualified).' signal'.($latestQualified===1?'':'s') : '—' }}</strong><small>{{ $latestScan ? 'Scan #'.$latestScan->id.' · '.$latestScan->started_at?->diffForHumans() : 'No scan yet' }}</small></article>
    <article><span>PAPER WIN RATE</span><strong>{{ $outcomes['win_rate']===null?'—':number_format($outcomes['win_rate'],1).'%' }}</strong><small>{{ number_format($outcomes['tp']) }} TP · {{ number_format($outcomes['sl']) }} SL</small></article>
    <article><span>NET RESULT</span><strong class="{{ $outcomes['net_r']>=0?'good':'bad' }}">{{ $fmtR($outcomes['net_r']) }}</strong><small>{{ number_format($outcomes['entries']) }} entered paper trades</small></article>
    <article><span>TOP STRATEGY</span><strong>{{ $topStrategy['name'] ?? 'Collecting' }}</strong><small>@if($topStrategy){{ $topStrategy['win_rate']===null?'—':number_format($topStrategy['win_rate'],1).'%' }} win · {{ $fmtR($topStrategy['net_r']) }}@else Waiting for evidence @endif</small></article>
</section>

<section class="sv-section-head">
    <div><span class="wf-kicker">VALIDATION WORKFLOW</span><h2>Open the stage you want to review</h2></div>
    <div class="wf-range-tabs"><a class="{{ $range==='1d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'1d']) }}">Today</a><a class="{{ $range==='7d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'7d']) }}">7D</a><a class="{{ $range==='30d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'30d']) }}">30D</a><a class="{{ $range==='90d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'90d']) }}">90D</a></div>
</section>

<section class="sv-stage-grid">
@foreach($stages as $stage)
<a class="sv-stage-card is-{{ $stage['state'] }}" href="{{ route($stage['route']) }}">
    <span class="sv-stage-icon">{{ $stage['icon'] }}</span>
    <div><h3>{{ $stage['title'] }}</h3><strong>{{ $stage['metric'] }}</strong><small>{{ $stage['sub'] }}</small></div>
    <i>›</i>
</a>
@endforeach
</section>

<section class="sv-bottom-grid">
    <article class="wf-panel sv-performance-card">
        <div class="wf-panel-head"><div><span class="wf-kicker">VALIDATION SNAPSHOT</span><h2>What the current evidence says</h2></div><a href="{{ route('admin.pulse.trade-results') }}">Results →</a></div>
        <div class="sv-metric-grid">
            <div><span>Signals</span><b>{{ number_format($outcomes['signals']) }}</b><small>{{ number_format($outcomes['waiting']) }} waiting entry</small></div>
            <div><span>Entry rate</span><b>{{ $entryRate===null?'—':number_format($entryRate,1).'%' }}</b><small>{{ number_format($outcomes['entries']) }} entered</small></div>
            <div><span>Resolved</span><b>{{ number_format($decisive+$outcomes['void']) }}</b><small>{{ number_format($outcomes['void']) }} ambiguous / expired</small></div>
            <div><span>Open</span><b>{{ number_format($outcomes['open_after_entry']) }}</b><small>paper trades still active</small></div>
        </div>
    </article>

    <article class="wf-panel sv-performance-card">
        <div class="wf-panel-head"><div><span class="wf-kicker">STRATEGY EVIDENCE</span><h2>Best current contributors</h2></div><a href="{{ route('admin.pulse.strategy-performance') }}">All strategies →</a></div>
        @forelse($strategyRows->take(4) as $row)
            <div class="sv-strategy-row"><span><b>{{ $row['name'] }}</b><small>{{ $row['decisive'] }} decisive trades</small></span><em>{{ $row['win_rate']===null?'—':number_format($row['win_rate'],1).'%' }}</em><strong class="{{ $row['net_r']>=0?'good':'bad' }}">{{ $fmtR($row['net_r']) }}</strong></div>
        @empty
            <div class="wf-empty">Strategy evidence will appear after paper trades resolve.</div>
        @endforelse
    </article>
</section>
@endsection
