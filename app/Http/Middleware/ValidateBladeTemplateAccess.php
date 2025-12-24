<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBladeTemplateAccess
{
    /**
     * Handle an incoming request.
     *
     * Additional security layer for Blade template editor
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only allow users with manage-settings permission
        if (!$request->user() || !$request->user()->can('manage-settings')) {
            abort(403, 'Unauthorized access to template editor.');
        }

        // Log template editing activity
        if ($request->isMethod('PUT') || $request->isMethod('POST')) {
            $logData = [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'ip' => $request->ip(),
                'template' => $request->route('template'),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
            ];
            
            // Try audit channel, fallback to default
            try {
                if (config('logging.channels.audit')) {
                    \Log::channel('audit')->info('Blade template edit attempt', $logData);
                } else {
                    \Log::info('Blade template edit attempt', $logData);
                }
            } catch (\Exception $e) {
                \Log::info('Blade template edit attempt', $logData);
            }
        }

        return $next($request);
    }
}
