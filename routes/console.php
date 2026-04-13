<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Movements sync: incremental every 5 minutes, full daily at 06:00
Schedule::command('movements:sync today')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/movements-sync.log'));

Schedule::command('movements:sync auto')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/movements-sync.log'));

// Store Goals mid-month alert: runs on the 15th of each month at 9:00 AM
Schedule::command('store-goals:midmonth-alert')
    ->monthlyOn(15, '09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/midmonth-alert.log'));

// Experience Tracker notifications: daily at 08:00
Schedule::command('experience:notify')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/experience-notifications.log'));

// Helpdesk SLA monitoring: every 10 minutes (warnings 2h before breach + breach notifications)
Schedule::command('helpdesk:sla-monitor')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/helpdesk-sla.log'));
