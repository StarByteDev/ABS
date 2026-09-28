<?php

namespace App\Http\Controllers;

use App\Models\MonthlyStatement;
use App\Models\PortfolioRequest;
use App\Models\PulseAlert;
use App\Models\User;
use App\Services\InvestorPerformanceService;
use App\Services\InvestorCurrencyService;
use App\Services\InvestorSettlementService;
use App\Services\PulseAuditService;
use App\Services\BrandedMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivatePortalController extends Controller
{
    public function index(Request $request, InvestorPerformanceService $performance, InvestorSettlementService $settlements)
    {
        $account = $this->account($request);
        if ($account) { $settlements->processAccount($account, now(), false); $account->refresh(); }
        $statements = $account?->statements()->reorder('statement_month', 'asc')->get() ?? collect();
        $latest = $statements->last();
        $invested = max(0.0, (float) ($account?->net_contributions ?? 0));
        $current = (float) ($account?->current_value ?? 0);
        $profit = (float) ($account?->total_profit ?? 0);
        $profitPaid = $account ? (float)$account->transactions()->where('status','posted')->where('type','profit')->sum('amount') : 0.0;
        $overallReturn = $invested > 0 ? ($profit / $invested) * 100 : 0.0;
        $monthlyReturn = $latest && (float) $latest->opening_balance > 0
            ? ((float) $latest->profit_loss / (float) $latest->opening_balance) * 100
            : 0.0;

        return view('private.index', [
            'account' => $account,
            'statements' => $statements->sortByDesc('statement_month')->values(),
            'recentTransactions' => $account?->transactions()->whereIn('status',['posted','voided'])->limit(8)->get() ?? collect(),
            'recentRequests' => $request->user()->portfolioRequests()->latest()->limit(6)->get(),
            'openRequests' => $request->user()->portfolioRequests()->whereIn('status', ['submitted','under_review'])->count(),
            'metrics' => compact('invested', 'current', 'profit', 'profitPaid', 'overallReturn', 'monthlyReturn'),
            'chart' => $this->chart($statements),
            'performance' => $account ? $performance->snapshot($account) : null,
            'agreement' => $account ? $performance->agreementSummary($account) : null,
            'performanceHistory' => $account ? $performance->history($account, 12) : collect(),
            'activityChart' => $account ? $this->activityChart($account->transactions()->where('status','posted')->whereDate('transaction_date','>=',now()->subMonths(11)->startOfMonth()->toDateString())->get()) : ['labels'=>[],'deposits'=>[],'withdrawals'=>[],'performance'=>[]],
        ]);
    }

    public function transactions(Request $request, InvestorSettlementService $settlements)
    {
        $account = $this->account($request);
        if ($account) { $settlements->processAccount($account, now(), false); $account->refresh(); }
        $posted = $account?->transactions()->where('status','posted')->get() ?? collect();
        return view('private.transactions', [
            'account' => $account,
            'transactions' => $account?->transactions()->whereIn('status',['posted','voided'])->paginate(25) ?? null,
            'activityChart' => $this->activityChart($posted->where('transaction_date','>=',now()->subMonths(11)->startOfMonth())),
            'activitySummary' => [
                'investment'=>(float)$posted->where('type','deposit')->sum(fn($r)=>(float)$r->amount),
                'capital_withdrawal'=>(float)$posted->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount),
                'withdrawal'=>(float)$posted->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount),
                'profit_paid'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),
                'profit'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),
                'cost'=>(float)$posted->filter(fn($r)=>in_array($r->type,['loss','fee'],true))->sum(fn($r)=>(float)$r->amount),
            ],
        ]);
    }

    public function statements(Request $request, InvestorSettlementService $settlements)
    {
        $account = $this->account($request);
        if ($account) { $settlements->processAccount($account, now(), false); $account->refresh(); }
        return view('private.statements', [
            'account' => $account,
            'statements' => $account?->statements()->paginate(18) ?? null,
        ]);
    }

    public function requests(Request $request, InvestorCurrencyService $currencies)
    {
        $account = $this->account($request);
        return view('private.requests', [
            'account' => $account,
            'requests' => $request->user()->portfolioRequests()->latest()->paginate(20),
            'supportedCurrencies' => $currencies->supported(),
            'currencyEditable' => $account ? $currencies->canChange($account) : false,
            'currencyLockMessage' => $account ? $currencies->lockMessage($account) : null,
        ]);
    }

    public function updateCurrency(Request $request, InvestorCurrencyService $currencies, PulseAuditService $audit)
    {
        $account = $this->account($request);
        abort_unless($account, 422, 'Your investor portfolio must be configured before choosing a currency.');
        $data = $request->validate([
            'currency' => ['required','string','max:10', Rule::in($currencies->supported())],
        ]);
        $before = strtoupper((string) $account->currency);
        $updated = $currencies->change($account, (string) $data['currency']);
        $audit->record('private_investor.currency_updated', $request->user(), 'PortfolioAccount', $updated->id, null, [
            'before' => $before, 'after' => $updated->currency,
        ], $request);

        return back()->with('success', 'Principal currency updated to '.$updated->currency.'. It will lock when financial activity begins.');
    }

    public function storeRequest(Request $request, BrandedMailService $mail, InvestorCurrencyService $currencies, PulseAuditService $audit)
    {
        $account = $this->account($request);
        abort_unless($account, 422, 'Your investor portfolio must be configured before submitting a request.');

        $data = $request->validate([
            'type' => ['required', Rule::in(['add_investment','withdrawal','portfolio_review'])],
            'amount' => ['nullable','numeric','min:0.01','max:999999999999.99'],
            'currency' => ['nullable','string','max:10', Rule::in($currencies->supported())],
            'message' => ['nullable','string','max:3000'],
        ]);

        if (in_array($data['type'], ['add_investment','withdrawal'], true) && empty($data['amount'])) {
            return back()->withErrors(['amount' => 'Enter the amount for this request.'])->withInput();
        }

        $requestedCurrency = strtoupper((string) ($data['currency'] ?? $account->currency));
        if ($data['type'] === 'add_investment' && $requestedCurrency !== strtoupper((string) $account->currency)) {
            $beforeCurrency = strtoupper((string) $account->currency);
            $account = $currencies->change($account, $requestedCurrency);
            $audit->record('private_investor.currency_updated_with_investment_request', $request->user(), 'PortfolioAccount', $account->id, null, [
                'before' => $beforeCurrency, 'after' => $account->currency,
            ], $request);
        }

        if ($data['type'] === 'withdrawal') {
            $requestedCurrency = strtoupper((string) $account->currency);
        }
        if ($data['type'] === 'portfolio_review') {
            $data['amount'] = null;
            $requestedCurrency = strtoupper((string) $account->currency);
        }
        if ($data['type'] === 'withdrawal' && (float) $data['amount'] > (float) $account->net_contributions) {
            return back()->withErrors(['amount' => 'A capital withdrawal cannot exceed your remaining principal of '.$account->currency.' '.number_format((float)$account->net_contributions, 2).'.'])->withInput();
        }
        if ($request->user()->portfolioRequests()->whereIn('status',['submitted','under_review'])->count() >= 5) {
            return back()->withErrors(['type' => 'You already have several open requests. Please wait for the current requests to be reviewed.']);
        }

        $portfolioRequest = PortfolioRequest::create([
            'user_id' => $request->user()->id,
            'portfolio_account_id' => $account->id,
            'type' => $data['type'],
            'amount' => $data['amount'] ?? null,
            'currency' => $requestedCurrency,
            'status' => 'submitted',
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
        ]);

        $this->alertAdmins($request->user(), $portfolioRequest);
        $mail->investorRequestReceived($request->user(), $portfolioRequest);
        $mail->adminInvestorRequest($request->user(), $portfolioRequest);

        return back()->with('success', match ($portfolioRequest->type) {
            'add_investment' => 'Additional investment request submitted for review.',
            'withdrawal' => 'Withdrawal request submitted for review.',
            default => 'Portfolio review request submitted. The ABS team can continue with you from here.',
        });
    }

    public function cancelRequest(Request $request, PortfolioRequest $portfolioRequest, BrandedMailService $mail)
    {
        abort_unless($portfolioRequest->user_id === $request->user()->id, 404);
        abort_unless($portfolioRequest->status === 'submitted', 422, 'Only newly submitted requests can be cancelled.');
        $portfolioRequest->update(['status' => 'cancelled']);
        $mail->investorRequestCancelled($request->user(), $portfolioRequest->fresh());
        return back()->with('success', 'Request cancelled.');
    }

    public function statement(Request $request, MonthlyStatement $statement, InvestorSettlementService $settlements)
    {
        abort_unless($statement->account?->user_id === $request->user()->id, 403);
        if ($statement->account) { $settlements->processAccount($statement->account, now(), false); $statement->refresh(); }
        return view('private.statement', compact('statement'));
    }

    public function exportStatement(Request $request, MonthlyStatement $statement, InvestorSettlementService $settlements): StreamedResponse
    {
        abort_unless($statement->account?->user_id === $request->user()->id, 403);
        if ($statement->account) { $settlements->processAccount($statement->account, now(), false); $statement->refresh(); }
        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Alpha Block Solutions - Private Investor Monthly Statement']);
            fputcsv($out, ['Statement month', $statement->statement_month->format('F Y')]);
            fputcsv($out, ['Currency', $statement->account?->currency ?? $statement->currency ?? 'USD']);
            fputcsv($out, ['Opening balance', $statement->opening_balance]);
            fputcsv($out, ['Contributions', $statement->contributions]);
            fputcsv($out, ['Capital withdrawn', $statement->withdrawals]);
            fputcsv($out, ['Profit / Loss', $statement->profit_loss]);
            fputcsv($out, ['Profit paid', $statement->profit_paid]);
            fputcsv($out, ['Payment date', $statement->payment_date?->toDateString() ?: '']);
            fputcsv($out, ['Closing capital balance', $statement->closing_balance]);
            fputcsv($out, ['Monthly return %', $statement->opening_balance > 0 ? round(((float)$statement->profit_loss / (float)$statement->opening_balance) * 100, 2) : 0]);
            fputcsv($out, ['Notes', $statement->notes]);
            fclose($out);
        }, 'ABS-Investor-Statement-'.$statement->statement_month->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function account(Request $request)
    {
        return $request->user()->portfolioAccount()->with(['transactions', 'statements'])->first();
    }

    private function chart($statements): array
    {
        $rows = $statements->sortBy('statement_month')->values()->take(-12);
        if ($rows->isEmpty()) return ['labels'=>[], 'values'=>[], 'profit'=>[], 'points'=>''];
        $values = $rows->map(fn ($row) => (float) $row->closing_balance)->values();
        $min = (float) $values->min();
        $max = (float) $values->max();
        $range = max(1.0, $max - $min);
        $count = max(1, $values->count() - 1);
        $points = $values->map(function ($value, $index) use ($min, $range, $count) {
            $x = 4 + ($index / $count) * 92;
            $y = 84 - (((float)$value - $min) / $range) * 68;
            return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        })->implode(' ');
        return [
            'labels' => $rows->map(fn ($row) => $row->statement_month->format('M y'))->all(),
            'values' => $values->all(),
            'profit' => $rows->map(fn ($row) => (float) $row->profit_loss)->all(),
            'points' => $points,
        ];
    }

    private function activityChart($rows): array
    {
        $months=collect(range(0,11))->map(fn($i)=>now()->subMonths(11-$i)->startOfMonth());
        $labels=[];$deposits=[];$withdrawals=[];$performance=[];
        foreach($months as $month){
            $key=$month->format('Y-m');
            $slice=$rows->filter(fn($r)=>$r->transaction_date?->format('Y-m')===$key);
            $labels[]=$month->format('M y');
            $deposits[]=(float)$slice->where('type','deposit')->sum(fn($r)=>(float)$r->amount);
            $withdrawals[]=(float)$slice->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount);
            $performance[]=(float)$slice->filter(fn($r)=>in_array($r->type,['profit','loss','fee'],true))->sum(fn($r)=>$r->type==='profit'?(float)$r->amount:-1*(float)$r->amount);
        }
        return compact('labels','deposits','withdrawals','performance');
    }

    private function alertAdmins(User $user, PortfolioRequest $portfolioRequest): void
    {
        $type = match ($portfolioRequest->type) {
            'add_investment' => 'Additional investment request',
            'withdrawal' => 'Withdrawal request',
            default => 'Portfolio review request',
        };
        foreach (User::query()->where('role','admin')->where('status','active')->get(['id']) as $admin) {
            PulseAlert::create([
                'user_id' => $admin->id,
                'type' => 'private_investor',
                'title' => $type,
                'message' => $user->name.($portfolioRequest->amount ? ' · '.$portfolioRequest->currency.' '.number_format((float)$portfolioRequest->amount, 2) : ''),
                'severity' => $portfolioRequest->type === 'withdrawal' ? 'warning' : 'info',
                'is_read' => false,
                'action_url' => route('admin.private-investors.requests'),
                'data' => ['portfolio_request_id' => $portfolioRequest->id, 'user_id' => $user->id],
            ]);
        }
    }
}
