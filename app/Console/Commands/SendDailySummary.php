<?php

namespace App\Console\Commands;

use App\Models\{Brigade, User, Ticket};
use App\Notifications\DailySummaryNotification;
use Illuminate\Console\Command;

class SendDailySummary extends Command
{
    protected $signature   = 'helpdesk:daily-summary {--date= : дата в формате Y-m-d} {--scheduled : проверять время и флаг из настроек}';
    protected $description = 'Утренняя сводка по заявкам на сегодня — отправляется бригадирам';

    public function handle(): int
    {
        if ($this->option('scheduled')) {
            if (!((bool) \App\Models\SystemSetting::get('daily_summary_enabled', '1'))) return Command::SUCCESS;
            $time = \App\Models\SystemSetting::get('daily_summary_time', '08:00');
            if (now()->format('H:i') !== $time) return Command::SUCCESS;
        }
        $date = $this->option('date') ?? now()->toDateString();

        $brigades = Brigade::with(['foreman', 'territories'])->get();

        foreach ($brigades as $brigade) {
            if (!$brigade->foreman) continue;

            $tickets = Ticket::with(['address', 'type', 'status', 'assignee'])
                ->whereDate('scheduled_at', $date)
                ->where('brigade_id', $brigade->id)
                ->get();

            $foreman  = $brigade->foreman;
            $channels = [];
            if ($foreman->notify_email)    $channels[] = 'email';
            if ($foreman->notify_telegram) $channels[] = 'telegram';
            if ($foreman->notify_max)      $channels[] = 'max';
            if (empty($channels))          $channels[] = 'mail(fallback)';

            $this->info("Бригада «{$brigade->name}» → {$foreman->name} | заявок: {$tickets->count()} | каналы: " . implode(',', $channels));

            $foreman->notify(new DailySummaryNotification($brigade, $tickets, $date));
        }

        return Command::SUCCESS;
    }
}
