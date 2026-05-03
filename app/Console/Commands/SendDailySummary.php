<?php

namespace App\Console\Commands;

use App\Models\{Brigade, User, Ticket};
use App\Notifications\DailySummaryNotification;
use Illuminate\Console\Command;

class SendDailySummary extends Command
{
    protected $signature   = 'helpdesk:daily-summary {--date= : дата в формате Y-m-d}';
    protected $description = 'Утренняя сводка по заявкам на сегодня — отправляется бригадирам';

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->toDateString();

        $brigades = Brigade::with(['foreman', 'territories'])->get();

        foreach ($brigades as $brigade) {
            if (!$brigade->foreman) continue;

            $tickets = Ticket::with(['address', 'type', 'status', 'assignee'])
                ->whereDate('scheduled_at', $date)
                ->where('brigade_id', $brigade->id)
                ->get();

            if ($tickets->isEmpty()) continue;

            $brigade->foreman->notify(
                new DailySummaryNotification($brigade, $tickets, $date)
            );

            $this->info("Отправлено бригадиру: {$brigade->foreman->name} ({$tickets->count()} заявок)");
        }

        return Command::SUCCESS;
    }
}
