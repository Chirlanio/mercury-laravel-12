<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Store Goals mid-month alert: runs on the 15th of each month at 9:00 AM
Schedule::command('store-goals:midmonth-alert')
    ->monthlyOn(15, '09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/midmonth-alert.log'));
