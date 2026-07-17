<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Утренняя сводка и вечерний отчёт — время берётся из настроек БД
        $schedule->command('helpdesk:daily-summary --scheduled')->everyMinute()->withoutOverlapping();
        $schedule->command('helpdesk:evening-report --scheduled')->everyMinute()->withoutOverlapping();
        $schedule->command('helpdesk:aggregate-call-stats')->dailyAt('00:05');
        $schedule->command('helpdesk:check-health')->everyFifteenMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
