<?php

namespace App\Services;

use App\Models\MonthlyStatement;
use App\Models\PortfolioAccount;
use App\Models\PortfolioInvestmentTerm;
use App\Models\PortfolioPerformancePlan;
use App\Models\PulseAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Finalizes due Private Investor months.
 *
 * Daily performance accruals are provisional. At the agreed payout date the
 * month's performance is converted into a posted Profit transaction (money paid
 * outside the portfolio) and a reconciled MonthlyStatement. Paid profit does not
 * increase investor capital. Capital withdrawals remain a separate principal flow.
 */
class InvestorSettlementService
{
    public function __construct(
        private InvestorPerformanceService $performance,
        private InvestorPortfolioAccountingService $accounting,
        private InvestorFxService $fx,
        private BrandedMailService $mail,
    ) {}

    public function processAll(?Carbon $asOf = null, bool $notify = true): array
    {
        $asOf = ($asOf ?: now())->copy();
        $summary = ['accounts'=>0,'months'=>0,'payouts_created'=>0,'statements_created'=>0,'statements_updated'=>0,'skipped'=>0,'errors'=>[]];

        PortfolioInvestmentTerm::query()->with('account.user')->where('status','active')->orderBy('id')->chunkById(50, function ($terms) use (&$summary, $asOf, $notify): void {
            foreach ($terms as $term) {
                if (! $term->account || ! $term->account->is_active) continue;
                $summary['accounts']++;
                try {
                    $result = $this->processAccount($term->account, $asOf, $notify);
                    foreach (['months','payouts_created','statements_created','statements_updated','skipped'] as $key) {
                        $summary[$key] += (int)($result[$key] ?? 0);
                    }
                } catch (Throwable $e) {
                    $summary['errors'][] = ['portfolio_account_id'=>$term->portfolio_account_id,'message'=>$e->getMessage()];
                }
            }
        });

        return $summary;
    }

    public function processAccount(PortfolioAccount $account, ?Carbon $asOf = null, bool $notify = false): array
    {
        $asOf = ($asOf ?: now())->copy();
        $term = $this->performance->investmentTerm($account);
        $summary = ['months'=>0,'payouts_created'=>0,'statements_created'=>0,'statements_updated'=>0,'skipped'=>0];
        if (! $term || $term->status !== 'active' || ! $term->effective_from) return $summary;

        $firstMonth = $term->effective_from->copy()->startOfMonth();
        $lastCandidate = $asOf->copy()->startOfMonth();
        if ($firstMonth->gt($lastCandidate)) return $summary;

        // Include the current calendar month only when its configured settlement moment is due.
        for ($month = $firstMonth->copy(); $month->lte($lastCandidate); $month->addMonth()) {
            $settlementDate = $this->settlementDate($term, $month);
            if ($asOf->lt($settlementDate)) continue;
            $summary['months']++;

            $result = $this->settleMonth($account, $term, $month->copy(), $settlementDate, $asOf, $notify);
            if (($result['payout_created'] ?? false)) $summary['payouts_created']++;
            if (($result['statement_created'] ?? false)) $summary['statements_created']++;
            if (($result['statement_updated'] ?? false)) $summary['statements_updated']++;
            if (($result['skipped'] ?? false)) $summary['skipped']++;
        }

        $this->recalculateAccount($account);
        return $summary;
    }

    public function settlementDate(PortfolioInvestmentTerm $term, Carbon $performanceMonth): Carbon
    {
        $performanceMonth = $performanceMonth->copy()->startOfMonth();
        $day = (int)($term->payout_day ?? 0);
        if ($day >= 1 && $day <= 28) {
            return $performanceMonth->copy()->addMonthNoOverflow()->day($day)->startOfDay();
        }
        return $performanceMonth->copy()->endOfMonth()->endOfDay();
    }

    private function settleMonth(PortfolioAccount $account, PortfolioInvestmentTerm $term, Carbon $month, Carbon $settlementDate, Carbon $asOf, bool $notify): array
    {
        $month = $month->copy()->startOfMonth();
        $result = ['payout_created'=>false,'statement_created'=>false,'statement_updated'=>false,'skipped'=>false];

        return DB::transaction(function () use ($account, $term, $month, $settlementDate, $asOf, $notify, $result): array {
            $locked = PortfolioAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            // Existing published/manual profit records are authoritative. The system never creates
            // a second payout for a month that already has one.
            $existingPayouts = $locked->transactions()
                ->where('status','posted')->where('type','profit')
                ->where(function ($query) use ($month) {
                    $query->whereDate('performance_month', $month->toDateString())
                        ->orWhere(function ($fallback) use ($month) {
                            $fallback->whereNull('performance_month')
                                ->whereBetween('transaction_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
                        });
                })->orderBy('transaction_date')->orderBy('id')->get();

            $plan = $this->performance->syncAgreementMonth($locked, $term, $month, $term->created_by);
            $payoutCreated = false;

            if ($existingPayouts->isEmpty() && (bool)($term->auto_payout ?? true) && $plan) {
                // A due historical month is fully accrued before settlement. postDue() is idempotent.
                $this->performance->ensureSchedule($plan);
                $this->performance->rebalanceFuture($plan);
                $this->performance->postDue($plan);
                $plan->refresh()->load('accruals');
                $amount = round((float)$plan->accruals->whereNotNull('posted_at')->sum(fn($row)=>(float)$row->posted_amount), 2);
                if (abs($amount) < 0.005) $amount = round((float)$plan->target_amount, 2);

                if ($amount > 0.004) {
                    $quote = $this->fxForSettlement($locked, $settlementDate);
                    if ($quote !== null) {
                        $payout = $this->accounting->create($locked, [
                            'type'=>'profit',
                            'amount'=>$amount,
                            'currency'=>strtoupper((string)$locked->currency),
                            'fx_rate_to_usd'=>$quote['rate'],
                            'fx_source'=>$quote['source'],
                            'transaction_date'=>$settlementDate->toDateString(),
                            'performance_month'=>$month->toDateString(),
                            'entry_source'=>'automatic_monthly_payout',
                            'reference'=>'Monthly profit payout · '.$month->format('M Y'),
                            'description'=>'Monthly profit paid under the agreed '.number_format((float)$term->monthly_target_rate,2).'% performance agreement.',
                        ], $term->created_by, true);
                        $existingPayouts = collect([$payout]);
                        $payoutCreated = true;
                    }
                }
            }

            $existing = $locked->statements()->whereDate('statement_month',$month->toDateString())->first();
            $statementData = $this->statementData($locked, $month, $existingPayouts);
            $statement = $locked->statements()->updateOrCreate(
                ['statement_month'=>$month->toDateString()],
                $statementData + [
                    'auto_generated'=>true,
                    'published_at'=>$existing?->published_at ?: now(),
                    'notes'=>$existing?->notes ?: 'Automatically reconciled from posted capital activity and monthly profit payout records.',
                ]
            );

            if ($plan && $plan->status === 'active') $plan->update(['status'=>'finalized']);

            $freshAccount = $locked->fresh('user');
            $shouldNotify = $notify && $payoutCreated && abs($settlementDate->diffInDays($asOf, false)) <= 2;
            if ($shouldNotify && $freshAccount->user) {
                PulseAlert::create([
                    'user_id'=>$freshAccount->user_id,
                    'type'=>'private_investor',
                    'title'=>'Monthly profit paid',
                    'message'=>$freshAccount->currency.' '.number_format((float)$statement->profit_paid,2).' for '.$month->format('F Y').' has been recorded as paid. Your monthly statement is available.',
                    'severity'=>'info','is_read'=>false,'action_url'=>route('private.statement',$statement),
                    'data'=>['portfolio_account_id'=>$freshAccount->id,'monthly_statement_id'=>$statement->id,'performance_month'=>$month->format('Y-m')],
                ]);
                $this->mail->investorStatementPublished($freshAccount->user, $freshAccount, $statement);
            }

            return [
                'payout_created'=>$payoutCreated,
                'statement_created'=>! $existing,
                'statement_updated'=>(bool)$existing,
                'skipped'=>!$payoutCreated && !$existing && (float)$statement->profit_paid <= 0,
            ];
        });
    }

    public function statementData(PortfolioAccount $account, Carbon $month, ?Collection $knownPayouts = null): array
    {
        $month = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $currency = strtoupper((string)$account->currency);

        $before = $account->transactions()->where('status','posted')->whereDate('transaction_date','<',$month->toDateString())->get();
        $during = $account->transactions()->where('status','posted')->whereBetween('transaction_date',[$month->toDateString(),$end->toDateString()])->get();
        $payouts = $knownPayouts ?: $account->transactions()->where('status','posted')->where('type','profit')->whereDate('performance_month',$month->toDateString())->get();

        $opening = round((float)$account->opening_value + (float)$before->sum(fn($tx)=>(float)$tx->current_value_effect),2);
        $openingUsdBase = (float)($account->opening_value_usd ?? ($currency==='USD' ? $account->opening_value : 0));
        $openingUsd = round($openingUsdBase + (float)$before->sum(fn($tx)=>(float)($tx->usd_current_value_effect ?? 0)),2);
        $contributions = round((float)$during->where('type','deposit')->sum(fn($tx)=>(float)$tx->amount),2);
        $withdrawals = round((float)$during->where('type','withdrawal')->sum(fn($tx)=>(float)$tx->amount),2);
        $contributionsUsd = round((float)$during->where('type','deposit')->sum(fn($tx)=>(float)($tx->principal_usd_basis ?? $tx->usd_amount ?? 0)),2);
        $withdrawalsUsd = round(abs((float)$during->where('type','withdrawal')->sum(fn($tx)=>(float)($tx->usd_net_contributions_effect ?? 0))),2);

        $profitPaid = round((float)$payouts->sum(fn($tx)=>(float)$tx->amount),2);
        $profitPaidUsd = round((float)$payouts->sum(fn($tx)=>(float)($tx->usd_amount ?? 0)),2);
        $otherPerformance = $during->filter(fn($tx)=>in_array($tx->type,['loss','fee'],true));
        $profitLoss = round($profitPaid + (float)$otherPerformance->sum(fn($tx)=>(float)$tx->profit_effect),2);
        $profitLossUsd = round($profitPaidUsd + (float)$otherPerformance->sum(fn($tx)=>(float)($tx->usd_profit_effect ?? 0)),2);

        // Closing values are ledger-derived. Paid profit has a zero capital effect.
        $closing = max(0, round($opening + (float)$during->sum(fn($tx)=>(float)$tx->current_value_effect),2));
        $closingUsd = max(0, round($openingUsd + (float)$during->sum(fn($tx)=>(float)($tx->usd_current_value_effect ?? 0)),2));
        $paymentDate = $payouts->sortByDesc('transaction_date')->first()?->transaction_date?->toDateString();
        $referenceRate = $payouts->sortByDesc('transaction_date')->first()?->fx_rate_to_usd
            ?? $during->whereNotNull('fx_rate_to_usd')->sortByDesc('transaction_date')->first()?->fx_rate_to_usd
            ?? ($currency==='USD' ? 1 : null);

        return [
            'currency'=>$currency,
            'fx_rate_to_usd'=>$referenceRate,
            'opening_balance'=>$opening,
            'contributions'=>$contributions,
            'withdrawals'=>$withdrawals,
            'profit_loss'=>$profitLoss,
            'profit_paid'=>$profitPaid,
            'closing_balance'=>$closing,
            'opening_balance_usd'=>$openingUsd,
            'contributions_usd'=>$contributionsUsd,
            'withdrawals_usd'=>$withdrawalsUsd,
            'profit_loss_usd'=>$profitLossUsd,
            'profit_paid_usd'=>$profitPaidUsd,
            'closing_balance_usd'=>$closingUsd,
            'payment_date'=>$paymentDate,
        ];
    }

    public function recalculateAccount(PortfolioAccount $account): PortfolioAccount
    {
        return DB::transaction(function () use ($account): PortfolioAccount {
            $locked = PortfolioAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $posted = $locked->transactions()->where('status','posted')->get();
            $currency = strtoupper((string)$locked->currency);
            $openingUsd = (float)($locked->opening_value_usd ?? ($currency==='USD' ? $locked->opening_value : 0));
            $month = now()->startOfMonth()->toDateString();
            $monthlyPayouts = $posted->where('type','profit')->filter(fn($tx)=>$tx->performance_month?->startOfMonth()->toDateString()===$month);
            $latestDate = $posted->max(fn($tx)=>$tx->transaction_date?->toDateString());

            $locked->update([
                'current_value'=>max(0,round((float)$locked->opening_value + (float)$posted->sum(fn($tx)=>(float)$tx->current_value_effect),2)),
                'net_contributions'=>max(0,round((float)$locked->opening_value + (float)$posted->sum(fn($tx)=>(float)$tx->net_contributions_effect),2)),
                'total_profit'=>round((float)$posted->sum(fn($tx)=>(float)$tx->profit_effect),2),
                'monthly_profit'=>round((float)$monthlyPayouts->sum(fn($tx)=>(float)$tx->amount),2),
                'current_value_usd'=>max(0,round($openingUsd + (float)$posted->sum(fn($tx)=>(float)($tx->usd_current_value_effect ?? 0)),2)),
                'net_contributions_usd'=>max(0,round($openingUsd + (float)$posted->sum(fn($tx)=>(float)($tx->usd_net_contributions_effect ?? 0)),2)),
                'total_profit_usd'=>round((float)$posted->sum(fn($tx)=>(float)($tx->usd_profit_effect ?? 0)),2),
                'monthly_profit_usd'=>round((float)$monthlyPayouts->sum(fn($tx)=>(float)($tx->usd_amount ?? 0)),2),
                'realized_fx_gain_loss_usd'=>round((float)$posted->sum(fn($tx)=>(float)($tx->fx_gain_loss_usd ?? 0)),2),
                'valuation_date'=>$latestDate ?: $locked->valuation_date,
            ]);
            return $locked->fresh();
        });
    }

    private function fxForSettlement(PortfolioAccount $account, Carbon $date): ?array
    {
        $currency = strtoupper((string)$account->currency);
        if ($currency === 'USD') return ['rate'=>1.0,'source'=>'native_usd'];

        // A current payout uses the live settlement-day reference when available so
        // Admin USD reporting reflects the real cost of the distribution. Historical
        // reconciliation never invents a rate: it reuses the nearest locked account rate.
        if ($date->copy()->startOfDay()->diffInDays(now()->startOfDay()) <= 1) {
            try {
                $quote = $this->fx->quoteToUsd($currency);
                if ((float)($quote['rate'] ?? 0) > 0) return ['rate'=>(float)$quote['rate'],'source'=>(string)($quote['source'] ?? 'live_fx_reference')];
            } catch (Throwable) {
                // Fall through to an auditable previously locked rate.
            }
        }

        $prior = $account->transactions()->where('status','posted')->whereNotNull('fx_rate_to_usd')
            ->whereDate('transaction_date','<=',$date->toDateString())->latest('transaction_date')->latest('id')->first();
        if ($prior && (float)$prior->fx_rate_to_usd > 0) return ['rate'=>(float)$prior->fx_rate_to_usd,'source'=>'locked_account_reference'];
        return null;
    }
}
