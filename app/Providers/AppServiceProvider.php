<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LvlGameApiService;
use App\Services\NexusGameApiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LvlGameApiService::class, function ($app) {
            return new LvlGameApiService();
        });

        $this->app->singleton(NexusGameApiService::class, function ($app) {
            return new NexusGameApiService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
