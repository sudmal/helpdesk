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

// Агрегация почасовой статистики звонков:
// -- в 00:05 за вчера (полные данные за истёкшие сутки)
Schedule::command('helpdesk:aggregate-call-stats')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->runInBackground();
// -- каждый час за сегодня (чтобы текущий день отображался в отчёте)
Schedule::command('helpdesk:aggregate-call-stats today')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Отчёты по сменам -- генерирует итог для каждой завершившейся смены
// (раз в 5 минут, идемпотентно: пропускает уже посчитанные)
Schedule::command('helpdesk:generate-shift-reports')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();