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
        $sessionLocale = session('app_locale');
        $lang = $sessionLocale ?: settings('localization.language', settings('locale.language', config('app.locale', 'en')));

        if ($lang) {
            config(['app.locale' => $lang]);
            App::setLocale($lang);
            Carbon::setLocale($lang);
        }

        return $next($request);
    }
}
