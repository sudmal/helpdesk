<?php

namespace App\Console\Commands;

use App\Models\{Role, Ticket, TicketStatus, User};
use App\Notifications\EveningReportNotification;
use Illuminate\Console\Command;

class SendEveningReport extends Command
{
    protected $signature   = 'helpdesk:evening-report {--date= : дата Y-m-d}';
    protected $description = 'Вечерний отчёт по итогам дня — отправляется руководителям ТП';

    public function handle(): int
    {
        $date = $this->option('date') ?? today()->toDateString();

        $finalStatuses = TicketStatus::where('is_final', true)->pluck('id');
        $openStatuses  = TicketStatus::where('is_final', false)->pluck('id');

        $stats = [
            'closed'  => Ticket::whereIn('status_id', $finalStatuses)
                               ->whereDate('closed_at', $date)->count(),
            'created' => Ticket::whereDate('created_at', $date)->count(),
            'open'    => Ticket::whereIn('status_id', $openStatuses)->count(),
            'urgent'  => Ticket::whereIn('status_id', $openStatuses)
                               ->whereIn('priority', ['high', 'urgent'])->count(),
        ];

        $tickets = Ticket::with(['address', 'type', 'status', 'brigade'])
            ->whereIn('status_id', $finalStatuses)
            ->whereDate('closed_at', $date)
            ->get();

        // Получатели: Админ + Начальник ТП
        $recipients = User::whereHas('role', fn($q) =>
            $q->whereIn('slug', ['admin', 'head_support'])
        )->where('is_active', true)->get();

        foreach ($recipients as $user) {
            $user->notify(new EveningReportNotification($stats, $tickets));
            $this->info("Отправлено: {$user->name}");
        }

        $this->table(
            ['Показатель', 'Значение'],
            collect($stats)->map(fn($v, $k) => [$k, $v])->values()
        );

        return Command::SUCCESS;
    }
}
