<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Services\PulsePointService;
use App\Services\PulseRewardedAdService;
use Illuminate\Http\Request;
use RuntimeException;

class RewardedAdController extends Controller
{
    public function session(Request $request, PulseRewardedAdService $ads)
    {
        try { $data = $ads->createWebSession($request->user()); }
        catch (RuntimeException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        return response()->json(['message' => 'Rewarded ad session ready.', 'data' => $data]);
    }

    public function claim(Request $request, PulseRewardedAdService $ads, PulsePointService $points)
    {
        $data = $request->validate([
            'claim_token' => ['required', 'string', 'max:3000'],
            'provider_payload' => ['nullable', 'array'],
        ]);
        try { $claim = $ads->claimWebReward($request->user(), $data['claim_token'], (array) ($data['provider_payload'] ?? [])); }
        catch (RuntimeException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        return response()->json([
            'message' => number_format((int) $claim->points).' Pulse Sparks added to your wallet.',
            'data' => ['claim' => $claim, 'balance' => $points->balance($request->user()), 'status' => $ads->userStatus($request->user())],
        ]);
    }

    public function mobileConfig(Request $request, PulseRewardedAdService $ads)
    {
        $platform = (string) $request->query('platform', 'android');
        try { $data = $ads->mobileConfig($request->user(), $platform); }
        catch (RuntimeException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        return response()->json(['data' => $data]);
    }

    public function admobSsv(Request $request, PulseRewardedAdService $ads)
    {
        try { $data = $ads->processAdMobSsv($request); }
        catch (\Throwable $e) {
            report($e);
            return response('reward rejected', 400)->header('Content-Type', 'text/plain');
        }
        return response('ok', 200)->header('Content-Type', 'text/plain');
    }
}
