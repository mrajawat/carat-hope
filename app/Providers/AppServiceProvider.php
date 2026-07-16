<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Shipping\Providers\ShipmentProviderInterface::class,
            \App\Shipping\Providers\ManualShipmentProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public', function (Request $request) {
            return app()->environment('local', 'testing')
                ? Limit::none()
                : Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('sensitive', function (Request $request) {
            return app()->environment('local', 'testing')
                ? Limit::none()
                : Limit::perMinute(5)->by($request->ip());
        });
    }
}
