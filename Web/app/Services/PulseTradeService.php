<?php

namespace App\Services;

use App\Models\BinanceConnection;
use App\Models\PulseAlert;
use App\Models\PulsePair;
use App\Models\PulseSignal;
use App\Models\PulseTrade;
use App\Models\PulseUserSetting;
use App\Models\User;
use RuntimeException;

class PulseTradeService
{
    public function __construct(
        private readonly BinanceFuturesService $binance,
        private readonly PulseAuditService $audit,
        private readonly PulseAccessService $access,
        private readonly PulsePairAccessService $pairAccess,
        private readonly BrandedMailService $mail,
    ) {}

    public function execute(User $user, PulseSignal $signal, array $input, bool $automatic = false): PulseTrade
    {
        $this->access->assertActive($user);
        abort_unless($signal->user_id === null || $signal->user_id === $user->id || $user->isAdmin(), 403);

        if (! $signal->isActionable()) {
            throw new RuntimeException('This signal is no longer active or actionable.');
        }

        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaultSettings());
        $executionMode = strtolower((string) ($settings->execution_mode ?? 'signal_only'));

        // V14.8.20: an explicit Confirm & Open Trade click is the user's consent for
        // manual signal execution. Do not force eligible customers through Settings first.
        // Pulse applies the managed manual defaults only when the account is currently
        // signal-only; automatic mode is left untouched so automation is never disabled
        // by a manual order.
        if (! $automatic && $executionMode === 'signal_only') {
            if (! $this->access->allows($user, 'manual_trading', false)) {
                throw new RuntimeException('Manual signal execution is not included in the current Pulse plan.');
            }
            $settings->update([
                'execution_mode' => 'manual',
                'auto_trade_enabled' => false,
                'default_order_type' => 'LIMIT',
                'default_leverage' => max(1, (int) ($settings->default_leverage ?: 3)),
                'margin_type' => in_array(strtoupper((string) $settings->margin_type), ['ISOLATED', 'CROSSED'], true) ? strtoupper((string) $settings->margin_type) : 'ISOLATED',
                'risk_per_trade_percent' => (float) ($settings->risk_per_trade_percent ?: 1),
                'sizing_mode' => in_array((string) $settings->sizing_mode, ['fixed_notional', 'fixed_quantity'], true) ? $settings->sizing_mode : 'fixed_notional',
                'fixed_notional' => (float) ($settings->fixed_notional ?: 25),
            ]);
            $executionMode = 'manual';
            $this->audit->record('trade.managed_setup_enabled', $user, 'PulseUserSetting', $settings->id, $settings->environment, [
                'source' => 'explicit_signal_trade',
                'managed_defaults' => true,
            ]);
        }
        if ($automatic && $executionMode !== 'automatic') {
            throw new RuntimeException('Automatic execution is not enabled in Pulse Settings.');
        }
        if ($settings->emergency_stop) {
            throw new RuntimeException('Pulse emergency stop is active. Disable it only after reviewing open positions and exchange orders.');
        }

        if (! $this->access->systemEnabled('execution_enabled', true)) {
            throw new RuntimeException('Pulse trade execution is disabled by the system administrator.');
        }

        if ($this->access->systemEnabled('emergency_stop_all', false)) {
            throw new RuntimeException('The global Pulse emergency stop is active. New exchange execution is blocked.');
        }

        $permission = $automatic ? 'auto_trading' : 'manual_trading';
        if (! $this->access->allows($user, $permission, ! $automatic)) {
            throw new RuntimeException($automatic ? 'Automatic trading is not allowed for this account or plan.' : 'Manual trading is not allowed for this account or plan.');
        }

        if ($automatic && (! config('pulse.allow_automatic_trading', false) || ! $this->access->systemEnabled('automatic_trading_enabled', false))) {
            throw new RuntimeException('Automatic trading is disabled for this installation.');
        }

        $environment = strtolower((string) ($input['environment'] ?? $settings->environment ?? 'testnet'));
        $this->access->assertEnvironment($user, $environment);
        $this->assertRiskLimits($user, $settings, $automatic);

        $connection = $this->connectionFor($user, $environment);
        $connection = $this->refreshExecutionSnapshot($connection);
        if (! (bool) data_get($connection->permissions, 'can_trade', false)) {
            throw new RuntimeException('Binance trading permission could not be confirmed for this connection. Open Binance Connection and verify the API key once.');
        }

        $pair = PulsePair::query()->where('symbol', strtoupper($signal->symbol))->where('is_enabled', true)->first();
        if (! $pair) {
            throw new RuntimeException('This trading pair is not enabled in Pulse.');
        }
        if (! $user->isAdmin() && ! $this->pairAccess->isAllowed($user, $pair->symbol)) {
            throw new RuntimeException('This Binance Futures market is not included in the current Pulse package.');
        }

        $orderType = strtoupper((string) ($input['order_type'] ?? $settings->default_order_type ?? 'MARKET'));
        if (! in_array($orderType, ['MARKET', 'LIMIT'], true)) {
            throw new RuntimeException('Order type must be MARKET or LIMIT.');
        }
        // Refresh the pair's current Binance filters before risk math and order
        // construction. This prevents stale tick/step precision from reaching the UI
        // or exchange when Binance changes a contract's trading rules.
        $pair = $this->binance->refreshPairRules($pair, $environment);
        $timeInForce = strtoupper((string) ($input['time_in_force'] ?? 'GTC'));
        if (! in_array($timeInForce, ['GTC', 'IOC', 'FOK'], true)) {
            throw new RuntimeException('Time in force must be GTC, IOC or FOK.');
        }
        $clientReference = trim((string) ($input['client_reference'] ?? ''));
        if ($clientReference !== '' && ! preg_match('/^[.A-Za-z0-9_:\/\-]{1,36}$/', $clientReference)) {
            throw new RuntimeException('Client reference contains unsupported characters.');
        }

        $positionSide = strtoupper((string) ($input['position_side'] ?? $settings->position_mode ?? 'BOTH'));
        if (! in_array($positionSide, ['BOTH', 'LONG', 'SHORT'], true)) {
            throw new RuntimeException('Position side must be BOTH, LONG or SHORT.');
        }

        $side = $signal->direction === 'LONG' ? 'BUY' : 'SELL';
        $leverage = max(1, min(
            (int) ($input['leverage'] ?? $settings->default_leverage ?? 1),
            (int) config('pulse.risk.max_leverage', 20),
        ));
        $price = $this->binance->normalizePrice($pair, (float) ($input['price'] ?? $signal->entry_price));
        $stopLoss = $this->binance->normalizePrice($pair, (float) ($input['stop_loss'] ?? $signal->stop_loss));
        $takeProfit = $this->binance->normalizePrice($pair, (float) ($input['take_profit'] ?? $signal->take_profit));
        $quantity = $this->resolveQuantity($pair, $signal, $settings, $input);

        $this->validateTradeValues($pair, $signal, $quantity, $price, $stopLoss, $takeProfit);
        $accountEquity = (float) data_get($connection->permissions, 'account_equity', 0);
        $availableBalance = (float) data_get($connection->permissions, 'available_balance', 0);
        if ($accountEquity <= 0 || $availableBalance <= 0) {
            throw new RuntimeException('Retest the Binance connection to refresh account equity and available balance before submitting an order.');
        }
        $notional = $price * $quantity;
        $requiredMargin = $notional / max(1, $leverage);
        $estimatedRisk = abs($price - $stopLoss) * $quantity;
        $riskPercent = ($estimatedRisk / $accountEquity) * 100;
        if ($riskPercent > (float) $settings->risk_per_trade_percent) {
            throw new RuntimeException('The proposed order exceeds the configured risk-per-trade limit. Reduce quantity or review risk controls.');
        }
        if ($requiredMargin > $availableBalance) {
            throw new RuntimeException('Available Binance balance is below the estimated margin required for this order.');
        }

        $trade = PulseTrade::create([
            'user_id' => $user->id,
            'signal_id' => $signal->id,
            'symbol' => $pair->symbol,
            'side' => $side,
            'environment' => $environment,
            'order_type' => $orderType,
            'leverage' => $leverage,
            'quantity' => $quantity,
            'entry_price' => $price,
            'current_price' => $price,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'exchange_position_side' => $positionSide,
            'status' => 'submitting',
            'protection_status' => 'pending',
            'meta' => [
                'automatic' => $automatic,
                'submission_started_at' => now()->toIso8601String(),
                'time_in_force' => $orderType === 'LIMIT' ? $timeInForce : null,
                'client_reference' => $clientReference !== '' ? $clientReference : null,
                'account_equity' => $accountEquity,
                'available_balance' => $availableBalance,
                'notional_value' => $notional,
                'required_margin' => $requiredMargin,
                'estimated_risk' => $estimatedRisk,
                'risk_percent' => $riskPercent,
            ],
        ]);

        try {
            $this->binance->changeMarginType($connection, $pair->symbol, (string) ($settings->margin_type ?: 'ISOLATED'));
            $this->binance->changeLeverage($connection, $pair->symbol, $leverage);

            $parameters = [
                'symbol' => $pair->symbol,
                'side' => $side,
                'type' => $orderType,
                'quantity' => $quantity,
                'positionSide' => $positionSide,
                'newOrderRespType' => 'RESULT',
            ];
            if ($orderType === 'LIMIT') {
                $parameters['price'] = $price;
                $parameters['timeInForce'] = $timeInForce;
            }
            if ($clientReference !== '') $parameters['newClientOrderId'] = $clientReference;

            $order = $this->binance->placeOrder($connection, $parameters);
            $exchangeStatus = strtoupper((string) ($order['status'] ?? 'NEW'));
            $filled = $exchangeStatus === 'FILLED';
            $averagePrice = (float) ($order['avgPrice'] ?? 0) ?: $price;

            $trade->update([
                'exchange_order_id' => isset($order['orderId']) ? (string) $order['orderId'] : null,
                'entry_price' => $averagePrice,
                'current_price' => $averagePrice,
                'status' => $filled ? 'open' : 'pending',
                'opened_at' => $filled ? now() : null,
                'meta' => array_merge($trade->meta ?: [], ['entry_order' => $this->safeExchangePayload($order)]),
            ]);

            if ($filled) {
                $this->attachProtectionOrEmergencyClose($trade->fresh(), $connection);
            }

            $signal->update(['status' => 'executed']);
            $this->notify($trade->fresh(), $filled ? 'Pulse trade opened' : 'Pulse order accepted',
                "{$signal->direction} {$pair->symbol} was accepted by Binance {$environment}.",
                $environment === 'live' ? 'warning' : 'success');

            $this->audit->record($automatic ? 'trade.auto_executed' : 'trade.executed', $user, 'PulseTrade', $trade->id, $environment, [
                'symbol' => $pair->symbol, 'side' => $side, 'order_type' => $orderType,
                'quantity' => $quantity, 'leverage' => $leverage, 'exchange_order_id' => $trade->exchange_order_id,
            ]);

            return $trade->fresh();
        } catch (\Throwable $e) {
            if ($trade->fresh()->status === 'submitting') {
                $trade->update([
                    'status' => 'failed',
                    'protection_status' => 'not_required',
                    'close_reason' => 'entry_submission_failed',
                    'meta' => array_merge($trade->meta ?: [], ['submission_error' => $e->getMessage()]),
                ]);
            }
            $this->audit->record('trade.execution_failed', $user, 'PulseTrade', $trade->id, $environment, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function sync(User $user): array
    {
        // Reconciliation is a safety operation, not a new trading entitlement.
        // Existing pending/open trades must continue to synchronize and receive
        // exchange-side protection even if the customer's Pulse package expires
        // while a trade is still live. New execution remains access-gated in execute().
        $summary = ['synced' => 0, 'opened' => 0, 'closed' => 0, 'protected' => 0, 'errors' => []];

        $trades = PulseTrade::query()->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'open', 'closing', 'protection_failed'])
            ->orderBy('id')->get();

        foreach ($trades as $trade) {
            try {
                $connection = $this->connectionFor($user, $trade->environment);
                $trade = $this->syncOne($trade, $connection, $summary);
                $summary['synced']++;
            } catch (\Throwable $e) {
                $summary['errors'][] = ['trade_id' => $trade->id, 'message' => $e->getMessage()];
            }
        }

        $this->audit->record('orders.synchronized', $user, null, null, null, $summary);
        return $summary;
    }

    public function close(User $actor, PulseTrade $trade, string $reason = 'user_requested'): PulseTrade
    {
        if ($trade->user_id !== $actor->id && ! $actor->isAdmin()) {
            throw new RuntimeException('This trade does not belong to the current user.');
        }
        if (! in_array($trade->status, ['pending', 'open', 'closing', 'protection_failed'], true)) {
            throw new RuntimeException('This trade has no cancellable order or open position.');
        }

        $owner = $trade->user;
        $connection = $this->connectionFor($owner, $trade->environment);

        if ($trade->status === 'pending') {
            if ($trade->exchange_order_id) {
                $this->binance->cancelOrder($connection, $trade->symbol, $trade->exchange_order_id);
            }
            $trade->update(['status' => 'cancelled', 'closed_at' => now(), 'close_reason' => 'pending_order_cancelled']);
        } else {
            $trade->update(['status' => 'closing', 'close_reason' => $reason]);
            $this->cancelProtection($trade, $connection);

            $position = $this->findPosition($connection, $trade);
            $amount = abs((float) ($position['positionAmt'] ?? 0));
            if ($amount <= 0.0000000001) {
                $trade->update(['status' => 'closed', 'closed_at' => now(), 'close_reason' => 'exchange_position_already_closed']);
            } else {
                $close = $this->binance->closePosition($connection, $trade->symbol, $trade->side, $amount, $trade->exchange_position_side);
                $filled = strtoupper((string) ($close['status'] ?? '')) === 'FILLED';
                $trade->update([
                    'exchange_close_order_id' => isset($close['orderId']) ? (string) $close['orderId'] : null,
                    'status' => $filled ? 'closed' : 'closing',
                    'closed_at' => $filled ? now() : null,
                    'meta' => array_merge($trade->meta ?: [], ['close_order' => $this->safeExchangePayload($close)]),
                ]);
            }
        }

        $this->refreshFinancials($trade->fresh(), $connection);
        $this->notify($trade->fresh(), 'Pulse close request processed', "{$trade->symbol} cancellation or position close was sent to Binance.", 'info');
        $this->audit->record('trade.close_requested', $actor, 'PulseTrade', $trade->id, $trade->environment, ['reason' => $reason, 'status' => $trade->fresh()->status]);

        return $trade->fresh();
    }

    public function emergencyStop(User $user): array
    {
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaultSettings());
        $settings->update(['emergency_stop' => true, 'auto_trade_enabled' => false]);

        $result = ['closed' => 0, 'cancelled' => 0, 'errors' => []];
        foreach (PulseTrade::query()->where('user_id', $user->id)->whereIn('status', ['pending', 'open', 'closing', 'protection_failed'])->get() as $trade) {
            try {
                $wasPending = $trade->status === 'pending';
                $this->close($user, $trade, 'emergency_stop');
                $wasPending ? $result['cancelled']++ : $result['closed']++;
            } catch (\Throwable $e) {
                $result['errors'][] = ['trade_id' => $trade->id, 'message' => $e->getMessage()];
            }
        }

        $this->audit->record('risk.emergency_stop', $user, null, null, $settings->environment, $result);
        return $result;
    }

    public function exchangeSnapshot(User $user, ?string $environment = null): array
    {
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaultSettings());
        $environment = $environment ?: $settings->environment;
        $this->access->assertEnvironment($user, $environment);
        return $this->binance->snapshot($this->connectionFor($user, $environment));
    }

    private function syncOne(PulseTrade $trade, BinanceConnection $connection, array &$summary): PulseTrade
    {
        if ($trade->status === 'pending' && $trade->exchange_order_id) {
            $order = $this->binance->queryOrder($connection, $trade->symbol, $trade->exchange_order_id);
            $status = strtoupper((string) ($order['status'] ?? ''));
            if ($status === 'FILLED') {
                $average = (float) ($order['avgPrice'] ?? 0) ?: (float) $trade->entry_price;
                $trade->update([
                    'status' => 'open', 'entry_price' => $average, 'current_price' => $average,
                    'opened_at' => $trade->opened_at ?: now(),
                    'meta' => array_merge($trade->meta ?: [], ['entry_order_sync' => $this->safeExchangePayload($order)]),
                ]);
                $summary['opened']++;
                $this->attachProtectionOrEmergencyClose($trade->fresh(), $connection);
                $summary['protected']++;
            } elseif (in_array($status, ['CANCELED', 'EXPIRED', 'REJECTED'], true)) {
                $trade->update(['status' => 'cancelled', 'closed_at' => now(), 'close_reason' => strtolower($status), 'protection_status' => 'not_required']);
                return $trade->fresh();
            }
        }

        $trade = $trade->fresh();
        $position = $this->findPosition($connection, $trade);
        $amount = (float) ($position['positionAmt'] ?? 0);
        $markPrice = (float) ($position['markPrice'] ?? $trade->current_price);
        $unrealized = (float) ($position['unRealizedProfit'] ?? 0);

        $updates = ['current_price' => $markPrice, 'unrealized_pnl' => $unrealized, 'last_synced_at' => now()];
        if ($trade->status === 'closing' && $trade->exchange_close_order_id) {
            $closeOrder = $this->binance->queryOrder($connection, $trade->symbol, $trade->exchange_close_order_id);
            $updates['meta'] = array_merge($trade->meta ?: [], ['close_order_sync' => $this->safeExchangePayload($closeOrder)]);
        }

        if (in_array($trade->status, ['open', 'closing', 'protection_failed'], true) && abs($amount) <= 0.0000000001) {
            $exitEvidence = $this->protectionExitEvidence($trade, $connection);
            $updates['status'] = 'closed';
            $updates['closed_at'] = $trade->closed_at ?: now();
            $updates['close_reason'] = $trade->close_reason && ! in_array($trade->close_reason, ['exchange_position_closed',''], true)
                ? $trade->close_reason
                : ($exitEvidence['reason'] ?? 'exchange_position_closed');
            if (! empty($exitEvidence['order_id'])) $updates['exchange_close_order_id'] = (string) $exitEvidence['order_id'];
            if (! empty($exitEvidence['meta'])) $updates['meta'] = array_merge((array) ($updates['meta'] ?? $trade->meta ?? []), ['protection_exit' => $exitEvidence['meta']]);
            $updates['protection_status'] = 'not_required';
            $summary['closed']++;
        }
        $trade->update($updates);
        $this->refreshFinancials($trade->fresh(), $connection);
        $this->verifyProtection($trade->fresh(), $connection);

        return $trade->fresh();
    }

    private function attachProtectionOrEmergencyClose(PulseTrade $trade, BinanceConnection $connection): void
    {
        if ($trade->exchange_tp_order_id && $trade->exchange_sl_order_id) {
            $trade->update(['protection_status' => 'confirmed']);
            return;
        }

        try {
            $orders = $this->binance->placeProtectionOrders(
                $connection, $trade->symbol, $trade->exchange_position_side,
                $trade->side, (float) $trade->take_profit, (float) $trade->stop_loss,
            );
            $trade->update([
                'exchange_tp_order_id' => isset($orders['take_profit']['algoId']) ? (string) $orders['take_profit']['algoId'] : null,
                'exchange_sl_order_id' => isset($orders['stop_loss']['algoId']) ? (string) $orders['stop_loss']['algoId'] : null,
                'protection_status' => 'confirmed',
                'meta' => array_merge($trade->meta ?: [], ['protection' => [
                    'take_profit' => $this->safeExchangePayload($orders['take_profit']),
                    'stop_loss' => $this->safeExchangePayload($orders['stop_loss']),
                ]]),
            ]);
        } catch (\Throwable $protectionError) {
            $trade->update([
                'status' => 'protection_failed', 'protection_status' => 'failed', 'close_reason' => 'protection_failed',
                'meta' => array_merge($trade->meta ?: [], ['protection_error' => $protectionError->getMessage()]),
            ]);
            $this->notify($trade->fresh(), 'Protection failed — emergency close started',
                "{$trade->symbol} filled but exchange-side TP/SL could not be confirmed. Pulse is closing the position.", 'danger', 'risk');

            try {
                $position = $this->findPosition($connection, $trade);
                $amount = abs((float) ($position['positionAmt'] ?? $trade->quantity));
                if ($amount > 0) {
                    $close = $this->binance->closePosition($connection, $trade->symbol, $trade->side, $amount, $trade->exchange_position_side);
                    $filled = strtoupper((string) ($close['status'] ?? '')) === 'FILLED';
                    $trade->update([
                        'exchange_close_order_id' => isset($close['orderId']) ? (string) $close['orderId'] : null,
                        'status' => $filled ? 'closed' : 'closing',
                        'closed_at' => $filled ? now() : null,
                        'close_reason' => 'emergency_close_after_protection_failure',
                        'meta' => array_merge($trade->meta ?: [], ['emergency_close_order' => $this->safeExchangePayload($close)]),
                    ]);
                }
            } catch (\Throwable $closeError) {
                $trade->update(['meta' => array_merge($trade->meta ?: [], ['emergency_close_error' => $closeError->getMessage()])]);
                throw new RuntimeException('Protection failed and the emergency close could not be confirmed. Review Binance immediately: '.$closeError->getMessage(), previous: $closeError);
            }

            throw new RuntimeException('Exchange-side TP/SL protection failed. Pulse initiated an emergency close: '.$protectionError->getMessage(), previous: $protectionError);
        }
    }

    private function verifyProtection(PulseTrade $trade, BinanceConnection $connection): void
    {
        if ($trade->status !== 'open' || ! $trade->exchange_tp_order_id || ! $trade->exchange_sl_order_id) {
            return;
        }
        try {
            $tp = $this->binance->queryAlgoOrder($connection, $trade->exchange_tp_order_id);
            $sl = $this->binance->queryAlgoOrder($connection, $trade->exchange_sl_order_id);
            $activeStates = ['NEW', 'WORKING', 'ACCEPTED', 'TRIGGERED'];
            $tpStatus = strtoupper((string) ($tp['algoStatus'] ?? $tp['status'] ?? ''));
            $slStatus = strtoupper((string) ($sl['algoStatus'] ?? $sl['status'] ?? ''));
            $confirmed = in_array($tpStatus, $activeStates, true) && in_array($slStatus, $activeStates, true);
            $trade->update([
                'protection_status' => $confirmed ? 'confirmed' : 'review_required',
                'meta' => array_merge($trade->meta ?: [], ['protection_sync' => [
                    'take_profit' => $this->safeExchangePayload($tp), 'stop_loss' => $this->safeExchangePayload($sl),
                ]]),
            ]);
        } catch (\Throwable $e) {
            $trade->update(['protection_status' => 'review_required', 'meta' => array_merge($trade->meta ?: [], ['protection_sync_error' => $e->getMessage()])]);
        }
    }

    /**
     * Identify whether an exchange-side protective TP or SL closed the position.
     * Binance algo responses can vary between environments, so ABS checks both
     * the algo state and any resulting child order before assigning a TP/SL label.
     */
    private function protectionExitEvidence(PulseTrade $trade, BinanceConnection $connection): array
    {
        $candidates = [
            'take_profit' => $trade->exchange_tp_order_id,
            'stop_loss' => $trade->exchange_sl_order_id,
        ];
        $evidence = [];

        foreach ($candidates as $reason => $algoId) {
            if (! $algoId) continue;
            try {
                $algo = $this->binance->queryAlgoOrder($connection, $algoId);
                $algoStatus = strtoupper((string) ($algo['algoStatus'] ?? $algo['status'] ?? ''));
                $childOrderId = $algo['actualOrderId'] ?? $algo['orderId'] ?? $algo['actualOrderID'] ?? null;
                $childStatus = '';
                $child = null;
                if ($childOrderId) {
                    try {
                        $child = $this->binance->queryOrder($connection, $trade->symbol, $childOrderId);
                        $childStatus = strtoupper((string) ($child['status'] ?? ''));
                    } catch (\Throwable) {
                        $child = null;
                    }
                }

                // Assign an exact TP/SL close reason only when the resulting exchange
                // order itself is confirmed FILLED. An algo reaching a terminal state alone
                // is not sufficient evidence because canceled/expired conditional orders can
                // also leave terminal algo records.
                $filled = $childStatus === 'FILLED';
                $evidence[$reason] = [
                    'algo_id' => (string) $algoId,
                    'algo_status' => $algoStatus,
                    'order_id' => $childOrderId ? (string) $childOrderId : null,
                    'order_status' => $childStatus ?: null,
                    'filled' => $filled,
                ];
                if ($filled) {
                    return ['reason' => $reason, 'order_id' => $childOrderId, 'meta' => $evidence];
                }
            } catch (\Throwable $e) {
                $evidence[$reason] = ['algo_id' => (string) $algoId, 'error' => $e->getMessage(), 'filled' => false];
            }
        }

        return ['reason' => null, 'order_id' => null, 'meta' => $evidence];
    }

    private function refreshFinancials(PulseTrade $trade, BinanceConnection $connection): void
    {
        try {
            $ids = array_values(array_unique(array_filter([(string) $trade->exchange_order_id, (string) $trade->exchange_close_order_id])));
            if ($ids !== []) {
                $fills = collect($ids)->flatMap(fn ($orderId) => $this->binance->userTrades(
                    $connection, $trade->symbol, null, 1000, $orderId,
                ));
            } else {
                $sevenDaysAgo = now()->subDays(6)->startOfDay();
                $start = max(
                    $sevenDaysAgo->getTimestampMs(),
                    $trade->created_at?->copy()->subMinute()->getTimestampMs() ?? $sevenDaysAgo->getTimestampMs(),
                );
                $fills = collect($this->binance->userTrades($connection, $trade->symbol, $start));
            }
            $fills = $fills->unique(fn (array $fill) => (string) ($fill['id'] ?? serialize($fill)))->values();
            $feeBreakdown = $fills
                ->groupBy(fn (array $fill) => strtoupper((string) ($fill['commissionAsset'] ?? 'UNKNOWN')))
                ->map(fn ($assetFills) => round((float) $assetFills->sum(fn (array $fill): float => (float) ($fill['commission'] ?? 0)), 12))
                ->all();
            $singleFeeAsset = count($feeBreakdown) === 1 ? (string) array_key_first($feeBreakdown) : null;
            $fees = $singleFeeAsset ? (float) $feeBreakdown[$singleFeeAsset] : 0.0;
            $realized = (float) $fills->sum(fn (array $fill): float => (float) ($fill['realizedPnl'] ?? 0));
            $trade->update([
                'fees' => $fees,
                'realized_pnl' => $realized,
                'commission_asset' => $singleFeeAsset ?: ($feeBreakdown !== [] ? 'MIXED' : $trade->commission_asset),
                'last_synced_at' => now(),
                'meta' => array_merge($trade->meta ?: [], ['commission_breakdown' => $feeBreakdown]),
            ]);
        } catch (\Throwable $e) {
            $trade->update(['meta' => array_merge($trade->meta ?: [], ['financial_sync_error' => $e->getMessage()])]);
        }
    }

    private function cancelProtection(PulseTrade $trade, BinanceConnection $connection): void
    {
        foreach (array_filter([$trade->exchange_tp_order_id, $trade->exchange_sl_order_id]) as $algoId) {
            try { $this->binance->cancelAlgoOrder($connection, $algoId); } catch (\Throwable) {}
        }
        // Cancel only the TP/SL orders recorded for this Pulse trade. Broad symbol-level
        // cancellation could remove unrelated user orders on the same market.
        $trade->update(['protection_status' => 'not_required']);
    }

    private function assertRiskLimits(User $user, PulseUserSetting $settings, bool $automatic): void
    {
        $plan = $user->pulsePlan();
        $planMaxOpen = (int) ($plan?->max_open_trades ?? 0);
        if ($plan && $planMaxOpen <= 0) {
            throw new RuntimeException('Open-position execution is not included in the current Pulse plan.');
        }
        $maxOpen = min(max(1, (int) ($settings->max_open_positions ?: 1)), max(1, $planMaxOpen ?: 1));
        $open = PulseTrade::query()->where('user_id', $user->id)->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])->count();
        if ($open >= $maxOpen) {
            throw new RuntimeException("The current risk settings allow up to {$maxOpen} concurrent Pulse positions or orders.");
        }

        $dailyLimit = $automatic ? (int) ($plan?->auto_trades_per_day ?: 0) : (int) ($plan?->manual_trades_per_day ?: 0);
        if ($dailyLimit > 0) {
            $used = PulseTrade::query()->where('user_id', $user->id)->whereDate('created_at', today())
                ->when($automatic, fn ($q) => $q->where('meta->automatic', true), fn ($q) => $q->where(function ($qq) { $qq->whereNull('meta->automatic')->orWhere('meta->automatic', false); }))
                ->count();
            if ($used >= $dailyLimit) {
                throw new RuntimeException('The daily Pulse trade limit has been reached.');
            }
        }

        $lossLimit = abs((float) $settings->daily_loss_limit);
        if ($lossLimit > 0) {
            $todayPnl = (float) PulseTrade::query()
                ->where('user_id', $user->id)
                ->where('status', 'closed')
                ->whereDate('closed_at', today())
                ->sum('realized_pnl');
            if ($todayPnl <= -$lossLimit) {
                throw new RuntimeException('The configured daily loss limit has been reached.');
            }
        }
    }

    private function validateTradeValues(PulsePair $pair, PulseSignal $signal, float $quantity, float $price, float $stopLoss, float $takeProfit): void
    {
        if ($quantity <= 0 || $price <= 0) {
            throw new RuntimeException('Quantity and price must be greater than zero.');
        }
        if ((float) $pair->minimum_quantity > 0 && $quantity < (float) $pair->minimum_quantity) {
            throw new RuntimeException('Quantity is below the exchange minimum for this pair.');
        }
        if ((float) $pair->minimum_notional > 0 && $quantity * $price < (float) $pair->minimum_notional) {
            throw new RuntimeException('Order value is below the exchange minimum notional.');
        }
        if ($signal->direction === 'LONG' && ! ($stopLoss < $price && $takeProfit > $price)) {
            throw new RuntimeException('A LONG setup requires stop loss below entry and take profit above entry.');
        }
        if ($signal->direction === 'SHORT' && ! ($stopLoss > $price && $takeProfit < $price)) {
            throw new RuntimeException('A SHORT setup requires stop loss above entry and take profit below entry.');
        }
    }

    private function resolveQuantity(PulsePair $pair, PulseSignal $signal, PulseUserSetting $settings, array $input): float
    {
        $quantity = (float) ($input['quantity'] ?? 0);
        if ($quantity <= 0 && $settings->sizing_mode === 'fixed_quantity') {
            $quantity = (float) $settings->fixed_quantity;
        }
        if ($quantity <= 0) {
            $notional = (float) ($input['notional'] ?? $settings->fixed_notional ?? 0);
            if ($notional <= 0) {
                throw new RuntimeException('Enter a valid quantity or USDT notional.');
            }
            $quantity = $notional / max((float) $signal->entry_price, 0.00000001);
        }
        return $this->binance->normalizeQuantity($pair, $quantity);
    }

    private function refreshExecutionSnapshot(BinanceConnection $connection): BinanceConnection
    {
        $testedAt = $connection->last_tested_at;
        $permissions = (array) ($connection->permissions ?? []);
        $needsRefresh = ! $testedAt
            || $testedAt->lt(now()->subMinutes(5))
            || ! (bool) data_get($permissions, 'can_trade', false)
            || (float) data_get($permissions, 'account_equity', 0) <= 0
            || (float) data_get($permissions, 'available_balance', 0) <= 0;

        if (! $needsRefresh) return $connection;

        try {
            $result = $this->binance->testConnection($connection);
            $connection->update([
                'last_tested_at' => now(),
                'last_error' => null,
                'permissions' => [
                    'can_trade' => (bool) ($result['can_trade'] ?? false),
                    'account_equity' => (float) ($result['total_wallet_balance'] ?? 0),
                    'available_balance' => (float) ($result['available_balance'] ?? 0),
                ],
            ]);
            return $connection->fresh();
        } catch (\Throwable $e) {
            $connection->update(['last_tested_at' => now(), 'last_error' => $e->getMessage()]);
            throw new RuntimeException('Pulse could not refresh the Binance account check. Review the Binance Connection once and try again.');
        }
    }

    private function connectionFor(User $user, string $environment): BinanceConnection
    {
        $connection = BinanceConnection::query()->where('user_id', $user->id)->where('environment', $environment)->where('is_active', true)->first();
        if (! $connection) {
            throw new RuntimeException("No active Binance {$environment} connection is configured.");
        }
        return $connection;
    }

    private function findPosition(BinanceConnection $connection, PulseTrade $trade): array
    {
        return collect($this->binance->positionRisk($connection, $trade->symbol))->first(function (array $row) use ($trade): bool {
            return strtoupper((string) ($row['symbol'] ?? '')) === strtoupper($trade->symbol)
                && ($trade->exchange_position_side === 'BOTH' || strtoupper((string) ($row['positionSide'] ?? 'BOTH')) === $trade->exchange_position_side);
        }) ?: [];
    }

    private function notify(PulseTrade $trade, string $title, string $message, string $severity, string $type = 'trade'): void
    {
        $alert = PulseAlert::create([
            'user_id' => $trade->user_id, 'type' => $type, 'title' => $title, 'message' => $message,
            'severity' => $severity, 'action_url' => route('pulse.trades.show', $trade),
            'data' => ['trade_id' => $trade->id, 'environment' => $trade->environment],
        ]);
        $user = $trade->relationLoaded('user') ? $trade->user : User::query()->with('pulseSettings')->find($trade->user_id);
        if ($user) {
            if ($type === 'risk') $this->mail->pulseAlert($user, $alert);
            else $this->mail->tradeAlert($user, $alert);
        }
    }

    private function defaultSettings(): array
    {
        return [
            'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false,
            'emergency_stop' => false, 'default_leverage' => 3, 'margin_type' => 'ISOLATED', 'position_mode' => 'BOTH',
            'risk_per_trade_percent' => 1, 'sizing_mode' => 'fixed_notional', 'fixed_notional' => 25,
            'minimum_signal_score' => 70, 'default_order_type' => 'MARKET', 'take_profit_percent' => 2,
            'stop_loss_percent' => 1, 'daily_loss_limit' => 0, 'max_open_positions' => 2,
            'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
            'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
        ];
    }

    private function safeExchangePayload(array $payload): array
    {
        return collect($payload)->except(['apiKey', 'signature'])->all();
    }
}
