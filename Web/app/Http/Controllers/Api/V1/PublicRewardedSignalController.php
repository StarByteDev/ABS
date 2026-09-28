<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PulsePublicRewardedSignalService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicRewardedSignalController extends Controller
{
    public function status(Request $request, PulsePublicRewardedSignalService $rewards)
    {
        $visitor = $this->visitorToken($request);
        $status = $rewards->status($visitor);
        return response()->json([
            'data' => [
                'visitor_token' => $visitor,
                'available' => (bool) ($status['available'] ?? false),
                'enabled' => (bool) ($status['enabled'] ?? false),
                'cooldown_seconds' => (int) ($status['cooldown_remaining'] ?? 0),
                'next_available_at' => $status['next_available_at'] ?? null,
                'visibility_mode' => 'until_refresh_or_navigation',
                'free_signal_page_url' => route('pulse.free-signal'),
            ],
            'meta' => [
                'registration_required' => false,
                'rewarded_access' => true,
                'visitor_token_persistence' => 'Store securely on device and reuse for cooldown continuity.',
            ],
        ]);
    }

    public function session(Request $request, PulsePublicRewardedSignalService $rewards)
    {
        $visitor = $this->visitorToken($request);
        try {
            $session = $rewards->createAdSession($visitor);
            return response()->json([
                'data' => [
                    'visitor_token' => $visitor,
                    ...$session,
                    'integration_mode' => 'google_ad_manager_rewarded_web',
                    'free_signal_page_url' => route('pulse.free-signal'),
                ],
            ]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            return response()->json([
                'message' => $message,
                'code' => str_contains($message, 'opportunity') ? 'market_wait' : 'reward_session_unavailable',
                'visitor_token' => $visitor,
            ], 422);
        }
    }

    public function claim(Request $request, PulsePublicRewardedSignalService $rewards)
    {
        $data = $request->validate([
            'claim_token' => ['required','string','max:2000'],
            'visitor_token' => ['nullable','string','max:128'],
            'reward_type' => ['nullable','string','max:100'],
            'reward_amount' => ['nullable','numeric','min:0','max:1000000'],
        ]);
        $visitor = $this->visitorToken($request);
        try {
            $unlock = $rewards->claim($visitor, $data['claim_token'], [
                'type' => $data['reward_type'] ?? 'reward',
                'amount' => $data['reward_amount'] ?? 0,
                'client' => 'mobile_api',
            ]);
            $snapshot = (array) $unlock->signal_snapshot;
            $qualified = (bool) data_get($snapshot, 'is_qualified_signal', true);
            $presentation = (string) data_get($snapshot, 'presentation', 'qualified_signal');
            return response()->json(['data' => [
                'visitor_token' => $visitor,
                'presentation_schema_version' => 3,
                'presentation' => $presentation,
                'is_qualified_signal' => $qualified,
                'fallback_mode' => $qualified ? null : ($presentation === 'btc_outlook' ? 'btc_4h_outlook' : $presentation),
                'display' => [
                    'eyebrow' => $presentation === 'btc_outlook' ? 'ABS PULSE · BTC 4-HOUR OUTLOOK' : 'ABS PULSE · FREE SIGNAL',
                    'title' => $presentation === 'btc_outlook' ? 'No qualified signal right now' : 'Qualified Pulse setup',
                    'subtitle' => $presentation === 'btc_outlook'
                        ? 'Monitor BTC over the next four hours while ABS waits for a stronger qualified setup.'
                        : 'Review the entry, target, stop and strategy evidence for the unlocked setup.',
                ],
                'signal' => $snapshot,
                'visibility_mode' => 'until_refresh_or_navigation',
                'next_available_at' => $unlock->next_available_at?->toIso8601String(),
                'cooldown_seconds' => max(0, now()->diffInSeconds($unlock->next_available_at, false)),
            ]]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'visitor_token' => $visitor], 422);
        }
    }

    private function visitorToken(Request $request): string
    {
        $token = trim((string) ($request->input('visitor_token') ?: $request->header('X-ABS-Visitor-Token', '')));
        if (! preg_match('/^[A-Za-z0-9_-]{32,128}$/', $token)) $token = Str::random(64);
        return $token;
    }
}
