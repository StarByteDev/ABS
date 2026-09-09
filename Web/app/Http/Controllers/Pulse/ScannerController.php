<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Services\PulsePageDataService;
use App\Services\PulseScannerService;
use App\Services\PulseUsageService;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    public function index(Request $request, PulsePageDataService $pages)
    {
        return view('pulse.scanner', [
            'page' => $pages->scanner($request->user(), $this->filters($request)),
        ]);
    }

    public function run(Request $request, PulseScannerService $scanner, PulseUsageService $usage)
    {
        try {
            $run = $scanner->run($request->user(), null, 'all');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'usage' => $usage->today($request->user())], 422);
            }
            return back()->withErrors(['scanner' => $e->getMessage()]);
        }

        $winner = $run->bestSignal;
        $message = $winner
            ? 'Best Signal unlocked: '.strtoupper($winner->symbol).' '.strtoupper($winner->direction).'. Included with your active Pulse package.'
            : 'No qualifying Best Signal was found in this scan.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'run' => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'markets_scanned' => (int) $run->markets_scanned,
                    'signals_generated' => (int) $run->signals_generated,
                    'per_signal_charge' => 0,
                    'started_at' => $run->started_at,
                    'completed_at' => $run->completed_at,
                    'best_signal' => $winner ? [
                        'id' => $winner->id,
                        'symbol' => $winner->symbol,
                        'timeframe' => $winner->timeframe,
                        'direction' => $winner->direction,
                        'entry_price' => $winner->entry_price,
                        'stop_loss' => $winner->stop_loss,
                        'take_profit' => $winner->take_profit,
                        'technical_score' => $winner->technical_score,
                        'reliability_score' => $winner->reliability_score,
                        'confidence_score' => $winner->confidence_score,
                        'confidence_label' => $winner->confidence_label,
                    ] : null,
                ],
                'usage' => $usage->today($request->user()),
            ], 201);
        }
        return redirect()->route('pulse.scanner')->with('success', $message);
    }

    public function refresh(Request $request, PulsePageDataService $pages, PulseUsageService $usage)
    {
        $page = $pages->scanner($request->user(), $this->filters($request));

        return response()->json([
            'metrics_html' => view('pulse.partials.scanner-metrics', compact('page'))->render(),
            'results_html' => view('pulse.partials.scanner-results', compact('page'))->render(),
            'bottom_html' => view('pulse.partials.scanner-bottom', [
                'page' => $page,
                'filters' => $page['filters'] ?? [],
            ])->render(),
            'last_scan' => $page['latest_run']?->completed_at?->diffForHumans() ?? 'not yet',
            'usage' => $usage->today($request->user()),
        ]);
    }

    private function filters(Request $request): array
    {
        return [];
    }
}
