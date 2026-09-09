@php
    $results = collect($page['results']);
    $price = fn ($value) => number_format((float) $value, (float) $value < 1 ? 4 : 2);
    $percent = fn ($value) => ((float)$value >= 0 ? '+' : '').number_format((float)$value,2).'%';
@endphp
<section class="pp-card pp-scanner-results" data-scan-fragment="results">
    <div class="pp-section-head"><h2>Best Signal</h2><span>{{ $page['latest_run'] ? 'Included with active package' : 'Run Find Best Signal to begin' }} · Updated {{ $page['latest_run']?->completed_at?->diffForHumans() ?? 'after next scan' }}</span></div>
    <div class="pp-results-scroll"><table class="pp-table pp-scanner-signal-table"><thead><tr><th>Pair</th><th>24H</th><th>Direction</th><th>Score</th><th>Entry</th><th>Stop Loss</th><th>Take Profit</th><th>Timeframe</th><th class="pp-action-col">Action</th></tr></thead><tbody>
    @forelse($results as $row)
        <tr><td><b>{{ $row['pair'] }}</b></td><td class="{{ ($row['change_percent'] ?? 0) >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $row['change_percent'] === null ? '—' : $percent($row['change_percent']) }}</td><td><span class="pp-pill {{ strtolower($row['direction']) }}">{{ $row['direction'] }}</span></td><td><b>{{ number_format((float)$row['score'],0) }}</b></td><td>{{ $price($row['entry_price'] ?? 0) }}</td><td>{{ $price($row['stop_loss'] ?? 0) }}</td><td>{{ $price($row['take_profit'] ?? 0) }}</td><td>{{ $row['timeframe'] }}</td><td class="pp-action-cell pp-action-col"><a class="pp-signal-action pp-scanner-action opportunity" href="{{ route('pulse.signals.index',['selected'=>$row['id'],'open_trade'=>$row['id']]) }}"><strong>{{ data_get($row,'trade_action.label','Open Signal') }}</strong></a></td></tr>
    @empty
        <tr><td colspan="9" class="pp-empty">{{ $page['latest_run'] ? 'No qualifying signal was found in the latest automatic scan.' : 'No Best Signal unlocked yet. Run Find Best Signal when you are ready; there is no per-signal wallet charge.' }}</td></tr>
    @endforelse
    </tbody></table></div>
</section>
