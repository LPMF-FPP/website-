<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('search', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ? 'u:' . $request->user()->getAuthIdentifier()
                : 'ip:' . $request->ip();

            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('document-template-preview', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ? 'tpl-preview:user:' . $request->user()->getAuthIdentifier()
                : 'tpl-preview:ip:' . $request->ip();

            $limit = max(1, config('document-templates.preview_rate_limit', 10));

            return Limit::perMinute($limit)->by($key);
        });
    }
}
