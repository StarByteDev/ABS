<?php
    $number = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $price = fn ($value) => number_format((float) $value, (float) $value < 1 ? 4 : 2);
    $percent = fn ($value, $signed = false, $decimals = 2) => ($signed && (float) $value >= 0 ? '+' : '').number_format((float) $value, $decimals).'%';
    $results = collect($page['results']);
    $perPage = 6;
    $totalPages = max(1, (int) ceil($results->count() / $perPage));
    $currentPage = min($totalPages, max(1, request()->integer('page', 1)));
    $visibleResults = $results->forPage($currentPage, $perPage)->values();
    $pageQuery = request()->query();
    unset($pageQuery['page']);
    $pageUrl = fn (int $pageNumber): string => route('pulse.scanner', array_merge($pageQuery, ['page' => $pageNumber]));
    $pageNumbers = $totalPages <= 5
        ? range(1, $totalPages)
        : collect([1, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages])
            ->filter(fn (int $pageNumber): bool => $pageNumber >= 1 && $pageNumber <= $totalPages)
            ->unique()->sort()->values()->all();
    $showingFrom = $results->isEmpty() ? 0 : (($currentPage - 1) * $perPage) + 1;
    $showingTo = min($currentPage * $perPage, $results->count());
    $can = fn (string $capability): bool => (bool) data_get($page, "capabilities.{$capability}.enabled", false);
?>
<section class="pp-card pp-scanner-results" data-scan-fragment="results">
        <div class="pp-section-head"><h2>Scanner Results</h2><span>{{ $page['setups_identified'] }} qualifying setups&nbsp; · &nbsp;{{ $page['pairs_assessed'] }} markets assessed&nbsp; · &nbsp;Updated {{ $page['latest_run']?->completed_at?->diffForHumans() ?? 'after next scan' }}</span></div>
        <div class="pp-results-scroll">
            <table class="pp-table pp-scanner-signal-table"><thead><tr><th>Pair</th><th>24H</th><th>Direction</th><th>Score</th><th>Entry Price</th><th>Stop Loss</th><th>Take Profit</th><th>Timeframe</th><th>Status</th><th class="pp-action-col">Action</th></tr></thead><tbody>
            <?php foreach ($visibleResults as $row): ?>
                <tr><td>{{ $row['pair'] }}</td><td class="{{ ($row['change_percent'] ?? 0) >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $row['change_percent'] === null ? '—' : $percent($row['change_percent'], true) }}</td><td><span class="pp-pill {{ strtolower($row['direction']) === 'neutral' ? 'watch' : strtolower($row['direction']) }}">{{ $row['direction'] === 'NEUTRAL' ? 'WATCH' : $row['direction'] }}</span></td><td>{{ number_format($row['score'], 0) }}</td><td><strong class="pp-price-entry">{{ $price($row['entry_price'] ?? 0) }}</strong></td><td><span class="pp-price-stop">{{ $price($row['stop_loss'] ?? 0) }}</span></td><td><span class="pp-price-target">{{ $price($row['take_profit'] ?? 0) }}</span></td><td>{{ $row['timeframe'] }}</td><td>
                    @php $scanAction = $row['trade_action'] ?? ['phase' => 'Entry Watch', 'kind' => 'opportunity', 'label' => 'Open Trade', 'target' => 'monitor_signal', 'direct' => false]; @endphp
                    <span class="pp-signal-phase {{ $scanAction['kind'] ?? 'opportunity' }}">{{ $scanAction['phase'] ?? ($row['status'] ?? 'Monitoring') }}</span>
                </td><td class="pp-action-cell pp-action-col">
                    @if(!empty($row['id']))
                        @php
                            $scanHref = route('pulse.signals.index', [
                                'selected' => $row['id'],
                                'open_trade' => $row['id'],
                            ]);
                        @endphp
                        <a class="pp-signal-action pp-scanner-action {{ $scanAction['kind'] ?? 'opportunity' }}" href="{{ $scanHref }}" title="{{ $scanAction['detail'] ?? 'Open trade guidance for this signal.' }}"><strong>{{ $scanAction['label'] ?? 'Open Trade' }}</strong></a>
                    @elseif(!empty($row['qualified']))
                        <span class="pp-action-state" title="This qualified result is not available as a saved executable signal. Run a fresh scan to refresh it.">Refresh Signal</span>
                    @else
                        <span class="pp-action-state">Monitor</span>
                    @endif
                </td></tr>
            <?php endforeach; ?>
            <?php if ($visibleResults->isEmpty()): ?><tr><td colspan="10" class="pp-empty">{{ $page['latest_run']?->status === 'failed' ? ($page['latest_run']?->error_message ?: 'The latest scan could not evaluate market data.') : 'No evaluated markets match the current filters. Run a fresh market scan or adjust the filters.' }}</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <div class="pp-results-footer"><span>Showing {{ $showingFrom }}–{{ $showingTo }} of {{ $results->count() }} evaluated results</span><nav class="pp-pagination" aria-label="Scanner result pages"><?php if ($currentPage > 1): ?><a href="{{ $pageUrl($currentPage - 1) }}">Previous</a><?php else: ?><span class="disabled">Previous</span><?php endif; ?><?php $previousPageNumber = null; foreach ($pageNumbers as $pageNumber): ?><?php if ($previousPageNumber !== null && $pageNumber > $previousPageNumber + 1): ?><span class="disabled" aria-hidden="true">…</span><?php endif; ?><a class="{{ $pageNumber === $currentPage ? 'active' : '' }}" href="{{ $pageUrl($pageNumber) }}" aria-current="{{ $pageNumber === $currentPage ? 'page' : 'false' }}">{{ $pageNumber }}</a><?php $previousPageNumber = $pageNumber; endforeach; ?><?php if ($currentPage < $totalPages): ?><a href="{{ $pageUrl($currentPage + 1) }}">Next</a><?php else: ?><span class="disabled">Next</span><?php endif; ?></nav></div>
    </section>
