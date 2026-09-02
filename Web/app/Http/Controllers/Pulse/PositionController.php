<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseTrade;
use App\Models\PulseUserSetting;
use App\Services\PulseTradeService;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __invoke(Request $request, PulseTradeService $trades)
    {
        $user = $request->user();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], ['environment' => 'testnet']);
        $environment = $request->string('environment')->toString() ?: $settings->environment;
        $snapshot = null;
        $error = null;

        try {
            $snapshot = $trades->exchangeSnapshot($user, $environment);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return view('pulse.positions', [
            'snapshot' => $snapshot,
            'error' => $error,
            'environment' => $environment,
            'localTrades' => PulseTrade::query()
                ->where('user_id', $user->id)
                ->where('environment', $environment)
                ->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])
                ->latest()
                ->get(),
        ]);
    }
}
