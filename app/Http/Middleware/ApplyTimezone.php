<?php

namespace App\Http\Middleware;

use App\Support\AppTimezone;
use Closure;
use Illuminate\Http\Request;

class ApplyTimezone
{
    public function handle(Request $request, Closure $next)
    {
        $tz = settings('localization.timezone', settings('locale.timezone', config('app.timezone', 'UTC')));
        AppTimezone::apply(is_string($tz) && $tz !== '' ? $tz : null);

        return $next($request);
    }
}
