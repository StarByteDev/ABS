@php
    $steps = [
        1=>['Price Source & Schedule','admin.pulse.price-source'],
        2=>['Latest Market Prices','admin.pulse.latest-prices'],
        3=>['Price Sync History','admin.pulse.price-history'],
        4=>['Scan & Signals','admin.pulse.scan-signals'],
        5=>['Paper Trades','admin.pulse.paper-trades'],
        6=>['Trade Results','admin.pulse.trade-results'],
        7=>['Strategy Performance','admin.pulse.strategy-performance'],
        8=>['Audit & History','admin.pulse.scan-audit'],
    ];
@endphp
<div class="wf-step-head">
    <div class="wf-step-copy"><span>ABS PULSE · STRATEGY VALIDATION</span><h1>{{ $title }}</h1>@if(!empty($subtitle))<p>{{ $subtitle }}</p>@endif</div>
    <div class="wf-step-actions">
        <a class="wf-overview-link" href="{{ route('admin.pulse.strategy-dashboard') }}">Strategy Overview</a>
        @if($step>1)<a href="{{ route($steps[$step-1][1]) }}">← {{ $steps[$step-1][0] }}</a>@endif
        @if($step<8)<a class="next" href="{{ route($steps[$step+1][1]) }}">{{ $steps[$step+1][0] }} →</a>@endif
    </div>
</div>
