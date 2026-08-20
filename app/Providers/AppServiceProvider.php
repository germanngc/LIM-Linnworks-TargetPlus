<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ngrok / reverse proxies forward HTTPS via headers; trust them so route() URLs stay https.
        if (app()->environment('local')) {
            TrustProxies::at('*');
        }

        if (request()->headers->get('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
