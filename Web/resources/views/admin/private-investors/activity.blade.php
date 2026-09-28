@extends('admin.layout')
@section('title','Investor Transactions — ABS Admin')
@section('heading','Investor Transactions')
@section('description','Review, edit or delete portfolio transactions across all Private Investor accounts.')
@section('page-actions')
    <a class="pi-btn" href="{{ route('admin.private-investors.overview') }}">Portfolio Overview</a>
@endsection
@section('content')
<section class="pi-shell pi-ledger-v1568 pi-v1571">
    <div class="pi-grid pi-grid-3">
        <article class="pi-stat"><small>Posted Entries</small><strong>{{ number_format($counts['posted']) }}</strong><em>Included in portfolio balances</em></article>
        <article class="pi-stat"><small>Draft Entries</small><strong>{{ number_format($counts['draft']) }}</strong><em>Not included in balances</em></article>
        <article class="pi-stat"><small>Corrected Entries</small><strong>{{ number_format($counts['voided']) }}</strong><em>Previously reversed entries</em></article>
    </div>

    <article class="pi-card">
        <form class="pi-filter-row" method="GET">
            <label>Investor
                <select name="user">
                    <option value="">All investors</option>
                    @foreach($members as $m)
                        <option value="{{ $m->id }}" @selected((string) request('user') === (string) $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    @foreach(['posted','draft','voided'] as $v)
                        <option value="{{ $v }}" @selected(request('status') === $v)>{{ ucfirst($v) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Type
                <select name="type">
                    <option value="">All</option>
                    @foreach(['deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit','loss'=>'Loss','fee'=>'Fee','adjustment'=>'Adjustment'] as $value=>$label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>From<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>To<input type="date" name="to" value="{{ request('to') }}"></label>
            <button class="pi-btn primary">Apply Filters</button>
        </form>
    </article>

    <article class="pi-card">
        <div class="pi-card-head">
            <div>
                <h2>Transactions</h2>
                <span>Edit or delete incorrect entries. Posted deletions reverse their stored accounting effect first.</span>
            </div>
        </div>
        <div class="pi-table-wrap">
            <table class="pi-table pi-ledger-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Investor</th><th>Activity</th><th>Investor Amount</th><th>Admin USD / FX</th><th>Status</th><th>Reference</th><th>Recorded by</th><th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($transactions as $tx)
                    <tr class="{{ $tx->status === 'voided' ? 'is-voided' : '' }}">
                        <td>{{ $tx->transaction_date?->format('d M Y') }}</td>
                        <td>
                            @if($tx->account)
                                <a href="{{ route('admin.private-investors.show',$tx->account) }}"><b>{{ $tx->account?->user?->name }}</b></a>
                            @else
                                <b>Account unavailable</b>
                            @endif
                            <small>{{ $tx->account?->user?->email }}</small>
                        </td>
                        <td>
                            <b>{{ match($tx->type) {'deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit','loss'=>'Loss','fee'=>'Fee',default=>'Adjustment'} }}</b>
                            @if($tx->description)<small>{{ $tx->description }}</small>@endif
                            @if($tx->status === 'voided' && $tx->void_reason)<small>Correction: {{ $tx->void_reason }}</small>@endif
                        </td>
                        <td class="{{ in_array($tx->type,['deposit','profit']) ? 'pi-positive' : (in_array($tx->type,['withdrawal','loss','fee']) ? 'pi-negative' : '') }}">
                            {{ $tx->account?->currency }} {{ number_format((float)$tx->amount,2) }}
                        </td>
                        <td>@if($tx->usd_amount !== null)
                            @if($tx->type==='withdrawal' && strtoupper((string)$tx->account?->currency)!=='USD')
                                <b>Settlement USD {{ number_format((float)($tx->settlement_usd_amount ?? $tx->usd_amount),2) }}</b>
                                <small>Basis USD {{ number_format((float)($tx->principal_usd_basis ?? abs((float)$tx->usd_net_contributions_effect)),2) }}</small>
                                <small class="{{ (float)($tx->fx_gain_loss_usd ?? 0)>=0?'pi-positive':'pi-negative' }}">FX {{ (float)($tx->fx_gain_loss_usd ?? 0)>=0?'gain +':'loss ' }}USD {{ number_format(abs((float)($tx->fx_gain_loss_usd ?? 0)),2) }}</small>
                            @else
                                <b>USD {{ number_format((float)$tx->usd_amount,2) }}</b>@if(strtoupper((string)$tx->account?->currency)!=='USD')<small>Locked @ {{ number_format((float)$tx->fx_rate_to_usd,6) }}</small>@endif
                            @endif
                        @else<span class="pi-status draft">FX required</span>@endif</td>
                        <td>
                            <span class="pi-status {{ $tx->status }}">{{ $tx->status === 'voided' ? 'corrected' : $tx->status }}</span>
                            @if($tx->status === 'voided')<small>{{ $tx->voided_at?->format('d M H:i') }}</small>@endif
                        </td>
                        <td>{{ $tx->reference ?: '—' }}</td>
                        <td>{{ $tx->creator?->name ?: 'Legacy / System' }}</td>
                        <td class="pi-ledger-actions pi-entry-manage">
                            @if($tx->status !== 'voided')
                                <details class="pi-entry-editor">
                                    <summary class="pi-mini-btn">Edit</summary>
                                    <form method="POST" action="{{ route('admin.private-investors.transactions.update',$tx) }}" class="pi-entry-edit-form">
                                        @csrf
                                        @method('PATCH')
                                        <div class="pi-entry-edit-grid">
                                            <label>Type
                                                <select name="type" required>
                                                    @foreach(['deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit','loss'=>'Loss','fee'=>'Fee','adjustment'=>'Adjustment'] as $value=>$label)
                                                        <option value="{{ $value }}" @selected($tx->type === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Amount<input type="number" step="0.01" name="amount" value="{{ $tx->amount }}" required></label>
                                            <label>Date<input type="date" name="transaction_date" value="{{ $tx->transaction_date?->toDateString() }}" required></label>
                                            @if(strtoupper((string)$tx->account?->currency)!=='USD')<label>USD conversion / settlement rate<input type="number" step="0.00000001" min="0.00000001" name="fx_rate_to_usd" value="{{ $tx->fx_rate_to_usd }}" required></label>@else<input type="hidden" name="fx_rate_to_usd" value="1">@endif
                                            <label>Reference<input name="reference" value="{{ $tx->reference }}"></label>
                                        </div>
                                        <label>Description<textarea name="description">{{ $tx->description }}</textarea></label>
                                        <button class="pi-mini-btn good" type="submit">Save Changes</button>
                                    </form>
                                </details>
                            @endif

                            @if($tx->status === 'draft')
                                <form method="POST" action="{{ route('admin.private-investors.transactions.post',$tx) }}">
                                    @csrf
                                    <button class="pi-mini-btn good" type="submit">Post</button>
                                </form>
                            @elseif($tx->status === 'posted')
                                <details class="pi-correction">
                                    <summary class="pi-mini-btn">Reverse</summary>
                                    <form method="POST" action="{{ route('admin.private-investors.transactions.void',$tx) }}">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="void_reason" required minlength="5" placeholder="Reason for correction"></textarea>
                                        <button class="pi-mini-btn danger" type="submit">Confirm Reverse</button>
                                    </form>
                                </details>
                            @endif

                            <form method="POST" action="{{ route('admin.private-investors.transactions.delete',$tx) }}" onsubmit="return confirm('Delete this portfolio entry? If it is posted, ABS will automatically reverse its balance effect before deletion. This cannot be undone from this screen.')">
                                @csrf
                                @method('DELETE')
                                <button class="pi-mini-btn danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">No activity matches the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </article>
</section>
@endsection
