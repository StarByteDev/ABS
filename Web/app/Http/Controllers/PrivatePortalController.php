<?php

namespace App\Http\Controllers;

use App\Models\MonthlyStatement;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivatePortalController extends Controller
{
    public function index(Request $request)
    {
        $account = $request->user()->portfolioAccount()->with(['transactions', 'statements'])->first();
        return view('private.index', compact('account'));
    }

    public function statement(Request $request, MonthlyStatement $statement)
    {
        abort_unless($statement->account?->user_id === $request->user()->id, 403);
        return view('private.statement', compact('statement'));
    }

    public function exportStatement(Request $request, MonthlyStatement $statement): StreamedResponse
    {
        abort_unless($statement->account?->user_id === $request->user()->id, 403);
        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Alpha Block Solutions - Private Member Monthly Statement']);
            fputcsv($out, ['Statement month', $statement->statement_month->format('F Y')]);
            fputcsv($out, ['Opening balance', $statement->opening_balance]);
            fputcsv($out, ['Contributions', $statement->contributions]);
            fputcsv($out, ['Withdrawals', $statement->withdrawals]);
            fputcsv($out, ['Profit / Loss', $statement->profit_loss]);
            fputcsv($out, ['Closing balance', $statement->closing_balance]);
            fputcsv($out, ['Notes', $statement->notes]);
            fclose($out);
        }, 'ABS-Statement-'.$statement->statement_month->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }
}
