@extends('pulse.layout')
@section('title','Investor Portfolio · ABS Pulse')
@section('heading','Investor Portfolio')
@section('content')
<section class="pi-shell pi-portfolio-v1569 pi-v1570">
    <header class="pi-head pi-portfolio-head">
        <div>
            <span class="pi-kicker">Private Investor</span>
            <h1>Portfolio Command Center</h1>
            <p>Your reported portfolio position, current-month progress, capital activity and statements.</p>
        </div>
        <div class="pi-actions">
            <a class="pi-btn" href="{{ route('private.statements') }}">Statements</a>
            <a class="pi-btn" href="{{ route('private.transactions') }}">Transactions</a>
            <a class="pi-btn primary" href="{{ route('private.requests') }}">New Request</a>
        </div>
    </header>

    @include('private._tabs')

    @if(! $account)
        <article class="pi-card">
            <div class="pi-empty">
                <strong>Your Private Investor access is active.</strong><br>
                Your portfolio record has not yet been published. Please contact ABS Support for account setup.
            </div>
        </article>
    @else
        @php
            $perf = $performance ?? [];
            $plan = $perf['plan'] ?? null;
            $mtd = (float) ($perf['mtd'] ?? 0);
            $target = (float) ($perf['target'] ?? 0);
            $agreementData = $agreement ?? [];
            $agreementTerm = $agreementData['term'] ?? null;
            $performanceToDate = (float)($agreementData['posted_total'] ?? 0);
            $indicative = (float) ($agreementData['indicative_value'] ?? $perf['indicative_value'] ?? $account->current_value);
            $progress = (float) ($perf['progress'] ?? 0);
            $chartDaily = $perf['chart']['daily'] ?? [];
            $chartCum = $perf['chart']['cumulative'] ?? [];
            $dailyAbs = count($chartDaily) ? array_map('abs', $chartDaily) : [1];
            $maxDaily = max(1, max($dailyAbs));
            $activityValues = array_merge(
                $activityChart['deposits'] ?? [0],
                $activityChart['withdrawals'] ?? [0],
                array_map('abs', $activityChart['performance'] ?? [0])
            );
            $activityMax = max(1, max($activityValues ?: [1]));
            $base = max(0, (float) $account->net_contributions);
            $publishedPl = (float) $account->total_profit;
            $driverMax = max(1, abs($base) + abs($publishedPl));
        @endphp

        <div class="pi-account-meta pi-account-strip">
            <span>Account <b>{{ $account->account_name }}</b></span>
            <span>Valuation <b>{{ optional($account->valuation_date)->format('d M Y') ?: 'Pending' }}</b></span>
            <span>Currency <b>{{ $account->currency }}</b></span>
            <span>Status <b class="{{ $account->is_active ? 'pi-positive' : 'pi-negative' }}">{{ $account->is_active ? 'Active' : 'Paused' }}</b></span>
        </div>

        <div class="pi-hero-grid">
            <article class="pi-hero-value">
                <small>REPORTED CAPITAL VALUE</small>
                <strong>{{ $account->currency }} {{ number_format((float) $account->current_value, 2) }}</strong>
                <p>Confirmed invested capital after capital movements. Paid profit is shown separately and is not added back to principal.</p>
                <div class="pi-hero-value-foot">
                    <span>Investor principal <b>{{ $account->currency }} {{ number_format((float) $metrics['invested'], 2) }}</b></span>
                    <span>Profit paid <b class="pi-positive">{{ $account->currency }} {{ number_format((float) $metrics['profitPaid'], 2) }}</b></span>
                    <span>Performance to date <b class="pi-positive">+{{ $account->currency }} {{ number_format($performanceToDate, 2) }}</b></span>
                </div>
            </article>

            <article class="pi-hero-month {{ $plan ? '' : 'is-empty' }}">
                <div class="pi-card-head">
                    <div>
                        <small>CURRENT MONTH</small>
                        <h2>{{ $plan ? $plan->plan_month->format('F Y') : now()->format('F Y') }}</h2>
                    </div>
                    @if($plan)
                        <span>{{ number_format((float) $plan->target_rate, 2) }}% target</span>
                    @endif
                </div>
                @if($plan)
                    <div class="pi-month-number">
                        <strong class="{{ $mtd >= 0 ? 'pi-positive' : 'pi-negative' }}">{{ $mtd >= 0 ? '+' : '' }}{{ $account->currency }} {{ number_format($mtd, 2) }}</strong>
                        <span>provisional month-to-date</span>
                    </div>
                    <div class="pi-progress"><i style="width:{{ min(100, max(0, $progress)) }}%"></i></div>
                    <div class="pi-progress-meta"><span>{{ number_format($progress, 1) }}% of target</span><b>{{ $account->currency }} {{ number_format($target, 2) }}</b></div>
                @else
                    <div class="pi-empty compact">No current-month performance plan is active.</div>
                @endif
            </article>
        </div>

        <div class="pi-grid pi-grid-5">
            <article class="pi-stat"><small>Indicative Capital + Accrual</small><strong>{{ $account->currency }} {{ number_format($indicative, 2) }}</strong><em>Reported value + provisional performance</em></article>
            <article class="pi-stat"><small>Agreed Monthly Rate</small><strong>{{ $agreementTerm ? number_format((float)$agreementTerm->monthly_target_rate,2).'%' : '—' }}</strong><em>{{ $agreementTerm?->effective_from?->format('d M Y') ?: 'Not configured' }}</em></article>
            <article class="pi-stat"><small>Profit Paid To Date</small><strong class="pi-positive">{{ $account->currency }} {{ number_format((float)$metrics['profitPaid'],2) }}</strong><em>Completed distributions</em></article>
            <article class="pi-stat"><small>Open Requests</small><strong>{{ number_format($openRequests) }}</strong><em>Investment, withdrawal or review</em></article>
            <article class="pi-stat"><small>Statements</small><strong>{{ number_format($statements->count()) }}</strong><em>Published reports</em></article>
        </div>

        <div class="pi-layout-main">
            <article class="pi-card pi-card-large">
                <div class="pi-card-head">
                    <div><h2>Current Month Progress</h2><span>Provisional daily progress</span></div>
                    @if($plan)<b class="pi-chip">Target {{ $account->currency }} {{ number_format($target, 2) }}</b>@endif
                </div>
                @if($plan && count($chartCum))
                    <div class="pi-daily-chart">
                        <div class="pi-daily-bars">
                            @foreach($chartDaily as $i => $value)
                                @php
                                    $dayRow = isset($perf['daily']) ? $perf['daily']->get($i) : null;
                                    $posted = $dayRow && $dayRow->posted_at;
                                @endphp
                                <i class="{{ $value < 0 ? 'negative' : '' }} {{ $posted ? 'posted' : 'future' }}" style="height:{{ max(3, min(100, (abs($value) / $maxDaily) * 100)) }}%" title="Day {{ $i + 1 }}: {{ number_format($value, 2) }}"></i>
                            @endforeach
                        </div>
                        <svg class="pi-daily-line" viewBox="0 0 100 90" preserveAspectRatio="none" role="img" aria-label="Current month provisional progress">
                            <line class="axis" x1="3" y1="20" x2="97" y2="20"/>
                            <line class="axis" x1="3" y1="50" x2="97" y2="50"/>
                            <line class="axis" x1="3" y1="82" x2="97" y2="82"/>
                            <polyline class="line" points="{{ $perf['chart']['points'] ?? '' }}"/>
                        </svg>
                    </div>
                @else
                    <div class="pi-empty">Current-month progress will appear after a performance plan is activated.</div>
                @endif
            </article>

            <article class="pi-card pi-month-brief">
                <div class="pi-card-head"><h2>Month Snapshot</h2><span>{{ now()->format('M Y') }}</span></div>
                @if($plan)
                    <div class="pi-brief-row"><span>Base amount</span><b>{{ $account->currency }} {{ number_format((float) $plan->base_amount, 2) }}</b></div>
                    <div class="pi-brief-row"><span>Monthly target</span><b>{{ number_format((float) $plan->target_rate, 2) }}%</b></div>
                    <div class="pi-brief-row"><span>Target amount</span><b>{{ $account->currency }} {{ number_format($target, 2) }}</b></div>
                    <div class="pi-brief-row"><span>Accrued to date</span><b class="{{ $mtd >= 0 ? 'pi-positive' : 'pi-negative' }}">{{ $account->currency }} {{ number_format($mtd, 2) }}</b></div>
                    <div class="pi-brief-row"><span>Days posted</span><b>{{ $perf['days_posted'] ?? 0 }} / {{ $perf['days_total'] ?? 0 }}</b></div>
                @else
                    <div class="pi-empty compact">No monthly plan is active.</div>
                @endif
            </article>
        </div>

        @if(($performanceHistory ?? collect())->isNotEmpty())
        @php
            $historyRows = collect($performanceHistory)->sortBy(fn($r)=>$r['plan']->plan_month)->values();
            $historyMax = max(1,(float)$historyRows->max(fn($r)=>abs((float)$r['target'])));
        @endphp
        <article class="pi-card pi-v1570-history-card">
            <div class="pi-card-head"><div><h2>Monthly Performance History</h2><span>Automatic schedule from your investment start date</span></div><b class="pi-chip">{{ number_format((float)($agreementTerm?->monthly_target_rate ?? 0),2) }}% agreement</b></div>
            <div class="pi-v1570-history-bars">
                @foreach($historyRows as $row)
                    @php $pct = max(3,min(100,(abs((float)$row['target'])/$historyMax)*100)); @endphp
                    <div title="{{ $row['plan']->plan_month->format('F Y') }} · Target {{ number_format((float)$row['target'],2) }} · Posted {{ number_format((float)$row['posted'],2) }}"><div class="bar"><i style="height:{{ $pct }}%"></i></div><b>{{ $row['plan']->plan_month->format('M') }}</b><small>{{ $account->currency }} {{ number_format((float)$row['posted'],0) }}</small></div>
                @endforeach
            </div>
        </article>
        @endif

        <div class="pi-layout-2 pi-investor-insight-grid">
            <article class="pi-card">
                <div class="pi-card-head"><div><h2>Capital Movement</h2><span>Last 12 months</span></div></div>
                <div class="pi-flow-chart pi-flow-chart-member">
                    @foreach(($activityChart['labels'] ?? []) as $i => $label)
                        <div class="pi-flow-column">
                            <div class="pi-flow-bars">
                                <i class="deposit" style="height:{{ max(2, (($activityChart['deposits'][$i] ?? 0) / $activityMax) * 100) }}%"></i>
                                <i class="withdrawal" style="height:{{ max(2, (($activityChart['withdrawals'][$i] ?? 0) / $activityMax) * 100) }}%"></i>
                                <i class="performance {{ ($activityChart['performance'][$i] ?? 0) < 0 ? 'negative' : '' }}" style="height:{{ max(2, (abs($activityChart['performance'][$i] ?? 0) / $activityMax) * 100) }}%"></i>
                            </div>
                            <small>{{ explode(' ', $label)[0] }}</small>
                        </div>
                    @endforeach
                </div>
                <div class="pi-legend"><span><i class="deposit"></i>Investment</span><span><i class="withdrawal"></i>Capital withdrawn</span><span><i class="performance"></i>Paid / net performance</span></div>
            </article>

            <article class="pi-card">
                <div class="pi-card-head"><div><h2>Portfolio Drivers</h2><span>Current reported position</span></div></div>
                <div class="pi-driver-card">
                    <div><span>Investor principal</span><b>{{ $account->currency }} {{ number_format($base, 2) }}</b><div class="pi-driver-bar"><i class="capital" style="width:{{ min(100, (abs($base) / $driverMax) * 100) }}%"></i></div></div>
                    <div><span>Paid / net P&amp;L</span><b class="{{ $publishedPl >= 0 ? 'pi-positive' : 'pi-negative' }}">{{ $publishedPl >= 0 ? '+' : '' }}{{ $account->currency }} {{ number_format($publishedPl, 2) }}</b><div class="pi-driver-bar"><i class="{{ $publishedPl >= 0 ? 'profit' : 'loss' }}" style="width:{{ min(100, (abs($publishedPl) / $driverMax) * 100) }}%"></i></div></div>
                    <div class="pi-driver-total"><span>Reported value</span><strong>{{ $account->currency }} {{ number_format((float) $account->current_value, 2) }}</strong></div>
                </div>
            </article>
        </div>

        <div class="pi-layout-2">
            <article class="pi-card">
                <div class="pi-card-head"><h2>Recent Statements</h2><a class="pi-btn" href="{{ route('private.statements') }}">View All</a></div>
                <div class="pi-table-wrap">
                    <table class="pi-table">
                        <thead><tr><th>Month</th><th>Opening</th><th>Profit / Loss</th><th>Profit Paid</th><th>Closing Capital</th><th></th></tr></thead>
                        <tbody>
                        @if($statements->take(5)->isEmpty())
                            <tr><td colspan="6">No completed statement period is available yet.</td></tr>
                        @else
                            @foreach($statements->take(5) as $statement)
                                <tr>
                                    <td>{{ $statement->statement_month->format('F Y') }}</td>
                                    <td>{{ number_format((float) $statement->opening_balance, 2) }}</td>
                                    <td class="{{ $statement->profit_loss >= 0 ? 'pi-positive' : 'pi-negative' }}">{{ $account->currency }} {{ number_format((float) $statement->profit_loss, 2) }}</td>
                                    <td class="pi-positive">{{ $account->currency }} {{ number_format((float) $statement->profit_paid, 2) }}</td>
                                    <td>{{ $account->currency }} {{ number_format((float) $statement->closing_balance, 2) }}</td>
                                    <td><a href="{{ route('private.statement', $statement) }}">Open</a></td>
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="pi-card">
                <div class="pi-card-head"><h2>Recent Activity</h2><a class="pi-btn" href="{{ route('private.transactions') }}">Transactions</a></div>
                @if($recentTransactions->take(6)->isEmpty())
                    <div class="pi-empty compact">No transactions recorded yet.</div>
                @else
                    @foreach($recentTransactions->take(6) as $tx)
                        <div class="pi-admin-investor {{ $tx->status === 'voided' ? 'is-voided' : '' }}">
                            <div>
                                <b>{{ match($tx->type){'deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit Paid','loss'=>'Loss','fee'=>'Fee','adjustment'=>'Adjustment',default=>ucfirst($tx->type)} }} @if($tx->status === 'voided')<span class="pi-status voided">Corrected</span>@endif</b>
                                <small>{{ optional($tx->transaction_date)->format('d M Y') }} · {{ $tx->reference ?: 'Portfolio activity' }}</small>
                            </div>
                            <strong class="{{ $tx->status === 'voided' ? 'pi-muted' : (in_array($tx->type, ['profit','deposit']) ? 'pi-positive' : (in_array($tx->type, ['loss','fee','withdrawal']) ? 'pi-negative' : '')) }}">{{ $account->currency }} {{ number_format((float) $tx->amount, 2) }}</strong>
                        </div>
                    @endforeach
                @endif
            </article>
        </div>

        <div class="pi-note"><strong>Portfolio reporting:</strong> posted transactions and automatically reconciled monthly statements form the official account record. Current-month accrual remains provisional until the agreed payout date, when profit is recorded as paid without increasing investor principal.</div>
    @endif
</section>
@endsection
