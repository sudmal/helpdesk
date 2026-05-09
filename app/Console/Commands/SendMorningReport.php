<?php

namespace App\Console\Commands;

use App\Models\{Ticket, TicketStatus, User};
use Illuminate\Console\Command;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use Illuminate\Notifications\Notification;

class SendMorningReport extends Command
{
    protected $signature   = 'helpdesk:morning-report';
    protected $description = 'Утренний push-отчёт по заявкам на сегодня';

    public function handle(): void
    {
        $today    = today()->toDateString();
        $openIds  = TicketStatus::where('is_final', false)->pluck('id');

        // Считаем по всем территориям
        $total   = Ticket::whereDate('scheduled_at', $today)->count();
        $open    = Ticket::whereDate('scheduled_at', $today)->whereIn('status_id', $openIds)->count();
        $closed  = $total - $open;
        $overdue = Ticket::whereIn('status_id', $openIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', today())
            ->count();

        $body = "На сегодня: {$total} заявок | Открытых: {$open} | Закрытых: {$closed}";
        if ($overdue > 0) {
            $body .= " | ⚠ Просроченных: {$overdue}";
        }

        // Отправляем всем активным пользователям с подписками
        $users = User::whereHas('pushSubscriptions')->get();

        foreach ($users as $user) {
            $user->notify(new class($body) extends Notification {
                public function __construct(private string $body) {}

                public function via($notifiable): array {
                    return [WebPushChannel::class];
                }

                public function toWebPush($notifiable, $notification): WebPushMessage {
                    return (new WebPushMessage)
                        ->title('📋 Утренний отчёт HelpDesk')
                        ->body($this->body)
                        ->data(['url' => '/'])
                        ->tag('morning-report');
                }
            });
        }

        $this->info("Отправлено {$users->count()} пользователям");
    }
}
