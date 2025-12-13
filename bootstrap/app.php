<?php

use App\Console\Commands\PurgeOldFiles;
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
        $middleware->append(\App\Http\Middleware\ApplyLocaleFromSettings::class);
    })
    ->withCommands([
        PurgeOldFiles::class,
    ])
    ->withSchedule(function (): void {
        Schedule::command('lims:purge-old-files')->dailyAt('02:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
