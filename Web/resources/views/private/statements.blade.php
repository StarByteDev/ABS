@extends('pulse.layout')
@section('title','Investor Statements · ABS Pulse')
@section('heading','Investor Statements')
@section('content')
<section class="pi-shell"><header class="pi-head"><div><span class="pi-kicker">Private Investor</span><h1>Monthly Statements</h1><p>Automatically reconciled monthly capital, paid profit and performance records.</p></div></header>@include('private._tabs')
@if($account && $statements)
<article class="pi-card"><div class="pi-table-wrap"><table class="pi-table"><thead><tr><th>Month</th><th>Opening</th><th>Added</th><th>Capital Withdrawn</th><th>Profit / Loss</th><th>Profit Paid</th><th>Closing Capital</th><th>Return</th><th></th></tr></thead><tbody>
@forelse($statements as $s)@php($r=(float)$s->opening_balance>0?((float)$s->profit_loss/(float)$s->opening_balance)*100:0)<tr><td>{{ $s->statement_month->format('F Y') }}</td><td>{{ $account->currency }} {{ number_format($s->opening_balance,2) }}</td><td>{{ $account->currency }} {{ number_format($s->contributions,2) }}</td><td>{{ $account->currency }} {{ number_format($s->withdrawals,2) }}</td><td class="{{ $s->profit_loss>=0?'pi-positive':'pi-negative' }}">{{ $account->currency }} {{ number_format($s->profit_loss,2) }}</td><td class="pi-positive">{{ $account->currency }} {{ number_format($s->profit_paid,2) }}</td><td>{{ $account->currency }} {{ number_format($s->closing_balance,2) }}</td><td class="{{ $r>=0?'pi-positive':'pi-negative' }}">{{ number_format($r,2) }}%</td><td><a href="{{ route('private.statement',$s) }}">View</a></td></tr>@empty<tr><td colspan="9">No completed statement period is available yet.</td></tr>@endforelse
</tbody></table></div></article>{{ $statements->links() }}
@else<div class="pi-empty">Portfolio reporting has not been configured yet.</div>@endif</section>
@endsection
