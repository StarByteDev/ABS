<?php

namespace App\Services;

use App\Models\PulsePointLedger;
use App\Models\PulsePointWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PulsePointService
{
    public function wallet(User $user): PulsePointWallet
    {
        return PulsePointWallet::query()->firstOrCreate(['user_id' => $user->id], [
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_spent' => 0,
        ]);
    }

    public function balance(User $user): int
    {
        return (int) $this->wallet($user)->balance;
    }

    public function credit(
        User $user,
        int $points,
        string $source,
        string $idempotencyKey,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = [],
    ): PulsePointLedger {
        if ($points <= 0) {
            throw new RuntimeException('Pulse Sparks credit must be greater than zero.');
        }

        return $this->apply($user, $points, 'credit', $source, $idempotencyKey, $description, $referenceType, $referenceId, $meta);
    }

    public function debit(
        User $user,
        int $points,
        string $source,
        string $idempotencyKey,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = [],
    ): PulsePointLedger {
        if ($points <= 0) {
            throw new RuntimeException('Pulse Sparks debit must be greater than zero.');
        }

        return $this->apply($user, -$points, 'debit', $source, $idempotencyKey, $description, $referenceType, $referenceId, $meta);
    }

    public function adminAdjust(User $user, int $amount, string $idempotencyKey, string $description, array $meta = []): PulsePointLedger
    {
        if ($amount === 0) {
            throw new RuntimeException('Pulse Sparks adjustment cannot be zero.');
        }

        return $this->apply(
            $user,
            $amount,
            $amount > 0 ? 'credit' : 'debit',
            'admin_adjustment',
            $idempotencyKey,
            $description,
            'User',
            $user->id,
            $meta,
        );
    }

    private function apply(
        User $user,
        int $signedAmount,
        string $type,
        string $source,
        string $idempotencyKey,
        ?string $description,
        ?string $referenceType,
        ?int $referenceId,
        array $meta,
    ): PulsePointLedger {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new RuntimeException('Pulse Sparks transaction requires an idempotency key.');
        }

        return DB::transaction(function () use ($user, $signedAmount, $type, $source, $idempotencyKey, $description, $referenceType, $referenceId, $meta): PulsePointLedger {
            // Create the wallet row without a race, then lock it before checking
            // idempotency. This serializes concurrent Spark activity for one user and
            // prevents same-key retries from observing stale balance/ledger state.
            DB::table('pulse_point_wallets')->insertOrIgnore([
                'user_id' => $user->id,
                'balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_spent' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var PulsePointWallet $wallet */
            $wallet = PulsePointWallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $existing = PulsePointLedger::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id || (int) $existing->amount !== $signedAmount) {
                    throw new RuntimeException('Pulse Sparks idempotency key is already associated with a different transaction.');
                }
                return $existing;
            }

            $newBalance = (int) $wallet->balance + $signedAmount;
            if ($newBalance < 0) {
                throw new RuntimeException('Not enough Pulse Sparks for this action.');
            }

            $wallet->balance = $newBalance;
            if ($signedAmount > 0) {
                $wallet->lifetime_earned = (int) $wallet->lifetime_earned + $signedAmount;
            } else {
                $wallet->lifetime_spent = (int) $wallet->lifetime_spent + abs($signedAmount);
            }
            $wallet->save();

            return PulsePointLedger::create([
                'user_id' => $user->id,
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'type' => $type,
                'source' => $source,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        }, 5);
    }
}
