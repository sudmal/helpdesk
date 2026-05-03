<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Утренняя сводка бригадирам — 08:00
        $schedule->command('helpdesk:daily-summary')
                 ->dailyAt('08:00')
                 ->withoutOverlapping()
                 ->onOneServer();

        // Вечерний отчёт руководителям — 20:00
        $schedule->command('helpdesk:evening-report')
                 ->dailyAt('20:00')
                 ->withoutOverlapping()
                 ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
