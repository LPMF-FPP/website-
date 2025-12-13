<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ApplyLocaleFromSettings
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $tz = settings('locale.timezone', config('app.timezone', 'UTC'));
        $sessionLocale = session('app_locale');
        $lang = $sessionLocale ?: settings('locale.language', config('app.locale', 'en'));

        if ($tz) {
            config(['app.timezone' => $tz]);
            @date_default_timezone_set($tz);
        }

        if ($lang) {
            config(['app.locale' => $lang]);
            App::setLocale($lang);
            Carbon::setLocale($lang);
        }

        return $next($request);
    }
}
