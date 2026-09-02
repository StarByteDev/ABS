@extends('layouts.app')
@section('title','Monthly Statement — Alpha Block Solutions')
@section('content')
<section class="container statement-sheet">
    <div class="statement-top">@include('partials.logo')<div><b>PRIVATE MEMBER MONTHLY STATEMENT</b><span>{{ $statement->statement_month->format('F Y') }}</span></div></div>
    <p>This statement summarizes the account records published for {{ auth()->user()->name }}.</p>
    <div class="statement-grid"><div><small>Opening Balance</small><strong>{{ number_format($statement->opening_balance,2) }}</strong></div><div><small>Contributions</small><strong>{{ number_format($statement->contributions,2) }}</strong></div><div><small>Withdrawals</small><strong>{{ number_format($statement->withdrawals,2) }}</strong></div><div><small>Profit / Loss</small><strong>{{ number_format($statement->profit_loss,2) }}</strong></div><div><small>Closing Balance</small><strong>{{ number_format($statement->closing_balance,2) }}</strong></div></div>
    <div class="statement-notes"><h3>Statement Notes</h3><p>{{ $statement->notes ?: 'No additional note is available for this period.' }}</p></div>
    <div class="statement-actions"><button class="button button-primary" onclick="window.print()">Print or Save as PDF</button><a class="button button-ghost" href="{{ route('private.statement.export',$statement) }}">Download Statement CSV</a></div>
    <div class="disclaimer"><strong>Confidential reporting document:</strong> Verify any discrepancy with {{ config('brand.support_email') }}. This statement is not a bank, custodian or exchange statement unless explicitly identified and reconciled as such.</div>
</section>
@endsection
