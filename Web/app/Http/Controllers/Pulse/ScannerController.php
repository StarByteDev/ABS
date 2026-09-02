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
        $data = $request->validate([
            'symbols' => ['nullable', 'array', 'max:'.max(1, (int) config('pulse.scanner.max_pairs_per_run', 1000))],
            'symbols.*' => ['string', 'max:30'],
            'timeframe' => ['required', 'in:15m,4h,all'],
        ]);

        try {
            $run = $scanner->run($request->user(), array_key_exists('symbols', $data) ? $data['symbols'] : null, $data['timeframe']);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'usage' => $usage->today($request->user()),
                ], 422);
            }

            return back()->withErrors(['scanner' => $e->getMessage()]);
        }

        $message = "Market scan completed: {$run->pairs_scanned} selected markets were reviewed.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'run' => $run,
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
        return $request->only([
            'quote', 'direction', 'strategy', 'min_score', 'timeframe', 'liquidity', 'symbol',
        ]);
    }
}
