<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyStatement;
use App\Models\PortfolioAccount;
use App\Models\PortfolioRequest;
use App\Models\PortfolioPerformancePlan;
use App\Models\PortfolioInvestmentTerm;
use App\Models\PortfolioTransaction;
use App\Models\PortfolioDailyAccrual;
use App\Models\PulseAlert;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\PulseAuditService;
use App\Services\InvestorPerformanceService;
use App\Services\InvestorPortfolioAccountingService;
use App\Services\InvestorCurrencyService;
use App\Services\InvestorSettlementService;
use App\Services\BrandedMailService;
use App\Services\InvestorFxService;
use App\Models\EmailDeliveryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;

class AdminPortfolioController extends Controller
{
    public function index(InvestorSettlementService $settlements)
    {
        // Keep due monthly payouts/statements synchronized before consolidated reporting.
        $settlements->processAll(now(), false);
        $accounts = PortfolioAccount::query()->with(['user','statements','requests','transactions'])->where('is_active', true)->get();
        $usdValue = fn (PortfolioAccount $a, string $field, string $local) => (float) ($a->{$field} ?? (strtoupper((string)$a->currency) === 'USD' ? $a->{$local} : 0));
        $currentValue = (float) $accounts->sum(fn ($a) => $usdValue($a, 'current_value_usd', 'current_value'));
        $contributions = (float) $accounts->sum(fn ($a) => $usdValue($a, 'net_contributions_usd', 'net_contributions'));
        $profit = (float) $accounts->sum(fn ($a) => $usdValue($a, 'total_profit_usd', 'total_profit'));
        $monthly = (float) $accounts->sum(fn ($a) => $usdValue($a, 'monthly_profit_usd', 'monthly_profit'));
        $realizedFx = (float) $accounts->sum(fn ($a) => (float)($a->realized_fx_gain_loss_usd ?? 0));
        $profitPaid = (float) PortfolioTransaction::query()->where('status','posted')->where('type','profit')->sum('usd_profit_effect');
        $return = $contributions > 0 ? ($profit / $contributions) * 100 : 0.0;

        $currentPlans = PortfolioPerformancePlan::query()->with(['accruals','account.transactions'])
            ->whereDate('plan_month', now()->startOfMonth()->toDateString())->get();
        $monthlyTarget = (float) $currentPlans->sum(function ($plan) {
            $rate = $this->latestFxRateToUsd($plan->account);
            return $rate ? (float)$plan->target_amount * $rate : 0;
        });
        $mtdProvisional = (float) $currentPlans->sum(function ($plan) {
            $rate = $this->latestFxRateToUsd($plan->account);
            $local = (float)$plan->accruals->whereNotNull('posted_at')->sum(fn ($row) => (float)$row->posted_amount);
            return $rate ? $local * $rate : 0;
        });

        $statementRows = MonthlyStatement::query()->with('account')->whereNotNull('published_at')
            ->whereDate('statement_month','>=',now()->subMonths(11)->startOfMonth()->toDateString())
            ->orderBy('statement_month')->get();
        $performance = $statementRows->groupBy(fn($row)=>$row->statement_month?->format('Y-m'))->map(function ($rows) {
            $first = $rows->first();
            return (object)[
                'statement_month'=>$first->statement_month,
                'closing_balance'=>(float)$rows->sum(fn($r)=>(float)($r->closing_balance_usd ?? (strtoupper((string)$r->currency)==='USD'?$r->closing_balance:0))),
                'profit_loss'=>(float)$rows->sum(fn($r)=>(float)($r->profit_loss_usd ?? (strtoupper((string)$r->currency)==='USD'?$r->profit_loss:0))),
                'contributions'=>(float)$rows->sum(fn($r)=>(float)($r->contributions_usd ?? (strtoupper((string)$r->currency)==='USD'?$r->contributions:0))),
                'withdrawals'=>(float)$rows->sum(fn($r)=>(float)($r->withdrawals_usd ?? (strtoupper((string)$r->currency)==='USD'?$r->withdrawals:0))),
            ];
        })->values();
        $performanceChart = $this->performanceChart($performance);

        $since = now()->subMonths(11)->startOfMonth();
        $activityRows = PortfolioTransaction::query()->with('account.user')->where('status','posted')
            ->whereDate('transaction_date','>=',$since->toDateString())->orderBy('transaction_date')->get();
        $capitalFlow = $this->capitalFlowChart($activityRows, true);
        $allocation = $accounts->sortByDesc(fn($a)=>$usdValue($a,'current_value_usd','current_value'))->take(8)->values()->map(function($a) use($currentValue,$usdValue){
            $value=$usdValue($a,'current_value_usd','current_value');
            return ['name'=>$a->user?->name ?? 'Investor','value'=>$value,'share'=>$currentValue>0?round(($value/$currentValue)*100,2):0,'currency'=>$a->currency,'local_principal'=>(float)$a->net_contributions,'usd_principal'=>$usdValue($a,'net_contributions_usd','net_contributions')];
        });

        $returnBands = [
            'positive' => $accounts->filter(fn($a)=>$usdValue($a,'total_profit_usd','total_profit') > 0)->count(),
            'flat' => $accounts->filter(fn($a)=>abs($usdValue($a,'total_profit_usd','total_profit')) < 0.01)->count(),
            'negative' => $accounts->filter(fn($a)=>$usdValue($a,'total_profit_usd','total_profit') < 0)->count(),
        ];

        return view('admin.private-investors.overview', [
            'summary' => [
                'investors' => User::query()->whereIn('role',['private_member','private_investor'])->where('status','active')->count(),
                'agreement_active' => PortfolioInvestmentTerm::query()->where('status','active')->count(),
                'setup_required' => max(0, User::query()->whereIn('role',['private_member','private_investor'])->where('status','active')->count() - PortfolioInvestmentTerm::query()->where('status','active')->count()),
                'portfolio_value' => $currentValue, 'net_contributions' => $contributions, 'total_profit' => $profit,
                'monthly_profit' => $monthly, 'profit_paid' => $profitPaid, 'realized_fx_gain_loss' => $realizedFx, 'return_percent' => $return,
                'open_requests' => PortfolioRequest::query()->whereIn('status',['submitted','under_review'])->count(),
                'published_statements' => MonthlyStatement::query()->whereNotNull('published_at')->count(),
                'monthly_target' => $monthlyTarget, 'mtd_provisional' => $mtdProvisional,
                'draft_entries' => PortfolioTransaction::query()->where('status','draft')->count(),
                'voided_entries' => PortfolioTransaction::query()->where('status','voided')->count(),
                'fx_pending' => $accounts->filter(fn($a)=>strtoupper((string)$a->currency)!=='USD' && $a->current_value_usd === null)->count(),
            ],
            'recentRequests' => PortfolioRequest::query()->with('user')->latest()->limit(8)->get(),
            'recentTransactions' => PortfolioTransaction::query()->with('account.user')->latest('created_at')->limit(10)->get(),
            'topAccounts' => $accounts->sortByDesc(fn($a)=>$usdValue($a,'current_value_usd','current_value'))->take(8)->values(),
            'performance' => $performance, 'performanceChart' => $performanceChart, 'capitalFlow' => $capitalFlow,
            'allocation' => $allocation, 'returnBands' => $returnBands,
        ]);
    }

    public function investors(Request $request)
    {
        $query = User::query()->whereIn('role',['private_member','private_investor'])->with(['portfolioAccount.investmentTerm','pulseAccess.plan'])->latest();
        if ($request->filled('q')) {
            $term = '%'.trim((string)$request->string('q')).'%';
            $query->where(fn ($q) => $q->where('name','like',$term)->orWhere('email','like',$term));
        }
        return view('admin.private-investors.investors', [
            'investors' => $query->paginate(30)->withQueryString(),
            'unconfigured' => User::query()->whereIn('role',['private_member','private_investor'])->where('status','active')->whereDoesntHave('portfolioAccount')->orderBy('name')->get(),
        ]);
    }

    public function show(PortfolioAccount $account, InvestorPerformanceService $performance, InvestorSettlementService $settlements)
    {
        $settlements->processAccount($account, now(), false);
        $account->load(['user.pulseAccess.plan','transactions','statements','requests']);
        $posted = $account->transactions->where('status','posted');
        $activityMetrics = [
            'deposits'=>(float)$posted->where('type','deposit')->sum(fn($r)=>(float)$r->amount),
            'withdrawals'=>(float)$posted->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount),
            'profit_paid'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),
            'profit'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),
            'losses'=>(float)$posted->filter(fn($r)=>in_array($r->type,['loss','fee'],true))->sum(fn($r)=>(float)$r->amount),
            'drafts'=>$account->transactions->where('status','draft')->count(),
            'voided'=>$account->transactions->where('status','voided')->count(),
            'principal_usd'=>(float)($account->net_contributions_usd ?? (strtoupper((string)$account->currency)==='USD'?$account->net_contributions:0)),
            'profit_paid_usd'=>(float)($account->total_profit_usd ?? (strtoupper((string)$account->currency)==='USD'?$account->total_profit:0)),
        ];
        return view('admin.private-investors.show', [
            'account' => $account,
            'requests' => $account->requests()->latest()->limit(10)->get(),
            'statements' => $account->statements()->latest('statement_month')->limit(12)->get(),
            'transactions' => $account->transactions()->latest('transaction_date')->limit(12)->get(),
            'supportConversation' => $account->user->supportConversations()->latest('last_message_at')->first(),
            'performance' => $performance->snapshot($account),
            'agreement' => $performance->agreementSummary($account),
            'activityMetrics'=>$activityMetrics,
            'activityChart'=>$this->capitalFlowChart($account->transactions->where('status','posted')),
        ]);
    }

    public function investmentSetup(PortfolioAccount $account, InvestorPerformanceService $performance, InvestorCurrencyService $currencies)
    {
        $account->load(['user','transactions']);
        $term = $performance->investmentTerm($account);
        $firstInvestment = $account->transactions->where('status','posted')->where('type','deposit')->sortBy('transaction_date')->first();
        $capital = (float) $account->net_contributions;
        return view('admin.private-investors.investment-setup', [
            'account' => $account,
            'term' => $term,
            'firstInvestment' => $firstInvestment,
            'capital' => $capital,
            'fullMonthTarget' => $term ? round($capital * ((float)$term->monthly_target_rate / 100), 2) : 0.0,
            'performance' => $performance->snapshot($account),
            'agreement' => $performance->agreementSummary($account),
            'supportedCurrencies' => $currencies->supported(),
            'currencyEditable' => $currencies->canChange($account),
            'currencyLockMessage' => $currencies->lockMessage($account),
        ]);
    }

    public function saveInvestmentTerm(Request $request, PortfolioAccount $account, InvestorPerformanceService $performance, PulseAuditService $audit, BrandedMailService $mail, InvestorSettlementService $settlements)
    {
        $data = $request->validate([
            'effective_from' => ['required','date'],
            'monthly_target_rate' => ['required','numeric','min:0','max:50'],
            'status' => ['required', Rule::in(['active','paused','closed'])],
            'auto_payout' => ['nullable','boolean'],
            'payout_day' => ['nullable','integer','min:1','max:28'],
            'notes' => ['nullable','string','max:3000'],
        ]);
        $data['auto_payout'] = true;
        $data['payout_day'] = filled($data['payout_day'] ?? null) ? (int)$data['payout_day'] : null;
        $term = $performance->saveInvestmentTerm($account, $data, $request->user()->id);
        $settlements->processAccount($account, now(), false);
        $audit->record('admin.private_investor_investment_terms_saved', $request->user(), 'PortfolioInvestmentTerm', $term->id, null, [
            'portfolio_account_id' => $account->id,
            'effective_from' => $term->effective_from?->toDateString(),
            'monthly_target_rate' => (float)$term->monthly_target_rate,
            'status' => $term->status,
            'auto_payout' => (bool)$term->auto_payout, 'payout_day' => $term->payout_day,
        ], $request);
        PulseAlert::create([
            'user_id'=>$account->user_id,'type'=>'private_investor','title'=>'Investment terms updated',
            'message'=>'Your private portfolio terms now show '.number_format((float)$term->monthly_target_rate,2).'% monthly from '.$term->effective_from?->format('d M Y').'.',
            'severity'=>'info','is_read'=>false,'action_url'=>route('private.index'),'data'=>['portfolio_account_id'=>$account->id,'investment_term_id'=>$term->id],
        ]);
        $mail->investorInvestmentTermsUpdated($account->user, $account, $term);
        return redirect()->route('admin.private-investors.account-performance',$account)
            ->with('success', 'Investment terms saved. Monthly targets and daily provisional progress were generated automatically from the effective date.');
    }

    public function portfolioValues(PortfolioAccount $account, InvestorCurrencyService $currencies)
    {
        $account->load(['user','transactions','statements']);
        $posted = $account->transactions->where('status', 'posted');
        $ledger = [
            'posted_count' => $posted->count(),
            'current_value_effect' => (float) $posted->sum(fn ($row) => (float) $row->current_value_effect),
            'net_contributions_effect' => (float) $posted->sum(fn ($row) => (float) $row->net_contributions_effect),
            'profit_effect' => (float) $posted->sum(fn ($row) => (float) $row->profit_effect),
            'monthly_profit_effect' => (float) $posted->filter(fn ($row) => $row->transaction_date?->format('Y-m') === now()->format('Y-m'))->sum(fn ($row) => (float) $row->monthly_profit_effect),
        ];
        $ledger['recalculated_current_value'] = max(0, round((float) $account->opening_value + $ledger['current_value_effect'], 2));
        $ledger['recalculated_net_investment'] = max(0, round((float) $account->opening_value + $ledger['net_contributions_effect'], 2));
        $ledger['recalculated_profit'] = round($ledger['profit_effect'], 2);
        $ledger['recalculated_month_profit'] = round($ledger['monthly_profit_effect'], 2);

        return view('admin.private-investors.portfolio-values', [
            'account' => $account,
            'ledger' => $ledger,
            'supportedCurrencies' => $currencies->supported(),
            'currencyEditable' => $currencies->canChange($account),
            'currencyLockMessage' => $currencies->lockMessage($account),
        ]);
    }

    public function resetAccountValues(Request $request, PortfolioAccount $account, PulseAuditService $audit)
    {
        $request->validate(['confirm_reset' => ['accepted']]);
        $before = $account->only(['opening_value','current_value','net_contributions','total_profit','monthly_profit','valuation_date']);
        $account->update([
            'opening_value' => 0,
            'current_value' => 0,
            'net_contributions' => 0,
            'total_profit' => 0,
            'monthly_profit' => 0,
            'opening_value_usd' => 0, 'current_value_usd' => 0, 'net_contributions_usd' => 0, 'total_profit_usd' => 0, 'monthly_profit_usd' => 0, 'realized_fx_gain_loss_usd' => 0,
            'valuation_date' => today()->toDateString(),
        ]);
        $audit->record('admin.private_investor_portfolio_values_reset', $request->user(), 'PortfolioAccount', $account->id, null, [
            'before' => $before,
            'after' => $account->fresh()->only(['opening_value','current_value','net_contributions','total_profit','monthly_profit','valuation_date']),
        ], $request);
        return back()->with('success', 'Portfolio values reset to zero. Transactions and statements were not deleted.');
    }

    public function recalculateAccountValues(Request $request, PortfolioAccount $account, PulseAuditService $audit)
    {
        $request->validate(['confirm_recalculate' => ['accepted']]);
        $account->load('transactions');
        $posted = $account->transactions->where('status', 'posted');
        $before = $account->only(['opening_value','current_value','net_contributions','total_profit','monthly_profit','valuation_date']);
        $latestDate = $posted->max(fn ($row) => $row->transaction_date?->toDateString());
        $currentValue = max(0, round((float) $account->opening_value + (float) $posted->sum(fn ($row) => (float) $row->current_value_effect), 2));
        $netInvestment = max(0, round((float) $account->opening_value + (float) $posted->sum(fn ($row) => (float) $row->net_contributions_effect), 2));
        $profit = round((float) $posted->sum(fn ($row) => (float) $row->profit_effect), 2);
        $monthProfit = round((float) $posted->filter(fn ($row) => $row->transaction_date?->format('Y-m') === now()->format('Y-m'))->sum(fn ($row) => (float) $row->monthly_profit_effect), 2);
        $openingUsd = strtoupper((string)$account->currency) === 'USD' ? (float)$account->opening_value : (float)($account->opening_value_usd ?? 0);
        $currentValueUsd = max(0, round($openingUsd + (float)$posted->sum(fn($row)=>(float)($row->usd_current_value_effect ?? 0)),2));
        $netInvestmentUsd = max(0, round($openingUsd + (float)$posted->sum(fn($row)=>(float)($row->usd_net_contributions_effect ?? 0)),2));
        $profitUsd = round((float)$posted->sum(fn($row)=>(float)($row->usd_profit_effect ?? 0)),2);
        $monthProfitUsd = round((float)$posted->filter(fn($row)=>$row->transaction_date?->format('Y-m')===now()->format('Y-m'))->sum(fn($row)=>(float)($row->usd_monthly_profit_effect ?? 0)),2);
        $realizedFx = round((float)$posted->sum(fn($row)=>(float)($row->fx_gain_loss_usd ?? 0)),2);
        $account->update([
            'current_value' => $currentValue,
            'net_contributions' => $netInvestment,
            'total_profit' => $profit,
            'monthly_profit' => $monthProfit,
            'current_value_usd'=>$currentValueUsd, 'net_contributions_usd'=>$netInvestmentUsd, 'total_profit_usd'=>$profitUsd, 'monthly_profit_usd'=>$monthProfitUsd, 'realized_fx_gain_loss_usd'=>$realizedFx,
            'valuation_date' => $latestDate ?: today()->toDateString(),
        ]);
        $audit->record('admin.private_investor_portfolio_values_recalculated', $request->user(), 'PortfolioAccount', $account->id, null, [
            'basis' => 'opening_value_plus_posted_transaction_effects',
            'before' => $before,
            'after' => $account->fresh()->only(['opening_value','current_value','net_contributions','total_profit','monthly_profit','valuation_date']),
            'posted_entries' => $posted->count(),
        ], $request);
        return back()->with('success', 'Portfolio totals recalculated from the opening investment and remaining posted transactions.');
    }

    public function accountPerformance(PortfolioAccount $account, InvestorPerformanceService $performance)
    {
        $account->load(['user','performancePlans.accruals']);
        return view('admin.private-investors.performance', [
            'account' => $account,
            'performance' => $performance->snapshot($account),
            'agreement' => $performance->agreementSummary($account),
            'history' => $performance->history($account, 12),
        ]);
    }

    public function fxQuote(Request $request, InvestorFxService $fx)
    {
        $currency = $fx->normalize((string)$request->query('currency','USD'));
        try {
            return response()->json(['data'=>$fx->quoteToUsd($currency)]);
        } catch (\Throwable $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function accountTransactions(PortfolioAccount $account, BrandedMailService $mail, InvestorCurrencyService $currencies, InvestorSettlementService $settlements)
    {
        $settlements->processAccount($account, now(), false);
        $account->load(['user','investmentTerm']);
        return view('admin.private-investors.account-transactions', [
            'account' => $account,
            'transactions' => $account->transactions()->with(['creator','voidedBy'])->latest('transaction_date')->latest('id')->paginate(30),
            'mailHealth' => $mail->deliveryHealth(),
            'recentInvestorEmails' => EmailDeliveryLog::query()->where('user_id',$account->user_id)->where('event','like','private_investor_%')->latest()->limit(5)->get(),
            'supportedCurrencies' => $currencies->supported(),
            'currencyEditable' => $currencies->canChange($account),
            'currencyLockMessage' => $currencies->lockMessage($account),
        ]);
    }

    public function accountStatements(PortfolioAccount $account, InvestorSettlementService $settlements)
    {
        $settlements->processAccount($account, now(), false);
        $account->load('user');
        return view('admin.private-investors.account-statements', [
            'account' => $account,
            'statements' => $account->statements()->latest('statement_month')->paginate(24),
        ]);
    }

    public function accountRequests(PortfolioAccount $account)
    {
        $account->load('user');
        return view('admin.private-investors.account-requests', [
            'account' => $account,
            'requests' => $account->requests()->latest()->paginate(30),
        ]);
    }

    public function requests(Request $request)
    {
        $query = PortfolioRequest::query()->with(['user','account','processor'])->latest();
        if ($request->filled('status')) $query->where('status',$request->string('status'));
        if ($request->filled('type')) $query->where('type',$request->string('type'));
        return view('admin.private-investors.requests', [
            'requests' => $query->paginate(30)->withQueryString(),
            'counts' => [
                'submitted' => PortfolioRequest::where('status','submitted')->count(),
                'under_review' => PortfolioRequest::where('status','under_review')->count(),
                'approved' => PortfolioRequest::where('status','approved')->count(),
                'completed' => PortfolioRequest::where('status','completed')->count(),
            ],
        ]);
    }

    public function statements(Request $request, InvestorSettlementService $settlements)
    {
        $settlements->processAll(now(), false);
        $query = MonthlyStatement::query()->with('account.user')->latest('statement_month');
        if ($request->filled('user')) {
            $query->whereHas('account', fn ($q) => $q->where('user_id',(int)$request->input('user')));
        }
        return view('admin.private-investors.statements', [
            'statements' => $query->paginate(30)->withQueryString(),
            'members' => User::whereIn('role',['private_member','private_investor'])->orderBy('name')->get(),
        ]);
    }

    public function activity(Request $request)
    {
        $query = PortfolioTransaction::query()->with(['account.user','creator','voidedBy'])->latest('transaction_date')->latest('id');
        if ($request->filled('user')) $query->whereHas('account', fn($q)=>$q->where('user_id',(int)$request->input('user')));
        if ($request->filled('status')) $query->where('status',(string)$request->input('status'));
        if ($request->filled('type')) $query->where('type',(string)$request->input('type'));
        if ($request->filled('from')) $query->whereDate('transaction_date','>=',$request->input('from'));
        if ($request->filled('to')) $query->whereDate('transaction_date','<=',$request->input('to'));
        return view('admin.private-investors.activity', [
            'transactions'=>$query->paginate(50)->withQueryString(),
            'members'=>User::whereIn('role',['private_member','private_investor'])->orderBy('name')->get(),
            'counts'=>[
                'posted'=>PortfolioTransaction::where('status','posted')->count(),
                'draft'=>PortfolioTransaction::where('status','draft')->count(),
                'voided'=>PortfolioTransaction::where('status','voided')->count(),
            ],
        ]);
    }

    public function monthlyPerformance(Request $request, InvestorPerformanceService $performance, InvestorSettlementService $settlements)
    {
        $settlements->processAll(now(), false);
        $accounts = PortfolioAccount::query()
            ->with(['user','performancePlans.accruals'])
            ->where('is_active', true)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('q')).'%';
                $query->whereHas('user', fn ($user) => $user->where('name','like',$term)->orWhere('email','like',$term));
            })
            ->orderByDesc('current_value')
            ->get();

        $rows = $accounts->map(function (PortfolioAccount $account) use ($performance) {
            $snapshot = $performance->snapshot($account);
            $agreement = $performance->agreementSummary($account);
            $fx = $this->latestFxRateToUsd($account);
            $mtd = (float) ($snapshot['mtd'] ?? 0);
            $target = (float) ($snapshot['target'] ?? 0);
            return [
                'account' => $account,
                'term' => $agreement['term'] ?? null,
                'plan' => $snapshot['plan'] ?? null,
                'mtd' => $mtd, 'mtd_usd' => $fx ? round($mtd*$fx,2) : null,
                'target' => $target, 'target_usd' => $fx ? round($target*$fx,2) : null,
                'fx_rate_to_usd' => $fx,
                'progress' => (float) ($snapshot['progress'] ?? 0),
                'performance_to_date' => (float) ($agreement['posted_total'] ?? 0),
                'indicative_value' => (float) ($agreement['indicative_value'] ?? $snapshot['indicative_value'] ?? $account->current_value),
                'days_posted' => (int) ($snapshot['days_posted'] ?? 0),
                'days_total' => (int) ($snapshot['days_total'] ?? 0),
            ];
        });

        return view('admin.private-investors.monthly-performance', [
            'rows' => $rows,
            'summary' => [
                'investors' => $accounts->count(),
                'active_plans' => $rows->filter(fn ($row) => $row['plan'] && $row['plan']->status === 'active')->count(),
                'target' => (float) $rows->sum(fn($row)=>(float)($row['target_usd'] ?? 0)),
                'mtd' => (float) $rows->sum(fn($row)=>(float)($row['mtd_usd'] ?? 0)),
            ],
        ]);
    }

    public function reports(Request $request, InvestorSettlementService $settlements)
    {
        $settlements->processAll(now(), false);
        $accounts = PortfolioAccount::query()->with(['user','statements','requests','transactions','performancePlans.accruals'])
            ->when($request->filled('q'), function($q) use($request){
                $term='%'.trim((string)$request->input('q')).'%';
                $q->whereHas('user',fn($u)=>$u->where('name','like',$term)->orWhere('email','like',$term));
            })
            ->orderByDesc('current_value')->paginate(50)->withQueryString();
        $month = now()->startOfMonth()->toDateString();
        $matrix = $accounts->getCollection()->map(function($a) use($month){
            $plan=$a->performancePlans->first(fn($p)=>$p->plan_month?->startOfMonth()->toDateString()===$month);
            $mtd=$plan ? (float)$plan->accruals->whereNotNull('posted_at')->sum(fn($r)=>(float)$r->posted_amount) : 0.0;
            $target=$plan ? (float)$plan->target_amount : 0.0;
            $last=$a->statements->sortByDesc('statement_month')->first();
            $fx=$this->latestFxRateToUsd($a);
            return [
                'account'=>$a,
                'return'=>(float)$a->net_contributions>0?((float)$a->total_profit/(float)$a->net_contributions)*100:0,
                'mtd'=>$mtd,'target'=>$target,'progress'=>abs($target)>0.0001?($mtd/$target)*100:0,
                'mtd_usd'=>$fx?round($mtd*$fx,2):null,'target_usd'=>$fx?round($target*$fx,2):null,'fx_rate_to_usd'=>$fx,
                'current_value_usd'=>(float)($a->current_value_usd ?? (strtoupper((string)$a->currency)==='USD'?$a->current_value:0)),
                'net_contributions_usd'=>(float)($a->net_contributions_usd ?? (strtoupper((string)$a->currency)==='USD'?$a->net_contributions:0)),
                'total_profit_usd'=>(float)($a->total_profit_usd ?? (strtoupper((string)$a->currency)==='USD'?$a->total_profit:0)),
                'profit_paid'=>(float)$a->transactions->where('status','posted')->where('type','profit')->sum(fn($tx)=>(float)$tx->amount),
                'profit_paid_usd'=>(float)$a->transactions->where('status','posted')->where('type','profit')->sum(fn($tx)=>(float)($tx->usd_profit_effect ?? 0)),
                'realized_fx_gain_loss_usd'=>(float)($a->realized_fx_gain_loss_usd ?? 0),
                'open_requests'=>$a->requests->whereIn('status',['submitted','under_review'])->count(),
                'last_statement'=>$last,
            ];
        });
        return view('admin.private-investors.reports', [
            'accounts'=>$accounts,
            'matrix'=>$matrix,
            'generatedAt'=>now(),
        ]);
    }

    public function exportReports(Request $request): StreamedResponse
    {
        $accounts = PortfolioAccount::query()->with(['user','statements'])->orderByDesc('current_value')->get();
        return response()->streamDownload(function() use($accounts): void {
            $out=fopen('php://output','w');
            fputcsv($out,['ABS Private Investor Portfolio Report']);
            fputcsv($out,['Generated at',now()->toDateTimeString()]);
            fputcsv($out,[]);
            fputcsv($out,['Investor','Email','Investor Currency','Investor Principal (Local)','Reported Capital (Local)','Profit Paid / Net P&L (Local)','Investor Principal USD Basis','Reported Capital USD','Profit Paid / Net P&L USD','Realized FX Gain/Loss USD','Return %','Current Performance Month Paid (Local)','Valuation Date','Last Statement']);
            foreach($accounts as $a){
                $last=$a->statements->sortByDesc('statement_month')->first();
                $ret=(float)$a->net_contributions>0?((float)$a->total_profit/(float)$a->net_contributions)*100:0;
                fputcsv($out,[$a->user?->name,$a->user?->email,$a->currency,$a->net_contributions,$a->current_value,$a->total_profit,$a->net_contributions_usd ?? (strtoupper((string)$a->currency)==='USD'?$a->net_contributions:null),$a->current_value_usd ?? (strtoupper((string)$a->currency)==='USD'?$a->current_value:null),$a->total_profit_usd ?? (strtoupper((string)$a->currency)==='USD'?$a->total_profit:null),$a->realized_fx_gain_loss_usd ?? 0,round($ret,2),$a->monthly_profit,$a->valuation_date?->toDateString(),$last?->statement_month?->format('Y-m')]);
            }
            fclose($out);
        }, 'ABS-Private-Investor-Report-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv']);
    }

    public function createForUser(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users','id')],
            'account_name' => ['required','string','max:120'],
            'currency' => ['required','string','max:10', Rule::in(config('private_investor.supported_currencies',['USD']))],
            'opening_value' => ['required','numeric','min:0'],
            'current_value' => ['required','numeric','min:0'],
            'net_contributions' => ['required','numeric','min:0'],
            'total_profit' => ['required','numeric'],
            'monthly_profit' => ['required','numeric'],
            'valuation_date' => ['required','date'],
            'notes' => ['nullable','string','max:4000'],
        ]);
        $user = User::findOrFail((int)$data['user_id']);
        abort_unless(in_array($user->role,['private_member','private_investor'],true),422,'This account must have the Private Investor role.');
        $data['currency'] = strtoupper((string)$data['currency']);
        if ($request->boolean('quick_setup')) {
            // Quick setup creates the account shell only. Record the actual investment from Transactions so FX and agreement terms are audited.
            $data['opening_value'] = 0; $data['current_value'] = 0; $data['net_contributions'] = 0;
            $data['total_profit'] = 0; $data['monthly_profit'] = 0;
        }
        $usdNative = $data['currency'] === 'USD';
        $data['opening_value_usd'] = $usdNative ? (float)$data['opening_value'] : null;
        $data['current_value_usd'] = $usdNative ? (float)$data['current_value'] : null;
        $data['net_contributions_usd'] = $usdNative ? (float)$data['net_contributions'] : null;
        $data['total_profit_usd'] = $usdNative ? (float)$data['total_profit'] : null;
        $data['monthly_profit_usd'] = $usdNative ? (float)$data['monthly_profit'] : null;
        $account = PortfolioAccount::updateOrCreate(['user_id'=>$user->id], $data + ['is_active'=>true]);
        $audit->record('admin.private_investor_portfolio_saved', $request->user(), 'PortfolioAccount', $account->id, null, [
            'user_id' => $user->id, 'currency' => $account->currency, 'current_value' => (float) $account->current_value,
        ], $request);
        return redirect()->route('admin.private-investors.show',$account)->with('success','Private investor portfolio saved.');
    }

    // Backward-compatible route used by older Admin links.
    public function store(Request $request, PulseAuditService $audit) { return $this->createForUser($request, $audit); }

    public function updateCurrency(Request $request, PortfolioAccount $account, InvestorCurrencyService $currencies, PulseAuditService $audit)
    {
        $data = $request->validate([
            'currency' => ['required','string','max:10', Rule::in($currencies->supported())],
        ]);
        $before = strtoupper((string) $account->currency);
        $updated = $currencies->change($account, (string) $data['currency']);
        $audit->record('admin.private_investor_currency_updated', $request->user(), 'PortfolioAccount', $updated->id, null, [
            'before' => $before, 'after' => $updated->currency, 'user_id' => $updated->user_id,
        ], $request);

        return back()->with('success', 'Investor principal currency updated to '.$updated->currency.'. It will lock when financial activity begins.');
    }

    public function updateAccount(Request $request, PortfolioAccount $account, PulseAuditService $audit, InvestorCurrencyService $currencies)
    {
        $data = $request->validate([
            'account_name'=>['required','string','max:120'], 'currency'=>['required','string','max:10', Rule::in($currencies->supported())],
            'opening_value'=>['required','numeric','min:0'], 'current_value'=>['required','numeric','min:0'],
            'net_contributions'=>['required','numeric','min:0'], 'total_profit'=>['required','numeric'],
            'monthly_profit'=>['required','numeric'], 'valuation_date'=>['required','date'],
            'notes'=>['nullable','string','max:4000'], 'is_active'=>['nullable','boolean'],
        ]);
        $requestedCurrency = $currencies->normalize((string) $data['currency']);
        $before = $account->only(['account_name','currency','opening_value','current_value','net_contributions','total_profit','monthly_profit','valuation_date','is_active']);

        if ($requestedCurrency !== strtoupper((string) $account->currency)) {
            $account = $currencies->change($account, $requestedCurrency);
        }
        $data['currency'] = $account->currency;

        if ($data['currency'] === 'USD') {
            $data += ['opening_value_usd'=>(float)$data['opening_value'],'current_value_usd'=>(float)$data['current_value'],'net_contributions_usd'=>(float)$data['net_contributions'],'total_profit_usd'=>(float)$data['total_profit'],'monthly_profit_usd'=>(float)$data['monthly_profit']];
        }
        $account->update($data + ['is_active'=>$request->boolean('is_active')]);
        $audit->record('admin.private_investor_portfolio_updated', $request->user(), 'PortfolioAccount', $account->id, null, [
            'before' => $before,
            'after' => $account->fresh()->only(['account_name','currency','opening_value','current_value','net_contributions','total_profit','monthly_profit','valuation_date','is_active']),
        ], $request);
        return back()->with('success','Portfolio valuation updated.');
    }


    public function savePerformancePlan(Request $request, PortfolioAccount $account, InvestorPerformanceService $performance, PulseAuditService $audit)
    {
        $data = $request->validate([
            'plan_month'=>['required','date'],
            'base_amount'=>['required','numeric','min:0'],
            'target_rate'=>['required','numeric','min:-50','max:50'],
            'status'=>['required',Rule::in(['active','paused','closed'])],
            'notes'=>['nullable','string','max:3000'],
        ]);
        $plan = $performance->savePlan($account, $data, $request->user()->id);
        $audit->record('admin.private_investor_performance_plan_saved', $request->user(), 'PortfolioPerformancePlan', $plan->id, null, [
            'portfolio_account_id'=>$account->id,
            'month'=>$plan->plan_month?->format('Y-m'),
            'base_amount'=>(float)$plan->base_amount,
            'target_rate'=>(float)$plan->target_rate,
            'target_amount'=>(float)$plan->target_amount,
        ], $request);
        return back()->with('success','Monthly performance plan saved. Daily provisional accruals were rebalanced to the monthly target.');
    }

    public function adjustDailyAccrual(Request $request, PortfolioAccount $account, PortfolioDailyAccrual $accrual, InvestorPerformanceService $performance, PulseAuditService $audit)
    {
        abort_unless($accrual->plan?->portfolio_account_id === $account->id, 404);
        $data = $request->validate([
            'manual_adjustment'=>['required','numeric','min:-999999999','max:999999999'],
            'admin_note'=>['nullable','string','max:1000'],
        ]);
        $before = (float)$accrual->manual_adjustment;
        $row = $performance->adjustDay($accrual, (float)$data['manual_adjustment'], $request->user()->id, $data['admin_note'] ?? null);
        $audit->record('admin.private_investor_daily_accrual_adjusted', $request->user(), 'PortfolioDailyAccrual', $row->id, null, [
            'portfolio_account_id'=>$account->id,
            'date'=>$row->accrual_date?->toDateString(),
            'from'=>$before,
            'to'=>(float)$row->manual_adjustment,
        ], $request);
        return back()->with('success','Daily provisional adjustment saved. Remaining days were automatically rebalanced so the monthly target is not exceeded.');
    }

    public function transaction(Request $request, PortfolioAccount $account, PulseAuditService $audit, InvestorPortfolioAccountingService $accounting, BrandedMailService $mail, InvestorPerformanceService $performance, InvestorCurrencyService $currencies)
    {
        $data = $request->validate([
            'type'=>['required','in:deposit,withdrawal,profit,loss,fee,adjustment'],
            'amount'=>['required','numeric'], 'transaction_date'=>['required','date'],
            'reference'=>['nullable','string','max:190'], 'description'=>['nullable','string','max:2000'],
            'save_mode'=>['nullable',Rule::in(['post','draft'])],
            'monthly_target_rate'=>['nullable','numeric','min:0','max:50'],
            'performance_start_date'=>['nullable','date'],
            'performance_month'=>['nullable','date'],
            'currency'=>['nullable','string','max:10', Rule::in($currencies->supported())],
            'fx_rate_to_usd'=>['nullable','numeric','gt:0','max:999999'],
        ]);
        $amount = (float)$data['amount'];
        if ($data['type'] === 'adjustment') {
            if (abs($amount) < 0.01) return back()->withErrors(['amount'=>'Adjustment cannot be zero.'])->withInput();
        } elseif ($amount < 0.01) {
            return back()->withErrors(['amount'=>'Enter a positive amount for this activity.'])->withInput();
        }
        $postNow = ($data['save_mode'] ?? 'post') !== 'draft';
        $targetRate = array_key_exists('monthly_target_rate', $data) && $data['monthly_target_rate'] !== null && $data['monthly_target_rate'] !== '' ? (float)$data['monthly_target_rate'] : null;
        $performanceStart = $data['performance_start_date'] ?? $data['transaction_date'];

        $requestedCurrency = $currencies->normalize((string)($data['currency'] ?? $account->currency));
        if ($requestedCurrency !== 'USD' && (float)($data['fx_rate_to_usd'] ?? 0) <= 0) {
            return back()->withErrors(['fx_rate_to_usd'=>'Enter the locked USD conversion / settlement rate for '.$requestedCurrency.'.'])->withInput();
        }
        if ($requestedCurrency !== strtoupper((string)$account->currency)) {
            $beforeCurrency = strtoupper((string)$account->currency);
            $account = $currencies->change($account, $requestedCurrency);
            $audit->record('admin.private_investor_currency_updated_with_transaction', $request->user(), 'PortfolioAccount', $account->id, null, [
                'before'=>$beforeCurrency, 'after'=>$account->currency, 'user_id'=>$account->user_id,
            ], $request);
        }

        $data['currency'] = strtoupper((string)$account->currency);
        $data['fx_rate_to_usd'] = $data['currency'] === 'USD' ? 1.0 : (float)($data['fx_rate_to_usd'] ?? 0);
        $data['fx_source'] = $data['currency'] === 'USD' ? 'native_usd' : 'admin_locked';
        $data['entry_source'] = 'admin_manual';
        $data['performance_month'] = $data['type'] === 'profit'
            ? Carbon::parse($data['performance_month'] ?? $data['transaction_date'])->startOfMonth()->toDateString()
            : null;
        unset($data['save_mode'], $data['monthly_target_rate'], $data['performance_start_date']);
        $transaction = $accounting->create($account, $data, $request->user()->id, $postNow);
        $audit->record($postNow ? 'admin.private_investor_transaction_recorded' : 'admin.private_investor_transaction_draft_created', $request->user(), 'PortfolioTransaction', $transaction->id, null, [
            'portfolio_account_id' => $account->id, 'user_id' => $account->user_id, 'type' => $transaction->type, 'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency, 'fx_rate_to_usd' => $transaction->fx_rate_to_usd !== null ? (float)$transaction->fx_rate_to_usd : null,
            'usd_amount' => $transaction->usd_amount !== null ? (float)$transaction->usd_amount : null,
            'principal_usd_basis' => $transaction->principal_usd_basis !== null ? (float)$transaction->principal_usd_basis : null,
            'settlement_usd_amount' => $transaction->settlement_usd_amount !== null ? (float)$transaction->settlement_usd_amount : null,
            'fx_gain_loss_usd' => (float)($transaction->fx_gain_loss_usd ?? 0),
        ], $request);
        if ($postNow) {
            if ($transaction->type === 'deposit' && $targetRate !== null) {
                $performance->saveInvestmentTerm($account, [
                    'effective_from' => $performanceStart,
                    'monthly_target_rate' => $targetRate,
                    'status' => 'active',
                    'notes' => 'Agreed monthly performance rate configured when the investment was posted.',
                ], $request->user()->id);
                $account->unsetRelation('investmentTerm');
            } elseif (in_array($transaction->type, ['deposit','withdrawal'], true) && $performance->investmentTerm($account)) {
                $performance->rebuildAgreementPlans($account, Carbon::parse($transaction->transaction_date), now(), $request->user()->id);
            }
            $emailReady = $this->notifyTransaction($account, $transaction, $mail);
        }
        if (! $postNow) return back()->with('success','Draft activity saved. It has not changed the investor balance.');
        $message = $transaction->type === 'deposit' && $targetRate !== null
            ? 'Investment posted and the monthly performance schedule was created automatically.'
            : 'Portfolio activity posted successfully.';
        $message .= ($emailReady ?? false) ? ' Investor email was queued for delivery.' : ' In-app alert created; email delivery requires mail setup.';
        return back()->with('success',$message);
    }


    public function updateTransaction(Request $request, PortfolioTransaction $transaction, PulseAuditService $audit, InvestorPortfolioAccountingService $accounting, BrandedMailService $mail, InvestorPerformanceService $performance)
    {
        $data = $request->validate([
            'type'=>['required','in:deposit,withdrawal,profit,loss,fee,adjustment'],
            'amount'=>['required','numeric'],
            'transaction_date'=>['required','date'],
            'reference'=>['nullable','string','max:190'],
            'description'=>['nullable','string','max:2000'],
            'performance_month'=>['nullable','date'],
            'fx_rate_to_usd'=>['nullable','numeric','gt:0','max:999999'],
        ]);
        $amount = (float) $data['amount'];
        if ($data['type'] === 'adjustment') {
            if (abs($amount) < 0.01) return back()->withErrors(['amount'=>'Adjustment cannot be zero.'])->withInput();
        } elseif ($amount < 0.01) {
            return back()->withErrors(['amount'=>'Enter a positive amount for this activity.'])->withInput();
        }

        $transaction->loadMissing('account.user');
        $account = $transaction->account;
        $data['currency'] = strtoupper((string)$account->currency);
        $data['fx_rate_to_usd'] = $data['currency'] === 'USD' ? 1.0 : (float)($data['fx_rate_to_usd'] ?? $transaction->fx_rate_to_usd ?? 0);
        $data['fx_source'] = $data['currency'] === 'USD' ? 'native_usd' : 'admin_locked';
        $data['entry_source'] = $transaction->entry_source ?: 'admin_manual';
        $data['performance_month'] = $data['type'] === 'profit'
            ? Carbon::parse($data['performance_month'] ?? $transaction->performance_month ?? $data['transaction_date'])->startOfMonth()->toDateString()
            : null;
        $before = $transaction->only(['id','portfolio_account_id','type','amount','currency','fx_rate_to_usd','usd_amount','transaction_date','reference','description','status','current_value_effect','net_contributions_effect','profit_effect','monthly_profit_effect','usd_current_value_effect','usd_net_contributions_effect','usd_profit_effect','usd_monthly_profit_effect','principal_usd_basis','settlement_usd_amount','fx_gain_loss_usd']);
        $wasPosted = $transaction->status === 'posted';
        $updated = $accounting->updateEntry($transaction, $data);
        $updated->loadMissing('account.user');

        $audit->record('admin.private_investor_transaction_updated', $request->user(), 'PortfolioTransaction', $updated->id, null, [
            'before'=>$before,
            'after'=>$updated->only(['id','portfolio_account_id','type','amount','transaction_date','reference','description','status','current_value_effect','net_contributions_effect','profit_effect','monthly_profit_effect']),
        ], $request);

        if ($wasPosted && $account && $performance->investmentTerm($account) && (in_array((string)$before['type'], ['deposit','withdrawal'], true) || in_array((string)$updated->type, ['deposit','withdrawal'], true))) {
            $earliest = Carbon::parse(min((string)$before['transaction_date'], $updated->transaction_date?->toDateString() ?: (string)$before['transaction_date']));
            $performance->rebuildAgreementPlans($account, $earliest, now(), $request->user()->id);
        }

        if ($wasPosted && $account?->user) {
            PulseAlert::create([
                'user_id'=>$account->user_id,
                'type'=>'private_investor',
                'title'=>'Portfolio activity updated',
                'message'=>ucfirst($updated->type).' · '.$account->currency.' '.number_format((float)$updated->amount,2).' was updated by ABS Administration.',
                'severity'=>'info',
                'is_read'=>false,
                'action_url'=>route('private.transactions'),
                'data'=>['portfolio_transaction_id'=>$updated->id,'portfolio_account_id'=>$account->id],
            ]);
            $mail->investorTransactionUpdated($account->user, $account->fresh(), $updated, $before);
        }

        return back()->with('success', $wasPosted
            ? 'Portfolio entry updated. Its previous balance effect was reversed and the corrected values were applied.'
            : 'Draft portfolio entry updated.');
    }

    public function deleteTransaction(Request $request, PortfolioTransaction $transaction, PulseAuditService $audit, InvestorPortfolioAccountingService $accounting, BrandedMailService $mail, InvestorPerformanceService $performance)
    {
        $transaction->loadMissing('account.user');
        $account = $transaction->account;
        $user = $account?->user;
        $wasPosted = $transaction->status === 'posted';
        $snapshot = $transaction->only(['id','portfolio_account_id','type','amount','transaction_date','reference','description','status','current_value_effect','net_contributions_effect','profit_effect','monthly_profit_effect','posted_at','voided_at','void_reason']);

        $deleted = $accounting->deleteEntry($transaction);
        $audit->record('admin.private_investor_transaction_deleted', $request->user(), 'PortfolioTransaction', (int)$snapshot['id'], null, [
            'deleted_entry'=>$snapshot,
            'portfolio_account_id'=>$account?->id,
            'user_id'=>$user?->id,
            'balance_effect_reversed'=>$wasPosted,
        ], $request);

        if ($wasPosted && $account && $performance->investmentTerm($account) && in_array((string)$snapshot['type'], ['deposit','withdrawal'], true)) {
            $performance->rebuildAgreementPlans($account, Carbon::parse((string)$snapshot['transaction_date']), now(), $request->user()->id);
        }

        if ($wasPosted && $account && $user) {
            PulseAlert::create([
                'user_id'=>$user->id,
                'type'=>'private_investor',
                'title'=>'Portfolio activity corrected',
                'message'=>ucfirst((string)$snapshot['type']).' · '.$account->currency.' '.number_format((float)$snapshot['amount'],2).' was removed from your portfolio record and its balance effect was reversed.',
                'severity'=>'warning',
                'is_read'=>false,
                'action_url'=>route('private.transactions'),
                'data'=>['deleted_portfolio_transaction_id'=>$snapshot['id'],'portfolio_account_id'=>$account->id],
            ]);
            $mail->investorTransactionDeleted($user, $account->fresh(), $snapshot);
        }

        return back()->with('success', $wasPosted
            ? 'Portfolio entry deleted and its balance effect was automatically reversed.'
            : 'Portfolio entry deleted.');
    }

    public function postDraftTransaction(Request $request, PortfolioTransaction $transaction, PulseAuditService $audit, InvestorPortfolioAccountingService $accounting, BrandedMailService $mail, InvestorPerformanceService $performance)
    {
        $transaction->loadMissing('account.user');
        $posted = $accounting->postDraft($transaction);
        $audit->record('admin.private_investor_transaction_draft_posted', $request->user(), 'PortfolioTransaction', $posted->id, null, [
            'portfolio_account_id'=>$posted->portfolio_account_id,'user_id'=>$posted->account?->user_id,'type'=>$posted->type,'amount'=>(float)$posted->amount,
        ], $request);
        $emailReady = $this->notifyTransaction($posted->account, $posted, $mail);
        if ($performance->investmentTerm($posted->account) && in_array($posted->type, ['deposit','withdrawal'], true)) {
            $performance->rebuildAgreementPlans($posted->account, Carbon::parse($posted->transaction_date), now(), $request->user()->id);
        }
        return back()->with('success',$emailReady ? 'Draft activity posted and investor email queued.' : 'Draft activity posted; in-app alert created and email delivery requires mail setup.');
    }

    public function deleteDraftTransaction(Request $request, PortfolioTransaction $transaction, PulseAuditService $audit, InvestorPortfolioAccountingService $accounting)
    {
        $snapshot = $transaction->only(['id','portfolio_account_id','type','amount','transaction_date','reference']);
        $accounting->deleteDraft($transaction);
        $audit->record('admin.private_investor_transaction_draft_deleted', $request->user(), 'PortfolioTransaction', $snapshot['id'], null, $snapshot, $request);
        return back()->with('success','Draft activity deleted. No investor balance was changed.');
    }

    public function voidTransaction(Request $request, PortfolioTransaction $transaction, PulseAuditService $audit, InvestorPortfolioAccountingService $accounting, BrandedMailService $mail)
    {
        $data = $request->validate(['void_reason'=>['required','string','min:5','max:1000']]);
        $transaction->loadMissing('account.user');
        $voided = $accounting->void($transaction, $request->user()->id, $data['void_reason']);
        $audit->record('admin.private_investor_transaction_voided', $request->user(), 'PortfolioTransaction', $voided->id, null, [
            'portfolio_account_id'=>$voided->portfolio_account_id,'user_id'=>$voided->account?->user_id,'type'=>$voided->type,'amount'=>(float)$voided->amount,'reason'=>$voided->void_reason,
        ], $request);
        PulseAlert::create([
            'user_id'=>$voided->account->user_id,'type'=>'private_investor','title'=>'Portfolio entry corrected',
            'message'=>ucfirst($voided->type).' · '.$voided->account->currency.' '.number_format((float)$voided->amount,2).' was voided and its balance effect reversed.',
            'severity'=>'warning','is_read'=>false,'action_url'=>route('private.transactions'),
            'data'=>['portfolio_transaction_id'=>$voided->id,'portfolio_account_id'=>$voided->account->id],
        ]);
        $mail->investorTransactionVoided($voided->account->user, $voided->account, $voided);
        return back()->with('success','Posted activity voided, balance effect reversed, and investor notified.');
    }

    public function statement(Request $request, PortfolioAccount $account, PulseAuditService $audit, BrandedMailService $mail, InvestorSettlementService $settlements)
    {
        $data = $request->validate([
            'statement_month'=>['required','date'],
            'notes'=>['nullable','string','max:4000'],
        ]);
        $month = Carbon::parse($data['statement_month'])->startOfMonth();
        if ($month->gt(now()->startOfMonth())) {
            return back()->withErrors(['statement_month'=>'Choose a completed or current performance month.'])->withInput();
        }

        // First run the same idempotent settlement path used by cron/API/web so Admin
        // never has to type balances that already exist in the audited ledger.
        $settlements->processAccount($account, now(), false);
        $account->refresh();
        $statementData = $settlements->statementData($account, $month);
        $existing = $account->statements()->whereDate('statement_month',$month->toDateString())->first();
        $statement = $account->statements()->updateOrCreate(
            ['statement_month'=>$month->toDateString()],
            $statementData + [
                'auto_generated'=>true,
                'published_at'=>$existing?->published_at ?: now(),
                'notes'=>trim((string)($data['notes'] ?? '')) ?: ($existing?->notes ?: 'Reconciled automatically from posted capital activity and profit payout records.'),
            ]
        );
        $settlements->recalculateAccount($account);

        $audit->record('admin.private_investor_statement_reconciled', $request->user(), 'MonthlyStatement', $statement->id, null, [
            'portfolio_account_id'=>$account->id,'user_id'=>$account->user_id,'statement_month'=>$month->toDateString(),
            'profit_loss'=>(float)$statement->profit_loss,'profit_paid'=>(float)$statement->profit_paid,'closing_balance'=>(float)$statement->closing_balance,
        ], $request);
        PulseAlert::create([
            'user_id'=>$account->user_id,'type'=>'private_investor','title'=>'Investor statement updated',
            'message'=>'Your '.$month->format('F Y').' portfolio statement has been reconciled and is available.',
            'severity'=>'info','is_read'=>false,'action_url'=>route('private.statement',$statement),
            'data'=>['portfolio_account_id'=>$account->id,'monthly_statement_id'=>$statement->id],
        ]);
        $mail->investorStatementPublished($account->user, $account->fresh(), $statement);
        return back()->with('success','Monthly statement reconciled from the audited ledger. No balance was manually overwritten.');
    }

    public function requestStatus(Request $request, PortfolioRequest $portfolioRequest, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'status'=>['required',Rule::in(['submitted','under_review','approved','declined','completed'])],
            'admin_note'=>['nullable','string','max:3000'],
        ]);
        $before = $portfolioRequest->status;
        $portfolioRequest->update([
            'status'=>$data['status'], 'admin_note'=>$data['admin_note'] ?? null,
            'processed_by'=>$request->user()->id,
            'processed_at'=>in_array($data['status'],['approved','declined','completed'],true) ? now() : null,
        ]);
        PulseAlert::create([
            'user_id'=>$portfolioRequest->user_id, 'type'=>'private_investor', 'title'=>'Portfolio request updated',
            'message'=>'Your '.str_replace('_',' ',$portfolioRequest->type).' request is now '.str_replace('_',' ',$data['status']).'.',
            'severity'=>$data['status']==='declined'?'warning':'info', 'is_read'=>false,
            'action_url'=>route('private.requests'), 'data'=>['portfolio_request_id'=>$portfolioRequest->id],
        ]);
        $audit->record('admin.private_investor_request_updated',$request->user(),'PortfolioRequest',$portfolioRequest->id,null,[
            'from'=>$before,'to'=>$data['status'],'user_id'=>$portfolioRequest->user_id,
        ],$request);
        $mail->investorRequestUpdated($portfolioRequest->user, $portfolioRequest->fresh());
        return back()->with('success','Investor request updated and notification sent.');
    }

    private function notifyTransaction(PortfolioAccount $account, PortfolioTransaction $transaction, BrandedMailService $mail): bool
    {
        $account->refresh()->loadMissing('user');
        $label = match($transaction->type) {
            'deposit'=>'Investment recorded','withdrawal'=>'Capital withdrawal recorded','profit'=>'Profit paid','loss'=>'Loss recorded','fee'=>'Fee recorded',default=>'Portfolio adjustment recorded',
        };
        $severity = in_array($transaction->type,['withdrawal','loss','fee'],true) ? 'warning' : 'info';
        PulseAlert::create([
            'user_id'=>$account->user_id,'type'=>'private_investor','title'=>$label,
            'message'=>$account->currency.' '.number_format(abs((float)$transaction->amount),2).' · '.Carbon::parse($transaction->transaction_date)->format('d M Y'),
            'severity'=>$severity,'is_read'=>false,'action_url'=>route('private.transactions'),
            'data'=>['portfolio_transaction_id'=>$transaction->id,'portfolio_account_id'=>$account->id],
        ]);
        $mail->investorTransactionPosted($account->user, $account, $transaction);
        return ($mail->deliveryHealth()['status'] ?? 'setup_required') === 'ready';
    }

    private function capitalFlowChart($rows, bool $usd = false): array
    {
        $months=collect(range(0,11))->map(fn($i)=>now()->subMonths(11-$i)->startOfMonth());
        $labels=[];$deposits=[];$withdrawals=[];$performance=[];
        foreach($months as $month){
            $key=$month->format('Y-m');
            $slice=$rows->filter(fn($r)=>$r->transaction_date?->format('Y-m')===$key);
            $labels[]=$month->format('M y');
            $value = fn($r) => $usd ? (float)($r->usd_amount ?? (strtoupper((string)$r->currency)==='USD'?$r->amount:0)) : (float)$r->amount;
            $deposits[]=(float)$slice->where('type','deposit')->sum(fn($r)=>$value($r));
            $withdrawals[]=(float)$slice->where('type','withdrawal')->sum(fn($r)=>$value($r));
            $performance[]=(float)$slice->filter(fn($r)=>in_array($r->type,['profit','loss','fee'],true))->sum(function($r) use($value){
                return $r->type==='profit' ? $value($r) : -1*$value($r);
            });
        }
        return compact('labels','deposits','withdrawals','performance');
    }

    private function latestFxRateToUsd(?PortfolioAccount $account): ?float
    {
        if (! $account) return null;
        if (strtoupper((string)$account->currency) === 'USD') return 1.0;
        $rate = $account->transactions()->where('status','posted')->whereNotNull('fx_rate_to_usd')->latest('transaction_date')->latest('id')->value('fx_rate_to_usd');
        if ($rate && (float)$rate > 0) return (float)$rate;
        $rate = $account->statements()->whereNotNull('fx_rate_to_usd')->latest('statement_month')->value('fx_rate_to_usd');
        return $rate && (float)$rate > 0 ? (float)$rate : null;
    }

    private function performanceChart($rows): array
    {
        if ($rows->isEmpty()) return ['points'=>'','labels'=>[],'values'=>[]];
        $values = $rows->map(fn ($row) => (float)$row->closing_balance)->values();
        $min=(float)$values->min(); $max=(float)$values->max(); $range=max(1.0,$max-$min); $count=max(1,$values->count()-1);
        $points=$values->map(function($value,$index) use($min,$range,$count){
            $x=4+($index/$count)*92; $y=84-(((float)$value-$min)/$range)*68;
            return number_format($x,2,'.','').','.number_format($y,2,'.','');
        })->implode(' ');
        return ['points'=>$points,'labels'=>$rows->map(fn($row)=>\Carbon\Carbon::parse($row->statement_month)->format('M y'))->all(),'values'=>$values->all()];
    }
}
