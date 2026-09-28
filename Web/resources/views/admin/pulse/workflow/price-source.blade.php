@extends('admin.layout')
@section('title','Price Source & Schedule — ABS Admin')
@section('heading','Price Source & Schedule')
@section('description','Choose how ABS keeps the shared market database fresh.')
@section('content')
@include('admin.pulse.workflow.step-head',['step'=>1,'title'=>'Price Source & Schedule','subtitle'=>'Configure one source, one cadence and the market universe used by the engine.'])
<section class="wf-panel wf-focus-panel">
<form method="POST" action="{{ route('admin.pulse.strategy-dashboard.engine') }}" class="wf-source-form">@csrf @method('PUT')<input type="hidden" name="enabled" value="{{ $research['enabled']?'true':'false' }}">
    <div class="wf-source-grid">
        @foreach([['cron','Production Cron','HostGator / shared hosting','Runs from server cron; minimum 60 seconds.'],['internal','ABS Internal Scheduler','Local PC / VPS','Runs inside the app with supported sub-minute cadence.'],['manual','Manual Only','Testing / controlled runs','Nothing runs until you press refresh or run cycle.']] as $mode)
        <label class="wf-source-option {{ $profile['mode']===$mode[0]?'selected':'' }}"><input type="radio" name="mode" value="{{ $mode[0] }}" {{ $profile['mode']===$mode[0]?'checked':'' }}><span>{{ $loop->iteration }}</span><div><b>{{ $mode[1] }}</b><small>{{ $mode[2] }}</small><p>{{ $mode[3] }}</p></div></label>
        @endforeach
    </div>
    <div class="wf-config-row"><label><span>Sync frequency</span><select name="interval_seconds">@foreach([5=>'5 seconds',30=>'30 seconds',60=>'1 minute',120=>'2 minutes',300=>'5 minutes'] as $value=>$label)<option value="{{ $value }}" {{ (int)$profile['configured_seconds']===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></label><div class="wf-config-fact"><span>Effective frequency</span><b>{{ $profile['effective_seconds'] }} seconds</b><small>{{ $profile['runner_required'] }}</small></div><div class="wf-config-fact"><span>Enabled market pairs</span><b>{{ number_format($enabledPairs) }} / {{ number_format($totalPairs) }}</b><a href="{{ route('admin.pulse.pairs') }}">Manage pairs →</a></div><div class="wf-config-fact"><span>Strategy timeframes</span><b>{{ collect($timeframes)->map(fn($t)=>strtoupper($t))->join(' + ') }}</b><small>Current Pulse engine horizons</small></div></div>
    <div class="wf-form-actions"><button class="button button-primary">Save Configuration</button><span>Live trading remains separate and disabled.</span></div>
</form>
</section>
<section class="wf-two-col"><article class="wf-panel"><div class="wf-panel-head"><div><span class="wf-kicker">MANUAL CONTROL</span><h2>Refresh market prices only</h2></div></div><p class="wf-short-copy">Updates the ABS price database without running a strategy scan.</p><form method="POST" action="{{ route('admin.market-data.refresh') }}">@csrf<button class="button button-ghost">Refresh Prices Now</button></form></article><article class="wf-panel"><div class="wf-panel-head"><div><span class="wf-kicker">FULL TEST</span><h2>Run one complete cycle</h2></div></div><p class="wf-short-copy">Refresh prices, scan strategies and reconcile existing paper trades.</p><form method="POST" action="{{ route('admin.pulse.strategy-dashboard.run-now') }}">@csrf<button class="button button-primary">Run Full Cycle</button></form></article></section>
<section class="wf-status-strip"><div><span>Feed</span><b class="{{ ($health['feed_status']??'')==='healthy'?'good':'warn' }}">{{ strtoupper($health['feed_status']??'offline') }}</b></div><div><span>Last sync</span><b>{{ $lastRun?->completed_at?->format('d M Y · H:i:s') ?? 'Never' }}</b></div><div><span>Prices updated</span><b>{{ number_format((int)($lastRun?->prices_updated ?? 0)) }}</b></div><div><span>Last full cycle</span><b>{{ strtoupper((string)($lastCycle['status']??'never')) }}</b></div></section>
@endsection
