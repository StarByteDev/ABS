<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use App\Models\EconomicEvent;
use App\Services\PulseMacroReleaseService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings may be added here.
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        // Fire only when an EXISTING economic event gains its first actual.
        // No historical backfill or repeated pushes on subsequent syncs.
        EconomicEvent::created(static function (EconomicEvent $event): void {
            app(PulseMacroReleaseService::class)->queueIfFreshAlreadyReleased($event);
        });
        EconomicEvent::updated(static function (EconomicEvent $event): void {
            app(PulseMacroReleaseService::class)->queueIfNewActual($event);
        });
    }
}
