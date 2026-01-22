<?php

namespace App\Providers;

use App\Events\NumberIssued;
use App\Listeners\SendIssueNotification;
use App\Models\Document;
use App\Models\Permission;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Observers\DocumentObserver;
use App\Observers\SampleObserver;
use App\Observers\TestRequestObserver;
use App\Support\ActivityLogger;
use App\Support\AppTimezone;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        $this->registerQueryMonitoring();

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

        Event::listen(Login::class, function (Login $event): void {
            ActivityLogger::log(
                'USER_LOGIN',
                $event->user?->getKey(),
                $event->user,
                null,
                null,
                ['guard' => $event->guard],
                $event->user?->getKey()
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            ActivityLogger::log(
                'USER_LOGOUT',
                $event->user?->getKey(),
                $event->user,
                null,
                null,
                ['guard' => $event->guard],
                $event->user?->getKey()
            );
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

        Gate::define('manage-users', function ($user) {
            // Allow admin and manajer_teknis by default
            if (in_array($user->role ?? null, ['admin', 'manajer_teknis'], true)) {
                return true;
            }

            $allowed = settings('security.roles.can_manage_users', []);

            return in_array($user->role ?? null, $allowed, true);
        });

        Gate::define('issue-number', function ($user) {
            // Allow admin by default so preview/issue works out of the box
            $allowed = settings('security.roles.can_issue_number', ['admin']);

            return in_array($user->role ?? null, $allowed, true);
        });

        Gate::define('viewPulse', function ($user) {
            // Allow admin and supervisor to view Pulse dashboard
            return in_array($user->role ?? null, ['admin', 'supervisor'], true);
        });

        // Register dynamic permission gates from database
        $this->registerPermissionGates();

        TestRequest::observe(TestRequestObserver::class);
        Sample::observe(SampleObserver::class);
        Document::observe(DocumentObserver::class);
    }

    protected function registerQueryMonitoring(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        \Illuminate\Support\Facades\DB::listen(function ($query) {
            $threshold = config('database.slow_query_threshold_ms', 1000);

            if ($query->time > $threshold) {
                \Illuminate\Support\Facades\Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }

            if ($query->time > ($threshold * 3)) {
                \Illuminate\Support\Facades\Log::error('Critical slow query', [
                    'sql' => $query->sql,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }

    /**
     * Register dynamic permission gates from database.
     */
    protected function registerPermissionGates(): void
    {
        // Skip if permissions table doesn't exist yet (during migrations)
        try {
            if (! Schema::hasTable('permissions')) {
                return;
            }

            // Get all permissions from database
            $permissions = Permission::all();

            foreach ($permissions as $permission) {
                Gate::define($permission->name, function ($user) use ($permission) {
                    /** @var User $user */
                    return $user->hasPermission($permission->name);
                });
            }
        } catch (\Throwable $e) {
            // Ignore errors during early install or if database is not ready
        }
    }
}
