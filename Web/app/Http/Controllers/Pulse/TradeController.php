<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseTrade;
use App\Services\PulseTradeService;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $base = PulseTrade::query()->where('user_id', $request->user()->id);
        $query = (clone $base)->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('environment')) $query->where('environment', $request->string('environment'));
        if ($request->filled('symbol')) $query->where('symbol', strtoupper((string) $request->string('symbol')));
        if (isset($data['from'])) $query->where('created_at', '>=', \Illuminate\Support\Carbon::parse($data['from'])->startOfDay());
        if (isset($data['to'])) $query->where('created_at', '<=', \Illuminate\Support\Carbon::parse($data['to'])->endOfDay());

        return view('pulse.trades.index', [
            'trades' => $query->paginate(20)->withQueryString(),
            'totalCount' => (clone $base)->count(),
            'openCount' => (clone $base)->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])->count(),
            'closedCount' => (clone $base)->where('status', 'closed')->count(),
            'protectionIssues' => (clone $base)->whereIn('status', ['open', 'protection_failed'])->whereIn('protection_status', ['failed', 'review_required'])->count(),
        ]);
    }

    public function show(Request $request, PulseTrade $trade)
    {
        abort_unless($trade->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        return view('pulse.trades.show', compact('trade'));
    }

    public function close(Request $request, PulseTrade $trade, PulseTradeService $trades)
    {
        $updated = $trades->close($request->user(), $trade);
        return redirect()->route('pulse.trades.show', $updated)
            ->with('success', 'The cancellation or close request was submitted. Use Sync Orders until the final exchange status is confirmed.');
    }

    public function sync(Request $request, PulseTradeService $trades)
    {
        $result = $trades->sync($request->user());
        $message = "Synchronized {$result['synced']} records; {$result['opened']} opened, {$result['closed']} closed and {$result['protected']} protected.";
        if ($result['errors'] !== []) $message .= ' Some exchange records require review.';
        return back()->with($result['errors'] === [] ? 'success' : 'warning', $message);
    }

    public function emergencyStop(Request $request, PulseTradeService $trades)
    {
        $result = $trades->emergencyStop($request->user());
        $message = "Emergency stop enabled. {$result['cancelled']} pending orders cancelled and {$result['closed']} positions sent for close.";
        if ($result['errors'] !== []) $message .= ' Review Binance immediately because some requests failed.';
        return back()->with($result['errors'] === [] ? 'success' : 'warning', $message);
    }
}
