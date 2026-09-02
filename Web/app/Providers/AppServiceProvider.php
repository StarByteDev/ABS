<?php

namespace App\Providers;

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
    }
}
