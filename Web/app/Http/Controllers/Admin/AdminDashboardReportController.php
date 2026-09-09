<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePublicSignalUnlock;
use App\Models\PulseSignal;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategy;
use App\Models\PulseStrategyLearningState;
use App\Models\User;
use App\Models\UserServiceAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardReportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        [$from, $to, $timeframe] = $this->range($request);

        $signals = PulseSignal::query()->whereBetween('generated_at', [$from, $to])
            ->when($timeframe, fn ($query) => $query->where('timeframe', $timeframe));
        $validations = PulseSignalValidation::query()->whereBetween('generated_at', [$from, $to])
            ->when($timeframe, fn ($query) => $query->where('timeframe', $timeframe));

        $signalCount = (clone $signals)->count();
        $entries = (clone $validations)->whereNotNull('entry_hit_at')->count();
        $wins = (clone $validations)->where('outcome', 'tp')->count();
        $losses = (clone $validations)->where('outcome', 'sl')->count();
        $ambiguous = (clone $validations)->where('outcome', 'ambiguous')->count();
        $pending = (clone $validations)->whereNull('resolved_at')->count();
        $winRate = ($wins + $losses) > 0 ? round(($wins / ($wins + $losses)) * 100, 2) : null;

        $activePulse = UserServiceAccess::query()->where('service', 'pulse')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count();

        $learning = PulseStrategyLearningState::query()->get()->groupBy('strategy_slug');
        $strategies = PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get()->map(function ($strategy) use ($learning): array {
            $states = $learning->get($strategy->slug, collect());
            return [
                $strategy->name,
                $states->isNotEmpty() ? round((float) $states->avg('reliability_score'), 2) : 50.0,
                (int) $states->sum('sample_size'),
                $states->isNotEmpty() ? round((float) $states->avg('win_rate') * 100, 2) : null,
            ];
        });

        $summary = [
            ['Report period', $from->format('Y-m-d').' to '.$to->format('Y-m-d')],
            ['Timeframe', $timeframe ? strtoupper($timeframe) : 'All'],
            ['Total users', User::query()->count()],
            ['Active users', User::query()->where('status', 'active')->count()],
            ['Active Pulse users', $activePulse],
            ['Signals generated', $signalCount],
            ['Entry hits', $entries],
            ['TP outcomes', $wins],
            ['SL outcomes', $losses],
            ['Ambiguous outcomes', $ambiguous],
            ['Pending validation', $pending],
            ['Decisive win rate', $winRate === null ? 'N/A' : $winRate.'%'],
            ['Approved package USDT', (float) PulseMembershipRequest::query()->where('status', 'approved')->whereBetween('reviewed_at', [$from, $to])->sum('final_amount')],
            ['Approved package payments', PulseMembershipRequest::query()->where('status', 'approved')->whereBetween('reviewed_at', [$from, $to])->count()],
            ['Package payments awaiting review', PulseMembershipRequest::query()->whereIn('status', ['submitted','under_review'])->count()],
            ['Public rewarded signal unlocks', PulsePublicSignalUnlock::query()->where('status', 'granted')->whereBetween('claimed_at', [$from, $to])->count()],
            ['Open support enquiries', ContactMessage::query()->where('status', 'new')->count()],
        ];

        $filename = 'ABS_Pulse_Executive_Report_'.$from->format('Ymd').'_'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($summary, $strategies): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Alpha Block Solutions', 'ABS Pulse Executive Report']);
            fputcsv($out, []);
            fputcsv($out, ['Executive metric', 'Value']);
            foreach ($summary as $row) fputcsv($out, $row);
            fputcsv($out, []);
            fputcsv($out, ['Strategy', 'Learned reliability %', 'Evidence samples', 'Observed win rate %']);
            foreach ($strategies as $row) fputcsv($out, $row);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        try {
            $from = Carbon::parse((string) $request->query('from', today()->subDays(29)->toDateString()))->startOfDay();
            $to = Carbon::parse((string) $request->query('to', today()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = today()->subDays(29)->startOfDay();
            $to = today()->endOfDay();
        }
        if ($from->greaterThan($to) || $from->diffInDays($to) > 365) {
            $from = today()->subDays(29)->startOfDay();
            $to = today()->endOfDay();
        }
        $timeframe = in_array($request->query('timeframe'), ['15m', '4h'], true) ? (string) $request->query('timeframe') : null;
        return [$from, $to, $timeframe];
    }
}
