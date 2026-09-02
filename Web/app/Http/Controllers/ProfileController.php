<?php

namespace App\Http\Controllers;

use App\Models\BinanceConnection;
use App\Models\NewsArticle;
use App\Models\PulseAlert;
use App\Models\PulseMembershipRequest;
use App\Models\PulseSignal;
use App\Models\PulseTrade;
use App\Models\PulseUserSetting;
use App\Services\MarketDataService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request, MarketDataService $market)
    {
        $user = $request->user();
        $user->loadMissing(['pulseAccess.plan']);

        $watchlist = $user->watchlists()->orderBy('sort_order')->get();
        $pulseAccess = $user->pulseAccess;
        $pulseAccessActive = $user->hasPulseAccess();
        $settings = PulseUserSetting::query()->where('user_id', $user->id)->first();
        $environment = $settings?->environment ?: 'testnet';
        $connection = BinanceConnection::query()
            ->where('user_id', $user->id)
            ->where('environment', $environment)
            ->first();
        $connectionReady = (bool) ($connection?->is_active && $connection?->last_tested_at && ! $connection?->last_error);

        $openStatuses = ['submitting', 'pending', 'open', 'closing', 'protection_failed'];
        $thirtyDayStart = now()->subDays(30)->startOfDay();

        return view('profile', [
            'user' => $user,
            'watchlist' => $watchlist,
            'market' => $market->cachedOverview(),
            'news' => NewsArticle::where('status', 'published')->latest('published_at')->take(4)->get(),
            'pulseAccess' => $pulseAccess,
            'pulseAccessActive' => $pulseAccessActive,
            'settings' => $settings,
            'environment' => $environment,
            'connection' => $connection,
            'connectionReady' => $connectionReady,
            'profileStats' => [
                'active_signals' => PulseSignal::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->count(),
                'open_positions' => PulseTrade::query()->where('user_id', $user->id)->whereIn('status', $openStatuses)->count(),
                'executed_trades_30d' => PulseTrade::query()->where('user_id', $user->id)->where('created_at', '>=', $thirtyDayStart)->count(),
                'realized_pnl_30d' => (float) PulseTrade::query()->where('user_id', $user->id)->where('status', 'closed')->where('closed_at', '>=', $thirtyDayStart)->sum('realized_pnl'),
                'unread_alerts' => PulseAlert::query()->where('user_id', $user->id)->where('is_read', false)->count(),
            ],
            'latestMembershipRequest' => PulseMembershipRequest::query()
                ->where('user_id', $user->id)
                ->with('plan')
                ->latest()
                ->first(),
        ]);
    }
}
