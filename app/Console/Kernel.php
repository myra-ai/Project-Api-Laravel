<?php

namespace App\Console;

use App\Console\Commands\Live\StreamStatusCheck;
use App\Console\Commands\Live\SyncWithS3;
use App\Console\Commands\Metrics\DeployLogs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Spatie\ShortSchedule\ShortSchedule;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(SyncWithS3::class)->everyFiveMinutes();
        $schedule->command(DeployLogs::class)->everyTenMinutes();
    }

    protected function shortSchedule(ShortSchedule $shortSchedule)
    {
        $shortSchedule->command(StreamStatusCheck::class)->everySeconds(5);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
