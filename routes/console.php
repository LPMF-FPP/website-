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
Schedule::command('lims:google-drive-sync-documents')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
Schedule::command('lims:google-drive-health')
    ->hourly()
    ->withoutOverlapping();
Schedule::command('storage:cleanup-duplicates --dry-run')
    ->dailyAt('02:20')
    ->withoutOverlapping();
Schedule::command('storage:cleanup-investigators --dry-run')
    ->dailyAt('02:35')
    ->withoutOverlapping();
Schedule::command('storage:cleanup-duplicates --force')
    ->weeklyOn(0, '03:20')
    ->withoutOverlapping()
    ->runInBackground();
Schedule::command('storage:cleanup-investigators --force')
    ->weeklyOn(0, '03:40')
    ->withoutOverlapping()
    ->runInBackground();

// Consolidated Reports Auto-Generation
Schedule::command('reports:generate-consolidated')
    ->dailyAt('06:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();
