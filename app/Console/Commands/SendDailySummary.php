<?php

namespace App\Console\Commands;

use App\Models\{Brigade, BrigadeSchedule, User, Ticket};
use App\Notifications\DailySummaryNotification;
use Illuminate\Console\Command;

class SendDailySummary extends Command
{
    protected $signature   = 'helpdesk:daily-summary {--date= : дата в формате Y-m-d} {--scheduled : проверять время и флаг из настроек}';
    protected $description = 'Утренняя сводка по заявкам на сегодня — отправляется всем работающим членам бригады';

    public function handle(): int
    {
        if ($this->option('scheduled')) {
            if (!((bool) \App\Models\SystemSetting::get('daily_summary_enabled', '1'))) return Command::SUCCESS;
            $time = \App\Models\SystemSetting::get('daily_summary_time', '08:00');
            if (now()->format('H:i') !== $time) return Command::SUCCESS;
        }
        $date = $this->option('date') ?? now()->toDateString();

        $brigades = Brigade::with(['members', 'territories'])->get();

        foreach ($brigades as $brigade) {
            $tickets = Ticket::with(['address', 'type', 'status', 'assignee'])
                ->whereDate('scheduled_at', $date)
                ->where('brigade_id', $brigade->id)
                ->get();

            $offUserIds = BrigadeSchedule::where('brigade_id', $brigade->id)
                ->whereDate('date', $date)
                ->where('status', 'off')
                ->pluck('user_id');

            $members = $brigade->members()
                ->where('is_active', true)
                ->whereNotIn('id', $offUserIds)
                ->get();

            if ($members->isEmpty()) {
                $this->info("Бригада «{$brigade->name}» — нет работающих участников");
                continue;
            }

            foreach ($members as $member) {
                $channels = [];
                if ($member->notify_email)    $channels[] = 'email';
                if ($member->notify_telegram) $channels[] = 'telegram';
                if ($member->notify_max)      $channels[] = 'max';
                if (empty($channels))          $channels[] = 'mail(fallback)';

                $this->info("Бригада «{$brigade->name}» → {$member->name} | заявок: {$tickets->count()} | каналы: " . implode(',', $channels));

                $member->notify(new DailySummaryNotification($brigade, $tickets, $date));
            }
        }

        return Command::SUCCESS;
    }
}