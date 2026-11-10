<?php

use App\Actions\PurgeExpiredMessages;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Disappearing messages whose time is up (also purged on each list request)
Artisan::command('messages:purge-expired', function (PurgeExpiredMessages $purge) {
    $this->info($purge->handle().' message(s) effacé(s).');
})->purpose('Erase the disappearing messages whose time is up');

Schedule::command('messages:purge-expired')->everyFiveMinutes();
