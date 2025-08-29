<?php

use Illuminate\Console\Scheduling\Schedule;

Artisan::command('schedule:custom', function () {
    // This is optional; for manual testing.
})->purpose('Run custom schedule');

return function (Schedule $schedule) {
    $schedule->command('kifurushi:expire')->dailyAt('03:00')
        ->timezone('Africa/Nairobi');
};
