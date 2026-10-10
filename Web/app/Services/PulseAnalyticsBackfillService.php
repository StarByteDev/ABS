<?php

namespace App\Services;

use App\Models\PulseSignalValidation;
use App\Models\PulseSystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PulseAnalyticsBackfillService
{
    public const SETTING_KEY = 'pulse_strategy_analytics_backfill_job';

    public function __construct(private readonly PulseLearningService $learning)
    {
    }

    public function state(): array
    {
        if (! Schema::hasTable('pulse_system_settings')) {
            $state = $this->emptyState();
            $state['message'] = 'Pulse system settings are not available yet.';
            return $state;
        }

        $state = PulseSystemSetting::value(self::SETTING_KEY, []);
        if (! is_array($state) || $state === []) {
            return $this->emptyState();
        }

        return array_merge($this->emptyState(), $state, [
            'progress_pct' => $this->progressPercent($state),
        ]);
    }

    public function queue(int $days, ?int $requestedBy = null): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $current = $this->state();
        if (in_array((string) ($current['status'] ?? ''), ['queued', 'running'], true)) {
            throw new \RuntimeException('A strategy analytics backfill is already queued or running.');
        }

        if (! Schema::hasTable('pulse_signal_validations') || ! Schema::hasTable('pulse_system_settings')) {
            throw new \RuntimeException('Pulse analytics tables are not available yet.');
        }

        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();
        $total = PulseSignalValidation::query()
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->selectRaw('DATE(resolved_at) as resolved_date')
            ->distinct()
            ->pluck('resolved_date')
            ->count();

        $state = [
            'status' => $total > 0 ? 'queued' : 'completed',
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_dates' => $total,
            'processed_dates' => 0,
            'last_processed_date' => null,
            'current_date' => null,
            'requested_by' => $requestedBy,
            'requested_at' => now()->toIso8601String(),
            'started_at' => null,
            'completed_at' => $total > 0 ? null : now()->toIso8601String(),
            'last_error' => null,
            'message' => $total > 0
                ? 'Queued for safe background processing by the existing ABS scheduler.'
                : 'No retained resolved validation dates were found in the selected window.',
        ];

        $this->writeState($state);
        return $this->state();
    }

    public function cancel(): array
    {
        $state = $this->state();
        if (! in_array((string) $state['status'], ['queued', 'running'], true)) {
            return $state;
        }

        $state['status'] = 'cancelled';
        $state['current_date'] = null;
        $state['completed_at'] = now()->toIso8601String();
        $state['message'] = 'Backfill cancelled. Existing analytics already rebuilt were kept.';
        $this->writeState($state);
        return $this->state();
    }

    /**
     * Process a small bounded slice so HostGator/shared hosting never needs a manual CLI run.
     * This method is called by the already-scheduled abs:pulse-maintenance command.
     */
    public function processPendingChunk(int $maxDates = 3, int $maxSeconds = 20): array
    {
        $state = $this->state();
        if (! in_array((string) $state['status'], ['queued', 'running'], true)) {
            return ['worked' => false, 'state' => $state];
        }

        $lock = Cache::lock('abs:pulse-analytics-backfill', max(30, $maxSeconds + 10));
        if (! $lock->get()) {
            return ['worked' => false, 'state' => $state, 'message' => 'Backfill is already being processed by another scheduler cycle.'];
        }

        $started = microtime(true);
        $worked = 0;
        try {
            $state = $this->state();
            if (! in_array((string) $state['status'], ['queued', 'running'], true)) {
                return ['worked' => false, 'state' => $state];
            }

            $state['status'] = 'running';
            $state['started_at'] ??= now()->toIso8601String();
            $state['last_error'] = null;
            $state['message'] = 'Rebuilding retained strategy evidence in small shared-hosting-safe batches.';
            $this->writeState($state);

            $from = Carbon::parse((string) $state['from'])->startOfDay();
            $to = Carbon::parse((string) $state['to'])->endOfDay();

            $dateQuery = PulseSignalValidation::query()
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$from, $to]);

            if (! empty($state['last_processed_date'])) {
                $dateQuery->whereDate('resolved_at', '>', (string) $state['last_processed_date']);
            }

            $dates = $dateQuery
                ->selectRaw('DATE(resolved_at) as resolved_date')
                ->distinct()
                ->orderBy('resolved_date')
                ->limit(max(1, min(5, $maxDates)))
                ->pluck('resolved_date');

            foreach ($dates as $date) {
                if ((microtime(true) - $started) >= $maxSeconds) break;

                $state = $this->state();
                if ((string) $state['status'] === 'cancelled') break;

                $state['current_date'] = (string) $date;
                $state['message'] = 'Rebuilding analytics for '.$date.'…';
                $this->writeState($state);

                // Do not refresh the entire optimizer after every historical date.
                // Refresh once after the batch window is complete.
                $this->learning->rebuildDate((string) $date, false);

                $worked++;
                $state = $this->state();
                $state['processed_dates'] = min((int) $state['total_dates'], (int) $state['processed_dates'] + 1);
                $state['last_processed_date'] = (string) $date;
                $state['current_date'] = null;
                $state['message'] = 'Processed '.$state['processed_dates'].' of '.$state['total_dates'].' retained date(s).';
                $this->writeState($state);
            }

            $state = $this->state();
            if ((string) $state['status'] === 'cancelled') {
                return ['worked' => $worked > 0, 'processed' => $worked, 'state' => $state];
            }

            $remaining = PulseSignalValidation::query()
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$from, $to])
                ->when(! empty($state['last_processed_date']), fn ($q) => $q->whereDate('resolved_at', '>', (string) $state['last_processed_date']))
                ->exists();

            if (! $remaining) {
                $state['current_date'] = null;
                $state['message'] = 'Finalizing learning state and adaptive strategy evidence…';
                $this->writeState($state);

                $this->learning->refreshLearningStates();

                $state = $this->state();
                $state['status'] = 'completed';
                $state['processed_dates'] = (int) $state['total_dates'];
                $state['current_date'] = null;
                $state['completed_at'] = now()->toIso8601String();
                $state['message'] = 'Analytics backfill completed. Strategy learning and adaptive R:R evidence are refreshed.';
                $this->writeState($state);
            }

            return ['worked' => $worked > 0, 'processed' => $worked, 'state' => $this->state()];
        } catch (Throwable $e) {
            $state = $this->state();
            $state['status'] = 'failed';
            $state['current_date'] = null;
            $state['completed_at'] = now()->toIso8601String();
            $state['last_error'] = mb_substr($e->getMessage(), 0, 1000);
            $state['message'] = 'Backfill stopped safely. No customer data was deleted or reset.';
            $this->writeState($state);
            return ['worked' => $worked > 0, 'processed' => $worked, 'state' => $this->state(), 'error' => $e->getMessage()];
        } finally {
            optional($lock)->release();
        }
    }

    private function writeState(array $state): void
    {
        DB::table('pulse_system_settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode($state, JSON_UNESCAPED_SLASHES),
                'type' => 'json',
                'group' => 'maintenance',
                'description' => 'Shared-hosting-safe Pulse strategy analytics backfill progress.',
            ]
        );
    }

    private function progressPercent(array $state): float
    {
        $total = max(0, (int) ($state['total_dates'] ?? 0));
        $processed = max(0, (int) ($state['processed_dates'] ?? 0));
        if ((string) ($state['status'] ?? '') === 'completed') return 100.0;
        if ($total < 1) return 0.0;
        return round(min(100, ($processed / $total) * 100), 1);
    }

    private function emptyState(): array
    {
        return [
            'status' => 'idle',
            'days' => 30,
            'from' => null,
            'to' => null,
            'total_dates' => 0,
            'processed_dates' => 0,
            'last_processed_date' => null,
            'current_date' => null,
            'requested_by' => null,
            'requested_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'last_error' => null,
            'message' => 'No analytics backfill is currently queued.',
            'progress_pct' => 0.0,
        ];
    }
}
