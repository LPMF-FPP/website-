<?php

namespace App\Providers;

use App\Events\NumberIssued;
use App\Listeners\SendIssueNotification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\NumberingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply runtime locale/timezone from settings (also for CLI/queue)
        try {
            $tz = settings('locale.timezone', config('app.timezone', 'UTC'));
            $lg = settings('locale.language', config('app.locale', 'en'));
            if ($tz) {
                config(['app.timezone' => $tz]);
                @date_default_timezone_set($tz);
            }
            if ($lg) {
                app()->setLocale($lg);
                \Carbon\Carbon::setLocale($lg);
            }
        } catch (\Throwable $e) {
            // ignore during early install or if settings table missing
        }

        Event::listen(NumberIssued::class, SendIssueNotification::class);

        Gate::define('manage-settings', function ($user) {
            // Allow admin and supervisor by default
            if (in_array($user->role ?? null, ['admin', 'supervisor'], true)) {
                return true;
            }
            // Check settings for additional roles
            $allowed = settings('security.roles.can_manage_settings', []);
            return in_array($user->role ?? null, $allowed, true);
        });

        Gate::define('issue-number', function ($user) {
            // Allow admin by default so preview/issue works out of the box
            $allowed = settings('security.roles.can_issue_number', ['admin']);
            return in_array($user->role ?? null, $allowed, true);
        });
    }
}
