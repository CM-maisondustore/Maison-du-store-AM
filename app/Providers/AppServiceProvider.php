<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer(['home', 'quote'], function ($view) {
            $view->with('catalogueVisible', \App\Models\SiteSetting::catalogueVisible());
        });
        \Illuminate\Http\Middleware\TrustProxies::at(config('deployment.trusted_proxies', []));
        if (config('deployment.force_https')) URL::forceScheme('https');
        RateLimiter::for('submissions', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
