<?php

use Illuminate\Console\Scheduling\Schedule;

Artisan::command('schedule:custom', function () {
    // This is optional; for manual testing.
})->purpose('Run custom schedule');

return function (Schedule $schedule) {
    $schedule->command('kifurushi:deactivate-expired')->dailyAt('00:00');
};
