<?php

namespace App\Console;

use App\Console\Commands\Live\StreamStatusCheck;
use App\Console\Commands\Sync\MediaWithS3;
use App\Console\Commands\Sync\AssetsWithS3;
use App\Console\Commands\Metrics\DeployLogs;
use App\Console\Commands\removeTokensExpired;
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
        $schedule->command(DeployLogs::class)->everyTenMinutes();
        $schedule->command(removeTokensExpired::class)->everyTenMinutes();
        $schedule->command(MediaWithS3::class)->everyFiveMinutes();
        $schedule->command(AssetsWithS3::class)->everyFiveMinutes();
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
