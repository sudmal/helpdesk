<?php

use Illuminate\Support\Facades\Schedule;

// Утренняя сводка — 08:00 каждый день
Schedule::command('helpdesk:daily-summary')->dailyAt('08:00');

// Вечерний отчёт — 20:00 каждый день
Schedule::command('helpdesk:evening-report')->dailyAt('20:00');

// Автозакрытие просроченных — каждую ночь в 03:00
Schedule::command('helpdesk:close-overdue --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Утренний отчёт в 8:00
Schedule::command('helpdesk:morning-report')->dailyAt('08:00');
