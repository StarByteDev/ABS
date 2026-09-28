<?php

namespace App\Services;

use App\Models\PortfolioAccount;
use App\Models\PortfolioTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestorPortfolioAccountingService
{
    public function create(PortfolioAccount $account, array $data, ?int $adminId, bool $postNow = true): PortfolioTransaction
    {
        return DB::transaction(function () use ($account, $data, $adminId, $postNow): PortfolioTransaction {
            $lockedAccount = PortfolioAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $prepared = $this->prepare($lockedAccount, $data);
            $transaction = $lockedAccount->transactions()->create(array_merge($data, $prepared['local'], $prepared['usd'], [
                'currency' => $prepared['currency'],
                'fx_rate_to_usd' => $prepared['rate'],
                // V15.7.2 keeps usd_amount as the settlement/conversion amount for backward compatibility.
                'usd_amount' => $prepared['settlement_usd_amount'],
                'settlement_usd_amount' => $prepared['settlement_usd_amount'],
                'principal_usd_basis' => $prepared['principal_usd_basis'],
                'fx_gain_loss_usd' => $prepared['fx_gain_loss_usd'],
                'fx_source' => $data['fx_source'] ?? ($prepared['currency'] === 'USD' ? 'native_usd' : 'admin_locked'),
                'fx_locked_at' => now(),
                'status' => $postNow ? 'posted' : 'draft',
                'created_by' => $adminId,
                'posted_at' => $postNow ? now() : null,
            ]));
            if ($postNow) {
                $this->applyEffects($lockedAccount, $prepared['local'], $prepared['usd'], $prepared['fx_gain_loss_usd'], (string) $data['transaction_date']);
            }
            return $transaction->fresh();
        });
    }

    public function postDraft(PortfolioTransaction $transaction): PortfolioTransaction
    {
        if ($transaction->status !== 'draft') {
            throw ValidationException::withMessages(['transaction' => 'Only draft entries can be posted.']);
        }

        return DB::transaction(function () use ($transaction): PortfolioTransaction {
            $lockedTransaction = PortfolioTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($lockedTransaction->status !== 'draft') {
                throw ValidationException::withMessages(['transaction' => 'This entry is no longer a draft.']);
            }
            $account = PortfolioAccount::query()->whereKey($lockedTransaction->portfolio_account_id)->lockForUpdate()->firstOrFail();
            $prepared = $this->prepare($account, [
                'type' => $lockedTransaction->type,
                'amount' => (float) $lockedTransaction->amount,
                'currency' => $lockedTransaction->currency ?: $account->currency,
                'fx_rate_to_usd' => $lockedTransaction->fx_rate_to_usd,
            ]);
            $lockedTransaction->update(array_merge($prepared['local'], $prepared['usd'], [
                'currency' => $prepared['currency'],
                'fx_rate_to_usd' => $prepared['rate'],
                'usd_amount' => $prepared['settlement_usd_amount'],
                'settlement_usd_amount' => $prepared['settlement_usd_amount'],
                'principal_usd_basis' => $prepared['principal_usd_basis'],
                'fx_gain_loss_usd' => $prepared['fx_gain_loss_usd'],
                'fx_source' => $lockedTransaction->fx_source ?: ($prepared['currency'] === 'USD' ? 'native_usd' : 'admin_locked'),
                'fx_locked_at' => $lockedTransaction->fx_locked_at ?: now(),
                'status' => 'posted',
                'posted_at' => now(),
            ]));
            $this->applyEffects($account, $prepared['local'], $prepared['usd'], $prepared['fx_gain_loss_usd'], $lockedTransaction->transaction_date?->toDateString() ?: now()->toDateString());
            return $lockedTransaction->fresh();
        });
    }

    public function void(PortfolioTransaction $transaction, int $adminId, string $reason): PortfolioTransaction
    {
        if ($transaction->status !== 'posted') {
            throw ValidationException::withMessages(['transaction' => 'Only posted entries can be voided.']);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['void_reason' => 'Enter a clear correction reason.']);
        }

        return DB::transaction(function () use ($transaction, $adminId, $reason): PortfolioTransaction {
            $lockedTransaction = PortfolioTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($lockedTransaction->status !== 'posted') {
                throw ValidationException::withMessages(['transaction' => 'This entry is no longer posted.']);
            }
            $account = PortfolioAccount::query()->whereKey($lockedTransaction->portfolio_account_id)->lockForUpdate()->firstOrFail();
            $this->applyEffects(
                $account,
                $this->inverseLocal($lockedTransaction),
                $this->inverseUsd($lockedTransaction),
                -1 * (float) ($lockedTransaction->fx_gain_loss_usd ?? 0),
                now()->toDateString()
            );
            $lockedTransaction->update(['status' => 'voided', 'voided_at' => now(), 'voided_by' => $adminId, 'void_reason' => $reason]);
            return $lockedTransaction->fresh();
        });
    }

    public function updateEntry(PortfolioTransaction $transaction, array $data): PortfolioTransaction
    {
        if ($transaction->status === 'voided') {
            throw ValidationException::withMessages(['transaction' => 'A corrected entry cannot be edited. Delete it if it was created by mistake, or create a new entry.']);
        }

        return DB::transaction(function () use ($transaction, $data): PortfolioTransaction {
            $lockedTransaction = PortfolioTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($lockedTransaction->status === 'voided') {
                throw ValidationException::withMessages(['transaction' => 'A corrected entry cannot be edited.']);
            }
            $account = PortfolioAccount::query()->whereKey($lockedTransaction->portfolio_account_id)->lockForUpdate()->firstOrFail();
            $wasPosted = $lockedTransaction->status === 'posted';
            $oldLocal = $this->localFromTransaction($lockedTransaction);
            $oldUsd = $this->usdFromTransaction($lockedTransaction);
            $oldFx = (float) ($lockedTransaction->fx_gain_loss_usd ?? 0);

            // For an edited posted withdrawal, calculate the new principal basis as if the old
            // transaction had first been reversed. This prevents the edit from consuming its own basis.
            $basisState = null;
            if ($wasPosted) {
                $basisState = [
                    'local_principal' => max(0, (float) $account->net_contributions - (float) $oldLocal['net_contributions_effect']),
                    'usd_principal' => max(0, (float) ($account->net_contributions_usd ?? 0) - (float) $oldUsd['usd_net_contributions_effect']),
                ];
            }
            $prepared = $this->prepare($account, $data, $basisState);

            $lockedTransaction->update(array_merge($data, $prepared['local'], $prepared['usd'], [
                'currency' => $prepared['currency'],
                'fx_rate_to_usd' => $prepared['rate'],
                'usd_amount' => $prepared['settlement_usd_amount'],
                'settlement_usd_amount' => $prepared['settlement_usd_amount'],
                'principal_usd_basis' => $prepared['principal_usd_basis'],
                'fx_gain_loss_usd' => $prepared['fx_gain_loss_usd'],
                'fx_source' => $data['fx_source'] ?? ($prepared['currency'] === 'USD' ? 'native_usd' : 'admin_locked'),
                'fx_locked_at' => now(),
            ]));

            if ($wasPosted) {
                $this->applyEffects(
                    $account,
                    $this->delta($prepared['local'], $oldLocal),
                    $this->delta($prepared['usd'], $oldUsd),
                    $prepared['fx_gain_loss_usd'] - $oldFx,
                    $lockedTransaction->transaction_date?->toDateString() ?: now()->toDateString()
                );
            }
            return $lockedTransaction->fresh();
        });
    }

    public function deleteEntry(PortfolioTransaction $transaction): array
    {
        return DB::transaction(function () use ($transaction): array {
            $lockedTransaction = PortfolioTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $account = PortfolioAccount::query()->whereKey($lockedTransaction->portfolio_account_id)->lockForUpdate()->first();
            $snapshot = $lockedTransaction->toArray();
            $snapshot['account_user_id'] = $account?->user_id;
            $snapshot['currency'] = $lockedTransaction->currency ?: $account?->currency;

            if ($lockedTransaction->status === 'posted' && $account) {
                $this->applyEffects(
                    $account,
                    $this->inverseLocal($lockedTransaction),
                    $this->inverseUsd($lockedTransaction),
                    -1 * (float) ($lockedTransaction->fx_gain_loss_usd ?? 0),
                    now()->toDateString()
                );
            }
            $lockedTransaction->delete();
            return $snapshot;
        });
    }

    public function deleteDraft(PortfolioTransaction $transaction): void
    {
        if ($transaction->status !== 'draft') {
            throw ValidationException::withMessages(['transaction' => 'Posted financial entries cannot be deleted with the draft-only action.']);
        }
        $transaction->delete();
    }

    public function effects(string $type, float $amount, string $prefix = ''): array
    {
        $amount = round($amount, 2);
        $base = match ($type) {
            'deposit' => ['current_value_effect' => $amount, 'net_contributions_effect' => $amount, 'profit_effect' => 0, 'monthly_profit_effect' => 0],
            'withdrawal' => ['current_value_effect' => -$amount, 'net_contributions_effect' => -$amount, 'profit_effect' => 0, 'monthly_profit_effect' => 0],
            'profit' => ['current_value_effect' => 0, 'net_contributions_effect' => 0, 'profit_effect' => $amount, 'monthly_profit_effect' => $amount],
            'loss', 'fee' => ['current_value_effect' => -$amount, 'net_contributions_effect' => 0, 'profit_effect' => -$amount, 'monthly_profit_effect' => -$amount],
            'adjustment' => ['current_value_effect' => $amount, 'net_contributions_effect' => 0, 'profit_effect' => 0, 'monthly_profit_effect' => 0],
            default => throw ValidationException::withMessages(['type' => 'Unsupported transaction type.']),
        };
        if ($prefix === '') return $base;
        return collect($base)->mapWithKeys(fn ($value, $key) => [$prefix.$key => $value])->all();
    }

    /**
     * Investor capital is always denominated in the account currency. FX never changes the
     * investor's principal. Admin USD carrying basis uses weighted-average historical cost.
     */
    private function prepare(PortfolioAccount $account, array $data, ?array $basisState = null): array
    {
        $currency = strtoupper((string) ($data['currency'] ?? $account->currency ?? 'USD'));
        if ($currency !== strtoupper((string) $account->currency)) {
            throw ValidationException::withMessages(['currency' => 'Transactions must use the investor principal currency.']);
        }
        if (! in_array($currency, config('private_investor.supported_currencies', ['USD']), true)) {
            throw ValidationException::withMessages(['currency' => 'Unsupported investor currency.']);
        }

        $rate = $currency === 'USD' ? 1.0 : (float) ($data['fx_rate_to_usd'] ?? 0);
        if ($rate <= 0) {
            throw ValidationException::withMessages(['fx_rate_to_usd' => 'Enter the locked USD conversion rate for this transaction.']);
        }

        $type = (string) $data['type'];
        $amount = (float) $data['amount'];
        // In V15.7.4, Profit is an external investor payout. It contributes to published P/L
        // but never compounds into investor capital unless a separate Investment entry is posted.
        $settlementUsd = round($amount * $rate, 2);
        $local = $this->effects($type, $amount);
        $principalBasis = null;
        $fxGainLoss = 0.0;

        if ($type === 'withdrawal') {
            $localPrincipal = (float) ($basisState['local_principal'] ?? $account->net_contributions ?? 0);
            $usdPrincipal = (float) ($basisState['usd_principal'] ?? ($account->net_contributions_usd ?? ($currency === 'USD' ? $localPrincipal : 0)));
            if ($amount > $localPrincipal + 0.005) {
                throw ValidationException::withMessages([
                    'amount' => 'Capital withdrawal cannot exceed the remaining investor principal of '.$currency.' '.number_format($localPrincipal, 2).'.',
                ]);
            }
            if ($localPrincipal <= 0 || $usdPrincipal < 0) {
                throw ValidationException::withMessages(['amount' => 'No investor principal is available for a capital withdrawal.']);
            }

            // Weighted-average historical USD cost basis. A full withdrawal consumes every remaining
            // cent of basis to avoid a rounding residue.
            $principalBasis = abs($amount - $localPrincipal) < 0.005
                ? round($usdPrincipal, 2)
                : round($amount * ($usdPrincipal / $localPrincipal), 2);

            $usd = [
                'usd_current_value_effect' => -$principalBasis,
                'usd_net_contributions_effect' => -$principalBasis,
                'usd_profit_effect' => 0,
                'usd_monthly_profit_effect' => 0,
            ];
            $fxGainLoss = round($principalBasis - $settlementUsd, 2);
        } else {
            $usd = $this->effects($type, $settlementUsd, 'usd_');
            if ($type === 'deposit') {
                $principalBasis = $settlementUsd;
            }
        }

        return [
            'currency' => $currency,
            'rate' => $rate,
            'settlement_usd_amount' => $settlementUsd,
            'principal_usd_basis' => $principalBasis,
            'fx_gain_loss_usd' => $currency === 'USD' ? 0.0 : $fxGainLoss,
            'local' => $local,
            'usd' => $usd,
        ];
    }

    private function applyEffects(PortfolioAccount $account, array $local, array $usd, float $fxGainLoss, string $valuationDate): void
    {
        $account->refresh();
        $account->update([
            'current_value' => max(0, round((float) $account->current_value + (float) $local['current_value_effect'], 2)),
            'net_contributions' => max(0, round((float) $account->net_contributions + (float) $local['net_contributions_effect'], 2)),
            'total_profit' => round((float) $account->total_profit + (float) $local['profit_effect'], 2),
            'monthly_profit' => round((float) $account->monthly_profit + (float) $local['monthly_profit_effect'], 2),
            'current_value_usd' => max(0, round((float) ($account->current_value_usd ?? 0) + (float) $usd['usd_current_value_effect'], 2)),
            'net_contributions_usd' => max(0, round((float) ($account->net_contributions_usd ?? 0) + (float) $usd['usd_net_contributions_effect'], 2)),
            'total_profit_usd' => round((float) ($account->total_profit_usd ?? 0) + (float) $usd['usd_profit_effect'], 2),
            'monthly_profit_usd' => round((float) ($account->monthly_profit_usd ?? 0) + (float) $usd['usd_monthly_profit_effect'], 2),
            'realized_fx_gain_loss_usd' => round((float) ($account->realized_fx_gain_loss_usd ?? 0) + $fxGainLoss, 2),
            'valuation_date' => $valuationDate,
        ]);
    }

    private function localFromTransaction(PortfolioTransaction $transaction): array
    {
        return [
            'current_value_effect' => (float) $transaction->current_value_effect,
            'net_contributions_effect' => (float) $transaction->net_contributions_effect,
            'profit_effect' => (float) $transaction->profit_effect,
            'monthly_profit_effect' => (float) $transaction->monthly_profit_effect,
        ];
    }

    private function usdFromTransaction(PortfolioTransaction $transaction): array
    {
        return [
            'usd_current_value_effect' => (float) ($transaction->usd_current_value_effect ?? 0),
            'usd_net_contributions_effect' => (float) ($transaction->usd_net_contributions_effect ?? 0),
            'usd_profit_effect' => (float) ($transaction->usd_profit_effect ?? 0),
            'usd_monthly_profit_effect' => (float) ($transaction->usd_monthly_profit_effect ?? 0),
        ];
    }

    private function inverseLocal(PortfolioTransaction $transaction): array
    {
        return collect($this->localFromTransaction($transaction))->map(fn ($v) => -1 * $v)->all();
    }

    private function inverseUsd(PortfolioTransaction $transaction): array
    {
        return collect($this->usdFromTransaction($transaction))->map(fn ($v) => -1 * $v)->all();
    }

    private function delta(array $new, array $old): array
    {
        $out = [];
        foreach ($new as $key => $value) $out[$key] = (float) $value - (float) ($old[$key] ?? 0);
        return $out;
    }
}
