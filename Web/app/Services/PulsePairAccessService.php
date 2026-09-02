<?php

namespace App\Services;

use App\Models\PulsePair;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PulsePairAccessService
{
    public function allowedPairs(User $user): Collection
    {
        $plan = $user->pulsePlan();
        $query = PulsePair::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('symbol');

        if (! $plan || ($plan->pair_access_mode ?: 'all') === 'all') {
            return $query->get();
        }

        $ids = $plan->pairs()->wherePivot('is_enabled', true)->pluck('pulse_pairs.id');
        if ($ids->isEmpty()) {
            return new Collection();
        }

        return $query->whereIn('id', $ids)->get();
    }

    public function allowedSymbols(User $user): array
    {
        return $this->allowedPairs($user)->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol))->values()->all();
    }

    public function isAllowed(User $user, string $symbol): bool
    {
        return in_array(strtoupper($symbol), $this->allowedSymbols($user), true);
    }

    public function catalog(User $user, array $selected = []): array
    {
        $selected = array_values(array_unique(array_map('strtoupper', $selected)));
        $plan = $user->pulsePlan();
        $pairs = $this->allowedPairs($user);

        return [
            'plan' => $plan?->only(['id','name','slug','max_selected_pairs','pair_access_mode','minimum_signal_score']),
            'limit' => max(1, (int) ($plan?->max_selected_pairs ?: 5)),
            'total_available' => $pairs->count(),
            'selected_count' => count(array_intersect($selected, $pairs->pluck('symbol')->all())),
            'quote_assets' => $pairs->pluck('quote_asset')->filter()->unique()->sort()->values()->all(),
            'pairs' => $pairs->map(fn (PulsePair $pair) => [
                'id' => $pair->id,
                'symbol' => $pair->symbol,
                'base_asset' => $pair->base_asset,
                'quote_asset' => $pair->quote_asset,
                'selected' => in_array(strtoupper($pair->symbol), $selected, true),
                'price_precision' => $pair->price_precision,
                'quantity_precision' => $pair->quantity_precision,
                'minimum_notional' => $pair->minimum_notional,
                'last_synced_at' => $pair->last_synced_at,
            ])->values(),
        ];
    }
}
