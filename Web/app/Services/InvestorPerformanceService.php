<?php

namespace App\Services;

use App\Models\PortfolioAccount;
use App\Models\PortfolioDailyAccrual;
use App\Models\PortfolioInvestmentTerm;
use App\Models\PortfolioPerformancePlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestorPerformanceService
{
    public function currentPlan(PortfolioAccount $account): ?PortfolioPerformancePlan
    {
        return PortfolioPerformancePlan::query()
            ->where('portfolio_account_id', $account->id)
            ->whereDate('plan_month', now()->startOfMonth()->toDateString())
            ->where('status', 'active')
            ->first();
    }

    public function investmentTerm(PortfolioAccount $account): ?PortfolioInvestmentTerm
    {
        return PortfolioInvestmentTerm::query()->where('portfolio_account_id', $account->id)->first();
    }

    public function saveInvestmentTerm(PortfolioAccount $account, array $data, ?int $adminId = null): PortfolioInvestmentTerm
    {
        $effective = Carbon::parse($data['effective_from'] ?? now())->startOfDay();
        $rate = round((float) ($data['monthly_target_rate'] ?? 0), 4);

        $term = PortfolioInvestmentTerm::query()->updateOrCreate(
            ['portfolio_account_id' => $account->id],
            [
                'effective_from' => $effective->toDateString(),
                'monthly_target_rate' => $rate,
                'status' => $data['status'] ?? 'active',
                'auto_payout' => array_key_exists('auto_payout', $data) ? (bool)$data['auto_payout'] : true,
                'payout_day' => isset($data['payout_day']) && $data['payout_day'] !== '' ? (int)$data['payout_day'] : null,
                'created_by' => $adminId,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]
        );

        if ($term->status === 'active') {
            $this->syncAgreementPlans($account, now(), $effective, $adminId);
        }

        return $term->fresh();
    }

    /**
     * Build/rebuild calendar-month provisional plans from the agreed monthly rate.
     * The first month is prorated automatically from the investment effective date.
     * Capital changes inside a month are weighted by the number of days that capital
     * was actually active, so mid-month investments/withdrawals remain fair.
     */
    public function rebuildAgreementPlans(PortfolioAccount $account, Carbon $from, ?Carbon $through = null, ?int $adminId = null): Collection
    {
        $term = $this->investmentTerm($account);
        if (! $term || $term->status !== 'active') return collect();

        $fromMonth = $from->copy()->startOfMonth();
        $toMonth = ($through ?: now())->copy()->startOfMonth();
        $planIds = PortfolioPerformancePlan::query()
            ->where('portfolio_account_id', $account->id)
            ->where('calculation_basis', 'weighted_calendar_month')
            ->whereDate('plan_month', '>=', $fromMonth->toDateString())
            ->whereDate('plan_month', '<=', $toMonth->toDateString())
            ->pluck('id');
        if ($planIds->isNotEmpty()) {
            PortfolioDailyAccrual::query()->whereIn('portfolio_performance_plan_id', $planIds)->delete();
        }

        return $this->syncAgreementPlans($account, $through ?: now(), $fromMonth, $adminId);
    }

    public function syncAgreementPlans(PortfolioAccount $account, ?Carbon $through = null, ?Carbon $from = null, ?int $adminId = null): Collection
    {
        $term = $this->investmentTerm($account);
        if (! $term || $term->status !== 'active') return collect();

        $first = ($from ?: $term->effective_from ?: now())->copy()->startOfMonth();
        $last = ($through ?: now())->copy()->startOfMonth();
        if ($first->gt($last)) return collect();

        $plans = collect();
        for ($month = $first->copy(); $month->lte($last); $month->addMonth()) {
            $plan = $this->syncAgreementMonth($account, $term, $month->copy(), $adminId);
            if ($plan) $plans->push($plan);
        }
        return $plans;
    }

    public function syncAgreementMonth(PortfolioAccount $account, PortfolioInvestmentTerm $term, Carbon $month, ?int $adminId = null): ?PortfolioPerformancePlan
    {
        $month = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth()->startOfDay();

        // A published monthly statement is the finalized record. Once a month is finalized,
        // the provisional accrual engine must not rewrite or keep posting that month.
        $hasPublishedStatement = $account->statements()
            ->whereDate('statement_month', $month->toDateString())
            ->whereNotNull('published_at')
            ->exists();
        if ($hasPublishedStatement) {
            $existing = PortfolioPerformancePlan::query()
                ->where('portfolio_account_id', $account->id)
                ->whereDate('plan_month', $month->toDateString())
                ->first();
            if ($existing && $existing->status === 'active') $existing->update(['status' => 'finalized']);
            return $existing?->fresh(['accruals']);
        }
        $effective = $term->effective_from?->copy()->startOfDay() ?? $month->copy();
        $start = $effective->gt($month) ? $effective : $month->copy();
        if ($start->gt($monthEnd)) $start = $monthEnd->copy();

        $dailyCapital = $this->dailyCapitalSeries($account, $month, $monthEnd);
        $active = $dailyCapital->filter(fn ($value, $date) => Carbon::parse($date)->gte($start));
        $capitalDays = (float) $active->sum();
        $daysInMonth = max(1, $month->daysInMonth);
        $equivalentBase = round($capitalDays / $daysInMonth, 2);
        $rate = round((float) $term->monthly_target_rate, 4);
        $target = round($equivalentBase * ($rate / 100), 2);

        $plan = PortfolioPerformancePlan::query()->updateOrCreate(
            ['portfolio_account_id' => $account->id, 'plan_month' => $month->toDateString()],
            [
                'investment_term_id' => $term->id,
                'accrual_start_date' => $start->toDateString(),
                'accrual_end_date' => $monthEnd->toDateString(),
                'base_amount' => $equivalentBase,
                'target_rate' => $rate,
                'target_amount' => $target,
                'calculation_basis' => 'weighted_calendar_month',
                'status' => 'active',
                'created_by' => $adminId ?: $term->created_by,
                'notes' => 'Generated from the agreed Private Investor monthly performance rate.',
            ]
        );

        $this->ensureSchedule($plan);
        $this->rebalanceFuture($plan);
        $this->postDue($plan);

        return $plan->fresh(['accruals']);
    }

    /** Daily invested-capital series using Opening Investment + posted capital movements. */
    public function dailyCapitalSeries(PortfolioAccount $account, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        $monthStart = $monthStart->copy()->startOfDay();
        $monthEnd = $monthEnd->copy()->startOfDay();
        $transactions = $account->transactions()
            ->where('status', 'posted')
            ->whereDate('transaction_date', '<=', $monthEnd->toDateString())
            ->orderBy('transaction_date')->orderBy('id')->get();

        $capital = round((float) $account->opening_value + (float) $transactions
            ->filter(fn ($row) => $row->transaction_date?->lt($monthStart))
            ->sum(fn ($row) => (float) $row->net_contributions_effect), 2);

        $byDate = $transactions
            ->filter(fn ($row) => $row->transaction_date?->between($monthStart, $monthEnd, true))
            ->groupBy(fn ($row) => $row->transaction_date?->toDateString());

        $series = collect();
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            foreach ($byDate->get($day->toDateString(), collect()) as $row) {
                $capital = round(max(0, $capital + (float) $row->net_contributions_effect), 2);
            }
            $series->put($day->toDateString(), $capital);
        }
        return $series;
    }

    /** Manual month override remains available for exceptional cases. */
    public function savePlan(PortfolioAccount $account, array $data, ?int $adminId = null): PortfolioPerformancePlan
    {
        $month = Carbon::parse($data['plan_month'] ?? now())->startOfMonth();
        $base = round(max(0, (float) ($data['base_amount'] ?? $account->current_value)), 2);
        $rate = round((float) ($data['target_rate'] ?? 0), 4);
        $target = round($base * ($rate / 100), 2);

        $plan = PortfolioPerformancePlan::query()->updateOrCreate(
            ['portfolio_account_id' => $account->id, 'plan_month' => $month->toDateString()],
            [
                'base_amount' => $base,
                'target_rate' => $rate,
                'target_amount' => $target,
                'accrual_start_date' => $month->toDateString(),
                'accrual_end_date' => $month->copy()->endOfMonth()->toDateString(),
                'calculation_basis' => 'manual_month',
                'status' => $data['status'] ?? 'active',
                'created_by' => $adminId,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]
        );

        $this->ensureSchedule($plan);
        $this->rebalanceFuture($plan);
        return $plan->fresh(['accruals']);
    }

    public function ensureSchedule(PortfolioPerformancePlan $plan): void
    {
        $month = Carbon::parse($plan->plan_month)->startOfMonth();
        $start = ($plan->accrual_start_date ?: $month)->copy()->startOfDay();
        $end = ($plan->accrual_end_date ?: $month->copy()->endOfMonth())->copy()->startOfDay();
        if ($start->lt($month)) $start = $month->copy();
        if ($end->gt($month->copy()->endOfMonth())) $end = $month->copy()->endOfMonth()->startOfDay();
        if ($start->gt($end)) return;

        DB::transaction(function () use ($plan, $start, $end, $month): void {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $day = $date->day;
                $hash = hash('sha256', $plan->portfolio_account_id.'|'.$month->format('Y-m').'|'.$day);
                // Keep posting away from the exact day boundary: between 07:00 and 22:59 local server time.
                $minuteOfWindow = hexdec(substr($hash, 0, 6)) % (16 * 60);
                $scheduled = $date->copy()->startOfDay()->addHours(7)->addMinutes($minuteOfWindow);
                PortfolioDailyAccrual::query()->firstOrCreate(
                    ['portfolio_performance_plan_id' => $plan->id, 'accrual_date' => $date->toDateString()],
                    ['scheduled_at' => $scheduled, 'planned_amount' => 0, 'manual_adjustment' => 0]
                );
            }

            // Remove only unposted rows that sit outside the plan's valid accrual window.
            $plan->accruals()->whereNull('posted_at')->where(function ($q) use ($start, $end) {
                $q->whereDate('accrual_date', '<', $start->toDateString())
                    ->orWhereDate('accrual_date', '>', $end->toDateString());
            })->delete();
        });
    }

    public function postDue(?PortfolioPerformancePlan $plan = null): int
    {
        $query = PortfolioDailyAccrual::query()
            ->whereNull('posted_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
        if ($plan) $query->where('portfolio_performance_plan_id', $plan->id);

        $posted = 0;
        foreach ($query->with('plan')->orderBy('scheduled_at')->get() as $row) {
            if (! $row->plan || $row->plan->status !== 'active') continue;
            $amount = round((float) $row->planned_amount + (float) $row->manual_adjustment, 2);
            $row->update(['posted_amount' => $amount, 'posted_at' => $row->scheduled_at ?: now()]);
            $posted++;
        }
        return $posted;
    }

    public function adjustDay(PortfolioDailyAccrual $row, float $adjustment, ?int $adminId = null, ?string $note = null): PortfolioDailyAccrual
    {
        return DB::transaction(function () use ($row, $adjustment, $adminId, $note): PortfolioDailyAccrual {
            $plan = $row->plan()->with('accruals')->firstOrFail();
            $futureCount = $plan->accruals->whereNull('posted_at')->where('id', '!=', $row->id)->count();
            if ($row->posted_at && $futureCount === 0) {
                $otherPosted = (float) $plan->accruals->whereNotNull('posted_at')->where('id', '!=', $row->id)->sum(fn ($item) => (float) $item->posted_amount);
                $proposed = round($otherPosted + (float) $row->planned_amount + $adjustment, 2);
                if (abs($proposed - (float) $plan->target_amount) > 0.009) {
                    throw ValidationException::withMessages(['manual_adjustment' => 'This month is fully posted. Update the monthly agreement or rebalance an earlier day before changing the final total.']);
                }
            }
            $row->update([
                'manual_adjustment' => round($adjustment, 2),
                'adjusted_by' => $adminId,
                'admin_note' => trim((string) $note) ?: null,
                'posted_amount' => $row->posted_at ? round((float) $row->planned_amount + $adjustment, 2) : null,
            ]);
            $this->rebalanceFuture($plan);
            return $row->fresh();
        });
    }

    public function rebalanceFuture(PortfolioPerformancePlan $plan): void
    {
        $this->ensureSchedule($plan);
        $rows = $plan->accruals()->orderBy('accrual_date')->get();
        if ($rows->isEmpty()) return;

        // V15.7.4 live-data repair: an older build could leave already-due rows posted
        // at 0.00 while the month still carried a positive target. When every posted
        // row is zero and no Admin adjustment exists, safely rebuild the automatic
        // distribution across the whole month and update those zero legacy postings.
        $postedRows = $rows->whereNotNull('posted_at')->values();
        $legacyZeroPosted = $postedRows->isNotEmpty()
            && (float)$plan->target_amount > 0.004
            && abs((float)$postedRows->sum(fn ($r) => (float)$r->posted_amount)) < 0.005
            && abs((float)$postedRows->sum(fn ($r) => (float)$r->manual_adjustment)) < 0.005;
        if ($legacyZeroPosted) {
            $weights = $rows->map(fn ($row) => $this->weight($plan, $row));
            $weightTotal = max(0.0001, (float)$weights->sum());
            $assigned = 0.0;
            $last = $rows->count() - 1;
            foreach ($rows->values() as $i => $row) {
                $planned = $i === $last
                    ? round((float)$plan->target_amount - $assigned, 2)
                    : round((float)$plan->target_amount * ((float)$weights[$i] / $weightTotal), 2);
                $assigned = round($assigned + $planned, 2);
                $update = ['planned_amount'=>$planned];
                if ($row->posted_at) $update['posted_amount'] = round($planned + (float)$row->manual_adjustment, 2);
                $row->update($update);
            }
            $rows = $plan->accruals()->orderBy('accrual_date')->get();
        }

        $locked = (float) $rows->whereNotNull('posted_at')->sum(fn ($r) => (float) $r->posted_amount);
        $future = $rows->whereNull('posted_at')->values();
        if ($future->isEmpty()) return;

        $manual = (float) $future->sum(fn ($r) => (float) $r->manual_adjustment);
        $distributable = round((float) $plan->target_amount - $locked - $manual, 2);
        $weights = $future->map(fn ($row) => $this->weight($plan, $row));
        $weightTotal = max(0.0001, (float) $weights->sum());
        $assigned = 0.0;
        $last = $future->count() - 1;

        foreach ($future as $i => $row) {
            $planned = $i === $last
                ? round($distributable - $assigned, 2)
                : round($distributable * ((float) $weights[$i] / $weightTotal), 2);
            $assigned = round($assigned + $planned, 2);
            $row->update(['planned_amount' => $planned]);
        }
    }

    public function snapshot(PortfolioAccount $account): array
    {
        $term = $this->investmentTerm($account);
        if ($term && $term->status === 'active') $this->syncAgreementPlans($account, now(), now()->startOfMonth(), $term->created_by);

        $plan = $this->currentPlan($account);
        if (! $plan) {
            return [
                'term' => $term, 'plan' => null, 'daily' => collect(), 'mtd' => 0.0, 'target' => 0.0, 'progress' => 0.0,
                'indicative_value' => (float) $account->current_value, 'days_posted' => 0, 'days_total' => now()->daysInMonth,
                'chart' => ['labels'=>[], 'daily'=>[], 'cumulative'=>[], 'points'=>''],
            ];
        }

        $this->ensureSchedule($plan);
        $this->rebalanceFuture($plan);
        $this->postDue($plan);
        $daily = $plan->accruals()->orderBy('accrual_date')->get();
        $mtd = round((float) $daily->whereNotNull('posted_at')->sum(fn ($r) => (float) $r->posted_amount), 2);
        $target = (float) $plan->target_amount;
        $progress = abs($target) > 0.0001 ? min(100, max(0, ($mtd / $target) * 100)) : 0.0;
        $indicative = round((float) $account->current_value + $mtd, 2);

        return [
            'term' => $term, 'plan' => $plan->fresh(), 'daily' => $daily, 'mtd' => $mtd, 'target' => $target,
            'progress' => $progress, 'indicative_value' => $indicative,
            'days_posted' => $daily->whereNotNull('posted_at')->count(), 'days_total' => $daily->count(),
            'chart' => $this->chart($daily),
        ];
    }

    public function agreementSummary(PortfolioAccount $account): array
    {
        $term = $this->investmentTerm($account);
        if (! $term) {
            return [
                'term' => null, 'plans' => collect(), 'target_total' => 0.0, 'posted_total' => 0.0,
                'months' => 0, 'current_target' => 0.0, 'current_posted' => 0.0,
                'indicative_value' => (float) $account->current_value, 'full_month_target' => 0.0,
            ];
        }
        if ($term->status === 'active') $this->syncAgreementPlans($account, now(), $term->effective_from, $term->created_by);
        $plans = PortfolioPerformancePlan::query()
            ->where('portfolio_account_id', $account->id)
            ->where('investment_term_id', $term->id)
            ->with('accruals')
            ->orderBy('plan_month')->get();
        $targetTotal = round((float) $plans->sum(fn ($p) => (float) $p->target_amount), 2);
        $postedTotal = round((float) $plans->sum(fn ($p) => (float) $p->accruals->whereNotNull('posted_at')->sum(fn ($r) => (float) $r->posted_amount)), 2);
        $current = $plans->first(fn ($p) => $p->plan_month?->format('Y-m') === now()->format('Y-m'));
        $currentPosted = $current ? round((float) $current->accruals->whereNotNull('posted_at')->sum(fn ($r) => (float) $r->posted_amount), 2) : 0.0;

        // Provisional accruals stop contributing to indicative value once that month has
        // an official published statement. This prevents historical provisional progress
        // from being counted twice after the statement has already updated current_value.
        $publishedMonths = $account->statements()
            ->whereNotNull('published_at')
            ->pluck('statement_month')
            ->map(fn ($value) => Carbon::parse($value)->format('Y-m'))
            ->all();
        $unfinalizedPosted = round((float) $plans
            ->reject(fn ($p) => in_array($p->plan_month?->format('Y-m'), $publishedMonths, true))
            ->sum(fn ($p) => (float) $p->accruals->whereNotNull('posted_at')->sum(fn ($r) => (float) $r->posted_amount)), 2);

        $fullMonthTarget = round((float) $account->net_contributions * ((float) $term->monthly_target_rate / 100), 2);
        return [
            'term' => $term, 'plans' => $plans, 'target_total' => $targetTotal, 'posted_total' => $postedTotal,
            'unfinalized_posted_total' => $unfinalizedPosted,
            'months' => $plans->count(), 'current_target' => (float) ($current?->target_amount ?? 0),
            'current_posted' => $currentPosted, 'indicative_value' => round((float)$account->current_value + $unfinalizedPosted, 2),
            'full_month_target' => $fullMonthTarget,
        ];
    }

    public function history(PortfolioAccount $account, int $months = 12): Collection
    {
        return PortfolioPerformancePlan::query()
            ->where('portfolio_account_id', $account->id)
            ->with('accruals')
            ->orderByDesc('plan_month')
            ->limit($months)
            ->get()
            ->map(function (PortfolioPerformancePlan $plan) {
                $posted = round((float) $plan->accruals->whereNotNull('posted_at')->sum(fn ($r) => (float) $r->posted_amount), 2);
                return [
                    'plan' => $plan,
                    'posted' => $posted,
                    'target' => (float) $plan->target_amount,
                    'progress' => abs((float) $plan->target_amount) > 0.0001 ? ($posted / (float) $plan->target_amount) * 100 : 0,
                ];
            });
    }

    private function weight(PortfolioPerformancePlan $plan, PortfolioDailyAccrual $row): float
    {
        $hash = hash('sha256', 'weight|'.$plan->portfolio_account_id.'|'.$plan->plan_month->format('Y-m').'|'.$row->accrual_date->format('Y-m-d'));
        $raw = hexdec(substr($hash, 0, 6)) / 0xFFFFFF;
        return 0.55 + ($raw * 0.90);
    }

    private function chart(Collection $rows): array
    {
        $labels = [];
        $daily = [];
        $cumulative = [];
        $running = 0.0;
        foreach ($rows as $row) {
            $amount = $row->posted_at ? (float) $row->posted_amount : 0.0;
            $running = round($running + $amount, 2);
            $labels[] = $row->accrual_date->format('d');
            $daily[] = $amount;
            $cumulative[] = $running;
        }
        if ($cumulative === []) return ['labels'=>[], 'daily'=>[], 'cumulative'=>[], 'points'=>''];
        $min = min(0.0, min($cumulative));
        $max = max(1.0, max($cumulative));
        $range = max(1.0, $max - $min);
        $count = max(1, count($cumulative) - 1);
        $points = collect($cumulative)->map(function ($value, $i) use ($min, $range, $count) {
            $x = 4 + ($i / $count) * 92;
            $y = 82 - (((float) $value - $min) / $range) * 64;
            return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        })->implode(' ');
        return compact('labels', 'daily', 'cumulative', 'points');
    }
}
