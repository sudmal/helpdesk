<?php

namespace App\Console\Commands;

use App\Models\{Ticket, TicketStatus};
use Illuminate\Console\Command;
use Carbon\Carbon;

class CloseOverdueTickets extends Command
{
    protected $signature   = 'helpdesk:close-overdue {--days=30 : Закрывать заявки старше N дней}';
    protected $description = 'Автоматически закрывает просроченные заявки старше указанного числа дней';

    public function handle(): void
    {
        $days      = (int) $this->option('days');
        $threshold = Carbon::now()->subDays($days);

        $closedStatus = TicketStatus::where('slug', 'closed')->firstOrFail();
        $openStatuses = TicketStatus::where('is_final', false)->pluck('id');

        $tickets = Ticket::whereIn('status_id', $openStatuses)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $threshold)
            ->whereNull('deleted_at')
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('Просроченных заявок для закрытия нет.');
            return;
        }

        $count = 0;
        foreach ($tickets as $ticket) {
            $ticket->update([
                'status_id'  => $closedStatus->id,
                'closed_at'  => now(),
                'act_number' => $ticket->act_number ?? 'б/а',
                'close_notes'=> 'Закрыто автоматически (просрочено)',
            ]);

            // Запись в историю
            $ticket->history()->create([
                'user_id'   => null,
                'action'    => 'status_changed',
                'field'     => 'Статус',
                'old_value' => 'Открыта',
                'new_value' => 'Закрыта (просрочено)',
            ]);

            $count++;
        }

        $this->info("Закрыто просроченных заявок: {$count}");
    }
}
