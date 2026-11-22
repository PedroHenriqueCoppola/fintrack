<?php

namespace App\Providers;

use App\Services\ReserveBalanceService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One balance map per request. A process-wide singleton would go stale
        // between requests under `php artisan serve`.
        $this->app->scoped(ReserveBalanceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
