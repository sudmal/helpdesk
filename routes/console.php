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

// Очистка неопределённых звонков (без адреса из биллинга) старше 2 дней — каждую ночь в 03:30
Schedule::command('helpdesk:prune-calls --days=2')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();
