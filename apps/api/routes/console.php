<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('social:sync')->everyMinute();
Schedule::command('messages:dispatch-scheduled')->everyMinute();
Schedule::command('campaigns:dispatch-recipients')->everyMinute();
