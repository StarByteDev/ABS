<?php

namespace App\Providers;

use App\Models\PulseAlert;
use App\Services\Push\PushNotificationService;
use Illuminate\Pagination\Paginator;
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

        // Every genuine in-app Pulse alert (trade, risk, signal, support...) is
        // mirrored to the owner's own devices. Never breaks alert creation.
        PulseAlert::created(static function (PulseAlert $alert): void {
            PushNotificationService::safely(fn (PushNotificationService $push) => $push->queueFromAlert($alert));
        });
    }
}
