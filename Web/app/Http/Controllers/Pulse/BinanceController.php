<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\BinanceConnection;
use App\Services\BinanceFuturesService;
use App\Services\PulseAccessService;
use App\Services\PulseAuditService;
use App\Models\PulseUserSetting;
use Illuminate\Http\Request;

class BinanceController extends Controller
{
    public function index(Request $request)
    {
        return view('pulse.binance', [
            'connections' => BinanceConnection::query()->where('user_id', $request->user()->id)->orderBy('environment')->get(),
            'liveAllowed' => (bool) config('pulse.allow_live_trading')
                && app(PulseAccessService::class)->systemEnabled('live_trading_enabled', false)
                && app(PulseAccessService::class)->allows($request->user(), 'live_trading', false),
        ]);
    }

    public function store(Request $request, PulseAuditService $audit, PulseAccessService $access, BinanceFuturesService $binance)
    {
        $data = $request->validate([
            'environment' => ['required', 'in:testnet,live'], 'label' => ['required', 'string', 'max:100'],
            'api_key' => ['required', 'string', 'max:255'], 'api_secret' => ['required', 'string', 'max:255'],
        ]);
        try { $access->assertEnvironment($request->user(), $data['environment']); }
        catch (\Throwable $e) { return back()->withErrors(['environment' => $e->getMessage()])->withInput(); }

        $existing = BinanceConnection::query()->where('user_id', $request->user()->id)->where('environment', $data['environment'])->first();
        $hasOtherActive = BinanceConnection::query()->where('user_id', $request->user()->id)
            ->where('is_active', true)->when($existing, fn ($query) => $query->whereKeyNot($existing->id))->exists();
        $shouldActivate = (bool) ($existing?->is_active) || ! $hasOtherActive;
        $connection = BinanceConnection::updateOrCreate(
            ['user_id' => $request->user()->id, 'environment' => $data['environment']],
            ['label' => $data['label'], 'api_key' => trim($data['api_key']), 'api_secret' => trim($data['api_secret']),
             'is_active' => $shouldActivate, 'permissions' => [], 'last_tested_at' => null, 'last_error' => null],
        );
        $audit->record('binance.connection_saved', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, ['auto_verify' => true], $request);

        try {
            $result = $binance->testConnection($connection);
            $canTrade = (bool) ($result['can_trade'] ?? false);
            $connection->update(['last_tested_at' => now(), 'last_error' => null, 'permissions' => [
                'can_trade' => $canTrade,
                'account_equity' => (float) ($result['total_wallet_balance'] ?? 0),
                'available_balance' => (float) ($result['available_balance'] ?? 0),
            ]]);
            $audit->record('binance.connection_auto_verified', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, ['can_trade' => $canTrade], $request);

            if ($canTrade && $shouldActivate) {
                BinanceConnection::query()->where('user_id', $request->user()->id)->whereKeyNot($connection->id)->update(['is_active' => false]);
                $connection->update(['is_active' => true]);
                PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], [
                    'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false, 'emergency_stop' => false,
                    'default_leverage' => 3, 'margin_type' => 'ISOLATED', 'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1,
                    'sizing_mode' => 'fixed_notional', 'fixed_notional' => 25, 'minimum_signal_score' => 70, 'default_order_type' => 'MARKET',
                    'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0, 'max_open_positions' => 2,
                    'selected_pairs' => ['BTCUSDT','ETHUSDT','SOLUSDT'], 'notification_preferences' => [],
                ])->update(['environment' => $connection->environment]);
            }

            if ($canTrade) {
                $message = ucfirst($connection->environment).' Binance connection was saved and verified successfully.';
                $message .= $shouldActivate ? ' Pulse selected it automatically and it is ready for guided Open Trade.' : ' Your existing active environment was left unchanged.';
                return back()->with('success', $message);
            }

            return back()->with('warning', ucfirst($connection->environment).' credentials were saved and verified, but Binance did not confirm Futures trading permission. Enable Futures trading for this API key and test again.');
        } catch (\Throwable $e) {
            $connection->update(['last_tested_at' => now(), 'last_error' => $e->getMessage(), 'permissions' => []]);
            $audit->record('binance.connection_auto_verify_failed', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, ['error' => $e->getMessage()], $request);
            return back()->withErrors(['binance' => 'Credentials were saved, but Binance verification failed: '.$e->getMessage()]);
        }
    }

    public function test(Request $request, BinanceConnection $connection, BinanceFuturesService $binance, PulseAuditService $audit)
    {
        abort_unless($connection->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        try {
            $result = $binance->testConnection($connection);
            $connection->update(['last_tested_at' => now(), 'last_error' => null, 'permissions' => [
                'can_trade' => $result['can_trade'],
                'account_equity' => $result['total_wallet_balance'],
                'available_balance' => $result['available_balance'],
            ]]);
            $audit->record('binance.connection_tested', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, ['connected' => true, 'can_trade' => $result['can_trade']], $request);
            $autoActivated = false;
            if ($result['can_trade'] && ! BinanceConnection::query()->where('user_id', $request->user()->id)->where('is_active', true)->exists()) {
                $connection->update(['is_active' => true]);
                PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], [
                    'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false, 'emergency_stop' => false,
                    'default_leverage' => 3, 'margin_type' => 'ISOLATED', 'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1,
                    'sizing_mode' => 'fixed_notional', 'fixed_notional' => 25, 'minimum_signal_score' => 70, 'default_order_type' => 'MARKET',
                    'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0, 'max_open_positions' => 2,
                    'selected_pairs' => ['BTCUSDT','ETHUSDT','SOLUSDT'], 'notification_preferences' => [],
                ])->update(['environment' => $connection->environment]);
                $autoActivated = true;
            }
            $message = $result['can_trade'] ? 'Connection verified with Futures trading permission.' : 'Connection verified, but Binance reports that trading is not enabled for this key/account.';
            if ($autoActivated) $message .= ' Pulse selected it automatically as your active environment.';
            return back()->with($result['can_trade'] ? 'success' : 'warning', $message.' Binance reported an available account balance of '.number_format($result['available_balance'], 4).'.');
        } catch (\Throwable $e) {
            $connection->update(['last_tested_at' => now(), 'last_error' => $e->getMessage(), 'permissions' => []]);
            $audit->record('binance.connection_failed', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, ['error' => $e->getMessage()], $request);
            return back()->withErrors(['binance' => $e->getMessage()]);
        }
    }

    public function activate(Request $request, BinanceConnection $connection, PulseAccessService $access, PulseAuditService $audit)
    {
        abort_unless($connection->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        try { $access->assertEnvironment($request->user(), $connection->environment); }
        catch (\Throwable $e) { return back()->withErrors(['environment' => $e->getMessage()]); }

        if (! $connection->last_tested_at || $connection->last_error || ! data_get($connection->permissions, 'can_trade', false)) {
            return back()->withErrors(['binance' => 'Test this Binance Futures connection successfully before making it the active trading environment.']);
        }

        BinanceConnection::query()->where('user_id', $request->user()->id)->update(['is_active' => false]);
        $connection->update(['is_active' => true]);
        PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], [
            'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false, 'emergency_stop' => false,
            'default_leverage' => 3, 'margin_type' => 'ISOLATED', 'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1,
            'sizing_mode' => 'fixed_notional', 'fixed_notional' => 25, 'minimum_signal_score' => 70, 'default_order_type' => 'MARKET',
            'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0, 'max_open_positions' => 2,
            'selected_pairs' => ['BTCUSDT','ETHUSDT','SOLUSDT'], 'notification_preferences' => [],
        ])->update(['environment' => $connection->environment]);
        $audit->record('binance.connection_activated', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, [], $request);
        return back()->with('success', ucfirst($connection->environment).' Binance USD-M Futures is now the active Pulse trading environment.');
    }

    public function destroy(Request $request, BinanceConnection $connection, PulseAuditService $audit)
    {
        abort_unless($connection->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $environment = $connection->environment; $id = $connection->id; $connection->delete();
        $audit->record('binance.connection_removed', $request->user(), 'BinanceConnection', $id, $environment, [], $request);
        return back()->with('success', ucfirst($environment).' Binance credentials were removed. Existing exchange positions are not closed automatically.');
    }
}
