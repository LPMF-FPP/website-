<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('inventory:check-alerts')->dailyAt('08:00');
Schedule::command('reminders:send')->everyMinute();
Schedule::command('qmh:fallback:expire')->hourly();
Schedule::command('qmh:action-items:refresh-overdue')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
Schedule::command('lims:purge-old-files')->dailyAt('02:00');
Schedule::command('documents:integrity-check --warn-threshold=1 --ratio-threshold=5 --sample=5')
    ->dailyAt('02:15')
    ->withoutOverlapping();

// Consolidated Reports Auto-Generation
Schedule::command('reports:generate-consolidated')
    ->dailyAt('06:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();
