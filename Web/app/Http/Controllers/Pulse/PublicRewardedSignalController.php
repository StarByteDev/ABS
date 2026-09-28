<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Services\AdCmsService;
use App\Services\PulseMembershipService;
use App\Services\PulsePublicRewardedSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class PublicRewardedSignalController extends Controller
{
    public function index(Request $request, PulsePublicRewardedSignalService $rewards, PulseMembershipService $membership, AdCmsService $ads)
    {
        $visitor = $rewards->visitor($request);
        $response = response()->view('pulse.public-signal', [
            'status' => $rewards->status($visitor['raw']),
            'plans' => $membership->orderedPublicPlans(),
            'adCms' => [
                'head' => $ads->headCode(),
                'top' => $ads->snippet('free_signal_top'),
                'inline' => $ads->snippet('free_signal_inline'),
                'footer' => $ads->snippet('free_signal_footer'),
            ],
        ]);

        return $visitor['is_new'] ? $response->withCookie($this->visitorCookie($visitor['raw'])) : $response;
    }

    public function session(Request $request, PulsePublicRewardedSignalService $rewards): JsonResponse
    {
        $visitor = $rewards->visitor($request);
        try {
            $payload = $rewards->createAdSession($visitor['raw']);
            $response = response()->json(['ok' => true, ...$payload]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $code = str_contains($message, 'No qualified Pulse signal is available') || str_contains($message, 'market outlook cannot be calculated') ? 'market_wait' : 'reward_session_unavailable';
            $response = response()->json(['ok' => false, 'code' => $code, 'message' => $message], 422);
        }

        return $visitor['is_new'] ? $response->withCookie($this->visitorCookie($visitor['raw'])) : $response;
    }

    public function claim(Request $request, PulsePublicRewardedSignalService $rewards): JsonResponse
    {
        $data = $request->validate([
            'claim_token' => ['required', 'string', 'max:2000'],
            'reward_type' => ['nullable', 'string', 'max:100'],
            'reward_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);
        $visitor = $rewards->visitor($request);

        try {
            $unlock = $rewards->claim($visitor['raw'], $data['claim_token'], [
                'type' => $data['reward_type'] ?? 'reward',
                'amount' => $data['reward_amount'] ?? 0,
            ]);
            $response = response()->json([
                'ok' => true,
                'signal' => $unlock->signal_snapshot,
                'visibility_mode' => 'until_refresh',
                'next_available_at' => $unlock->next_available_at?->toIso8601String(),
                'cooldown_seconds' => max(0, now()->diffInSeconds($unlock->next_available_at, false)),
            ]);
        } catch (\Throwable $e) {
            $response = response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return $visitor['is_new'] ? $response->withCookie($this->visitorCookie($visitor['raw'])) : $response;
    }

    public function status(Request $request, PulsePublicRewardedSignalService $rewards): JsonResponse
    {
        $visitor = $rewards->visitor($request);
        $response = response()->json(['ok' => true, ...$rewards->status($visitor['raw'])]);
        return $visitor['is_new'] ? $response->withCookie($this->visitorCookie($visitor['raw'])) : $response;
    }

    private function visitorCookie(string $raw): Cookie
    {
        return cookie(PulsePublicRewardedSignalService::VISITOR_COOKIE, $raw, 60 * 24 * 365, '/', null, request()->isSecure(), true, false, 'Lax');
    }
}
