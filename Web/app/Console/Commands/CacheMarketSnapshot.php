<?php

namespace App\Console\Commands;

use App\Services\MarketDataService;
use Illuminate\Console\Command;

class CacheMarketSnapshot extends Command
{
    protected $signature = 'abs:cache-market';
    protected $description = 'Refresh cached ABS market-provider responses';

    public function handle(MarketDataService $market): int
    {
        $overview = $market->overview(true);
        $movers = $market->movers(5, true);
        $chartReady = true;

        foreach (MarketDataService::CORE_SYMBOLS as $symbol) {
            $chart = $market->chart($symbol, '1h', 80, true);
            $chartReady = $chartReady && (bool) ($chart['is_live'] ?? false);
        }

        $ready = (bool) ($overview['is_live'] ?? false)
            && (bool) ($movers['is_live'] ?? false)
            && $chartReady;

        if (! $ready) {
            $this->warn('Market cache refresh completed, but one or more live providers were unavailable. No substitute values were created.');
            return self::FAILURE;
        }

        $this->info('ABS live market cache refreshed.');
        return self::SUCCESS;
    }
}
