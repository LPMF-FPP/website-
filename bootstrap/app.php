<?php

use App\Console\Commands\GoogleDriveHealthCommand;
use App\Console\Commands\GoogleDriveSmokeCommand;
use App\Console\Commands\PurgeOldFiles;
use App\Console\Commands\QmhRefreshActionItemOverdue;
use App\Console\Commands\ReconcileGowaUpdates;
use App\Console\Commands\SyncGoogleDriveDocumentsCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware additions
        $middleware->prepend(\App\Http\Middleware\ApplyTimezone::class);
        $middleware->append(\App\Http\Middleware\ApplyLocaleFromSettings::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
        $middleware->alias([
            'audit.activity' => \App\Http\Middleware\AuditActivity::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'any_permission' => \App\Http\Middleware\CheckAnyPermission::class,
            'action-item.transition' => \App\Http\Middleware\CheckActionItemTransition::class,
            'audit.gowa-update' => \App\Http\Middleware\AuditGowaUpdateRequest::class,
        ]);

        // Trust all proxies for HTTPS handling
        $middleware->trustProxies(at: '*');

        // Exclude WhatsApp webhook and system endpoints from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/whatsapp/webhook',
            'api/system/restart-queue',
            'api/dashboard-stats',
            'api/monitoring/*',
        ]);

        // Replace API middleware to include session support
        // This enables API routes to use session-based authentication with cookies
        $middleware->group('api', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withCommands([
        GoogleDriveHealthCommand::class,
        GoogleDriveSmokeCommand::class,
        PurgeOldFiles::class,
        QmhRefreshActionItemOverdue::class,
        SyncGoogleDriveDocumentsCommand::class,
        ReconcileGowaUpdates::class,
    ])
    ->withSchedule(function (): void {
        Schedule::command('lims:purge-old-files')->dailyAt('02:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && app()->environment('production')) {
                app('sentry')->captureException($e);
            }
        });

        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
        ]);

        $exceptions->throttle(function (Throwable $e) {
            return \Illuminate\Support\Facades\RateLimiter::attempt(
                'error-reporting:'.get_class($e),
                perMinute: 5,
                callback: fn () => true
            );
        });
    })->create();
