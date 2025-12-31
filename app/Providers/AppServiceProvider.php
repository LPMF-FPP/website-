<?php

namespace App\Providers;

use App\Events\NumberIssued;
use App\Listeners\SendIssueNotification;
use App\Support\AppTimezone;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

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
            AppTimezone::apply();
            $lg = settings('localization.language', settings('locale.language', config('app.locale', 'en')));
            if ($lg) {
                app()->setLocale($lg);
                \Carbon\Carbon::setLocale($lg);
            }
        } catch (\Throwable $e) {
            // ignore during early install or if settings table missing
        }

        Event::listen(NumberIssued::class, SendIssueNotification::class);
        Queue::before(function () {
            AppTimezone::apply();
        });

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
