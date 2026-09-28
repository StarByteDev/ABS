<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MonthlyStatement;
use App\Models\PortfolioRequest as InvestorRequest;
use App\Models\PulseAlert;
use App\Models\User;
use App\Services\InvestorPerformanceService;
use App\Services\InvestorCurrencyService;
use App\Services\InvestorSettlementService;
use App\Services\PulseAuditService;
use App\Services\BrandedMailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivatePortalController extends Controller
{
    public function account(Request $request, InvestorPerformanceService $performance, InvestorCurrencyService $currencies, InvestorSettlementService $settlements)
    {
        $account = $request->user()->portfolioAccount()->with(['transactions','statements'])->first();
        if ($account) { $settlements->processAccount($account, now(), false); $account->refresh()->load(['transactions','statements']); }
        $payload = $this->accountPayload($account, $request->user());
        if ($account && $payload) {
            $payload['supported_currencies'] = $currencies->supported();
            $payload['currency_editable'] = $currencies->canChange($account);
            $payload['currency_lock_message'] = $currencies->lockMessage($account);
            $snapshot = $performance->snapshot($account);
            $agreement = $performance->agreementSummary($account);
            $payload['investment_agreement'] = $agreement['term'] ? [
                'effective_from'=>$agreement['term']->effective_from?->toDateString(),
                'monthly_target_rate'=>(float)$agreement['term']->monthly_target_rate,
                'status'=>$agreement['term']->status,
                'auto_payout'=>(bool)$agreement['term']->auto_payout,
                'payout_day'=>$agreement['term']->payout_day,
                'payout_timing'=>$agreement['term']->payout_day ? 'day_'.$agreement['term']->payout_day.'_of_following_month' : 'month_end',
                'full_month_target'=>(float)$agreement['full_month_target'],
                'performance_to_date'=>(float)$agreement['posted_total'],
                'indicative_value'=>(float)$agreement['indicative_value'],
                'generated_months'=>(int)$agreement['months'],
            ] : null;
            $payload['performance_plan'] = $snapshot['plan'] ? [
                'month'=>$snapshot['plan']->plan_month?->format('Y-m'),
                'accrual_start_date'=>$snapshot['plan']->accrual_start_date?->toDateString(),
                'accrual_end_date'=>$snapshot['plan']->accrual_end_date?->toDateString(),
                'base_amount'=>(float)$snapshot['plan']->base_amount,
                'target_rate'=>(float)$snapshot['plan']->target_rate,
                'target_amount'=>(float)$snapshot['target'],
                'mtd_provisional'=>(float)$snapshot['mtd'],
                'indicative_value'=>(float)$agreement['indicative_value'],
                'progress_percent'=>round((float)$snapshot['progress'],2),
                'days_posted'=>$snapshot['days_posted'],
                'days_total'=>$snapshot['days_total'],
                'daily'=>$snapshot['daily']->map(fn($row)=>[
                    'date'=>$row->accrual_date?->toDateString(),
                    'scheduled_at'=>$row->scheduled_at?->toIso8601String(),
                    'amount'=>$row->posted_at ? (float)$row->posted_amount : null,
                    'posted_at'=>$row->posted_at?->toIso8601String(),
                ])->values()->all(),
            ] : null;
            $payload['performance_history'] = $performance->history($account,12)->map(fn($row)=>[
                'month'=>$row['plan']->plan_month?->format('Y-m'),
                'active_from'=>$row['plan']->accrual_start_date?->toDateString(),
                'active_to'=>$row['plan']->accrual_end_date?->toDateString(),
                'equivalent_capital'=>(float)$row['plan']->base_amount,
                'rate'=>(float)$row['plan']->target_rate,
                'target'=>(float)$row['target'],
                'posted'=>(float)$row['posted'],
                'progress_percent'=>round((float)$row['progress'],2),
            ])->values()->all();
        }
        return response()->json(['data'=>$payload]);
    }

    public function transactions(Request $request, InvestorSettlementService $settlements)
    {
        $account = $request->user()->portfolioAccount()->first();
        if ($account) { $settlements->processAccount($account, now(), false); $account->refresh(); }
        $rows = $account ? $account->transactions()->whereIn('status',['posted','voided'])->paginate(min(100,max(1,$request->integer('per_page',25)))) : null;
        return response()->json(['data'=>$rows]);
    }

    public function statements(Request $request, InvestorSettlementService $settlements)
    {
        $account = $request->user()->portfolioAccount()->first();
        if ($account) { $settlements->processAccount($account, now(), false); $account->refresh(); }
        $rows = $account ? $account->statements()->paginate(min(100,max(1,$request->integer('per_page',20)))) : null;
        return response()->json(['data'=>$rows]);
    }

    public function statement(Request $request, MonthlyStatement $statement, InvestorSettlementService $settlements)
    {
        abort_unless($statement->account?->user_id === $request->user()->id,403);
        if ($statement->account) { $settlements->processAccount($statement->account, now(), false); $statement->refresh(); }
        return response()->json(['data'=>$statement]);
    }

    public function requests(Request $request)
    {
        return response()->json(['data'=>$request->user()->portfolioRequests()->latest()->paginate(min(100,max(1,$request->integer('per_page',20))))]);
    }

    public function updateCurrency(Request $request, InvestorCurrencyService $currencies, PulseAuditService $audit)
    {
        $account = $request->user()->portfolioAccount()->first();
        abort_unless($account, 422, 'Investor portfolio is not configured.');
        $data = $request->validate([
            'currency'=>['required','string','max:10',Rule::in($currencies->supported())],
        ]);
        $before = strtoupper((string)$account->currency);
        $updated = $currencies->change($account, (string)$data['currency']);
        $audit->record('private_investor.api_currency_updated', $request->user(), 'PortfolioAccount', $updated->id, null, [
            'before'=>$before, 'after'=>$updated->currency,
        ], $request);
        return response()->json(['data'=>[
            'currency'=>$updated->currency,
            'supported_currencies'=>$currencies->supported(),
            'currency_editable'=>$currencies->canChange($updated),
            'currency_lock_message'=>$currencies->lockMessage($updated),
        ],'message'=>'Principal currency updated.']);
    }

    public function storeRequest(Request $request, BrandedMailService $mail, InvestorCurrencyService $currencies, PulseAuditService $audit)
    {
        $account = $request->user()->portfolioAccount()->first();
        abort_unless($account,422,'Investor portfolio is not configured.');
        $data = $request->validate([
            'type'=>['required',Rule::in(['add_investment','withdrawal','portfolio_review'])],
            'amount'=>['nullable','numeric','min:0.01','max:999999999999.99'],
            'currency'=>['nullable','string','max:10',Rule::in($currencies->supported())],
            'message'=>['nullable','string','max:3000'],
        ]);
        if (in_array($data['type'],['add_investment','withdrawal'],true) && empty($data['amount'])) {
            return response()->json(['message'=>'Amount is required for this request.'],422);
        }

        $requestedCurrency = strtoupper((string)($data['currency'] ?? $account->currency));
        if ($data['type']==='add_investment' && $requestedCurrency !== strtoupper((string)$account->currency)) {
            $beforeCurrency = strtoupper((string)$account->currency);
            $account = $currencies->change($account, $requestedCurrency);
            $audit->record('private_investor.api_currency_updated_with_investment_request', $request->user(), 'PortfolioAccount', $account->id, null, [
                'before'=>$beforeCurrency, 'after'=>$account->currency,
            ], $request);
        }
        if ($data['type']==='withdrawal') {
            $requestedCurrency = strtoupper((string)$account->currency);
        }
        if ($data['type']==='portfolio_review') {
            $data['amount'] = null;
            $requestedCurrency = strtoupper((string)$account->currency);
        }
        if ($data['type']==='withdrawal' && (float)$data['amount'] > (float)$account->net_contributions) {
            return response()->json(['message'=>'Capital withdrawal exceeds the remaining investor principal.'],422);
        }
        if ($request->user()->portfolioRequests()->whereIn('status',['submitted','under_review'])->count() >= 5) {
            return response()->json(['message'=>'Too many open investor requests.'],422);
        }
        $row = InvestorRequest::create([
            'user_id'=>$request->user()->id,'portfolio_account_id'=>$account->id,'type'=>$data['type'],
            'amount'=>$data['amount']??null,'currency'=>$requestedCurrency,'status'=>'submitted',
            'message'=>trim((string)($data['message']??'')) ?: null,
        ]);
        $this->alertAdmins($request->user(),$row);
        $mail->investorRequestReceived($request->user(),$row);
        $mail->adminInvestorRequest($request->user(),$row);
        return response()->json(['data'=>$row,'message'=>'Investor request submitted.'],201);
    }

    public function cancelRequest(Request $request, InvestorRequest $portfolioRequest, BrandedMailService $mail)
    {
        abort_unless($portfolioRequest->user_id===$request->user()->id,404);
        abort_unless($portfolioRequest->status==='submitted',422,'Only submitted requests can be cancelled.');
        $portfolioRequest->update(['status'=>'cancelled']);
        $mail->investorRequestCancelled($request->user(), $portfolioRequest->fresh());
        return response()->json(['data'=>$portfolioRequest->fresh(),'message'=>'Request cancelled.']);
    }

    private function accountPayload($account, User $user): ?array
    {
        if (! $account) return null;
        $invested=(float)$account->net_contributions;
        $profit=(float)$account->total_profit;
        $latest=$account->statements->sortByDesc('statement_month')->first();
        $posted=$account->transactions->where('status','posted');
        return [
            'id'=>$account->id,'account_name'=>$account->account_name,'currency'=>$account->currency,
            'opening_value'=>(float)$account->opening_value,'current_value'=>(float)$account->current_value,
            'net_contributions'=>$invested,'investor_principal'=>$invested,'total_profit'=>$profit,'profit_paid'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),'monthly_profit'=>(float)$account->monthly_profit,
            'valuation_date'=>$account->valuation_date?->toDateString(),'is_active'=>(bool)$account->is_active,'notes'=>$account->notes,
            'overall_return_percent'=>$invested>0?round(($profit/$invested)*100,2):0,
            'latest_monthly_return_percent'=>$latest && (float)$latest->opening_balance>0?round(((float)$latest->profit_loss/(float)$latest->opening_balance)*100,2):0,
            'open_requests'=>$user->portfolioRequests()->whereIn('status',['submitted','under_review'])->count(),
            'activity_summary'=>[
                'investment'=>(float)$posted->where('type','deposit')->sum(fn($r)=>(float)$r->amount),
                'capital_withdrawal'=>(float)$posted->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount),
                'withdrawal'=>(float)$posted->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount),
                'profit_paid'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),
                'profit'=>(float)$posted->where('type','profit')->sum(fn($r)=>(float)$r->amount),
                'losses_and_fees'=>(float)$posted->filter(fn($r)=>in_array($r->type,['loss','fee'],true))->sum(fn($r)=>(float)$r->amount),
            ],
            'activity_chart'=>$this->activityChart($posted),
            'recent_transactions'=>$account->transactions->whereIn('status',['posted','voided'])->take(8)->values(),
            'recent_statements'=>$account->statements->take(12)->values(),
            'privacy'=>'private_account_only',
        ];
    }

    private function activityChart($rows): array
    {
        $months=collect(range(0,11))->map(fn($i)=>now()->subMonths(11-$i)->startOfMonth());
        $labels=[];$investment=[];$withdrawals=[];$performance=[];
        foreach($months as $month){
            $key=$month->format('Y-m');
            $slice=$rows->filter(fn($r)=>$r->transaction_date?->format('Y-m')===$key);
            $labels[]=$month->format('M y');
            $investment[]=(float)$slice->where('type','deposit')->sum(fn($r)=>(float)$r->amount);
            $withdrawals[]=(float)$slice->where('type','withdrawal')->sum(fn($r)=>(float)$r->amount);
            $performance[]=(float)$slice->filter(fn($r)=>in_array($r->type,['profit','loss','fee'],true))->sum(fn($r)=>$r->type==='profit'?(float)$r->amount:-1*(float)$r->amount);
        }
        return compact('labels','investment','withdrawals','performance');
    }

    private function alertAdmins(User $user, InvestorRequest $row): void
    {
        foreach (User::query()->where('role','admin')->where('status','active')->get(['id']) as $admin) {
            PulseAlert::create([
                'user_id'=>$admin->id,'type'=>'private_investor','title'=>'New private investor request',
                'message'=>$user->name.' · '.str_replace('_',' ',$row->type).($row->amount?' · '.$row->currency.' '.number_format((float)$row->amount,2):''),
                'severity'=>$row->type==='withdrawal'?'warning':'info','is_read'=>false,
                'action_url'=>route('admin.private-investors.requests'),'data'=>['portfolio_request_id'=>$row->id],
            ]);
        }
    }
}
