<?php

use Illuminate\Support\Facades\Schedule;

// Утренняя сводка и вечерний отчёт — запускаются каждую минуту, время задаётся в настройках
Schedule::command('helpdesk:daily-summary --scheduled')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('helpdesk:evening-report --scheduled')->everyMinute()->withoutOverlapping()->runInBackground();

// Автозакрытие просроченных — каждую ночь в 03:00
Schedule::command('helpdesk:close-overdue --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('helpdesk:morning-report --scheduled')->everyMinute()->withoutOverlapping()->runInBackground();
