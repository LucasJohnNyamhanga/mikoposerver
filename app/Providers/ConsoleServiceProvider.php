<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Run the kifurushi:expire command every day at midnight
            $schedule->command('kifurushi:expire')
                ->dailyAt('03:00')
                ->timezone('Africa/Nairobi');

        });
    }
}
