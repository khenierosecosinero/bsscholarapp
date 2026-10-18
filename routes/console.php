<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    app(\App\Services\AccountAccessService::class)->releaseExpiredSuspensions();
})->everyFifteenMinutes()->name('release-expired-scholar-suspensions');

Schedule::command('events:complete-expired')
    ->everyMinute()
    ->name('complete-expired-confirmed-events')
    ->withoutOverlapping();
