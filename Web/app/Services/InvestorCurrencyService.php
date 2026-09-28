<?php

namespace App\Services;

use App\Models\PortfolioAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controls the investor principal currency.
 *
 * An account currency is freely selectable only before financial history exists.
 * Once capital/performance/statement activity exists it becomes an accounting
 * invariant: changing the label would break the promise that original-currency
 * principal is returned in that same currency.
 */
class InvestorCurrencyService
{
    public function supported(): array
    {
        return array_values(array_unique(array_map(
            static fn ($currency) => strtoupper(trim((string) $currency)),
            config('private_investor.supported_currencies', ['USD'])
        )));
    }

    public function normalize(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, $this->supported(), true)) {
            throw ValidationException::withMessages(['currency' => 'Select a supported investor currency.']);
        }
        return $currency;
    }

    public function canChange(PortfolioAccount $account): bool
    {
        return $this->blockers($account) === [];
    }

    public function blockers(PortfolioAccount $account): array
    {
        $blockers = [];

        if ($account->transactions()->exists()) {
            $blockers[] = 'transaction history';
        }
        if ($account->statements()->exists()) {
            $blockers[] = 'published or draft statements';
        }
        if ($account->requests()->whereIn('type', ['add_investment','withdrawal'])->whereIn('status', ['submitted','under_review','approved'])->exists()) {
            $blockers[] = 'an open capital request';
        }
        $meaningfulPerformance = $account->performancePlans()->with('accruals')->get()->contains(function ($plan): bool {
            if (abs((float) $plan->base_amount) >= 0.005 || abs((float) $plan->target_amount) >= 0.005) return true;
            return $plan->accruals->contains(function ($row): bool {
                return abs((float) ($row->planned_amount ?? 0)) >= 0.005
                    || abs((float) ($row->manual_adjustment ?? 0)) >= 0.005
                    || abs((float) ($row->posted_amount ?? 0)) >= 0.005;
            });
        });
        if ($meaningfulPerformance) {
            $blockers[] = 'non-zero monthly performance history';
        }

        foreach (['opening_value','current_value','net_contributions','total_profit','monthly_profit'] as $field) {
            if (abs((float) $account->{$field}) >= 0.005) {
                $blockers[] = 'non-zero portfolio values';
                break;
            }
        }

        return array_values(array_unique($blockers));
    }

    public function lockMessage(PortfolioAccount $account): string
    {
        $blockers = $this->blockers($account);
        if ($blockers === []) {
            return 'Currency can be changed until the first financial entry is recorded.';
        }

        return 'Principal currency is locked because this account already has '.implode(', ', $blockers).'. This protects the investor’s original-currency principal.';
    }

    /**
     * Change currency only while the account is financially empty.
     */
    public function change(PortfolioAccount $account, string $currency): PortfolioAccount
    {
        $currency = $this->normalize($currency);

        return DB::transaction(function () use ($account, $currency): PortfolioAccount {
            $locked = PortfolioAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $current = strtoupper((string) ($locked->currency ?: 'USD'));
            if ($currency === $current) {
                return $locked;
            }

            $blockers = $this->blockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages([
                    'currency' => 'Principal currency cannot be changed after financial activity starts. '.
                        'The existing '.$current.' principal must remain in '.$current.' so it can be returned in the original currency.',
                ]);
            }

            // All values are zero at this point. Keep USD administration fields explicitly zero
            // so dashboards never mistake an empty non-USD account for missing FX reconciliation.
            $locked->update([
                'currency' => $currency,
                'opening_value_usd' => 0,
                'current_value_usd' => 0,
                'net_contributions_usd' => 0,
                'total_profit_usd' => 0,
                'monthly_profit_usd' => 0,
                'realized_fx_gain_loss_usd' => 0,
            ]);

            return $locked->fresh();
        });
    }
}
