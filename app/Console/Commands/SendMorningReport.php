<?php

namespace App\Console\Commands;

use App\Models\{Ticket, TicketStatus, User};
use App\Services\TelegramService;
use Illuminate\Console\Command;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use Illuminate\Notifications\Notification;

class SendMorningReport extends Command
{
    protected $signature   = 'helpdesk:morning-report {--scheduled : проверять время из настроек}';
    protected $description = 'Утренний push + telegram отчёт по заявкам на сегодня';

    public function handle(TelegramService $telegram): void
    {
        if ($this->option('scheduled')) {
            if (!((bool) \App\Models\SystemSetting::get('daily_summary_enabled', '1'))) return;
            $time = \App\Models\SystemSetting::get('daily_summary_time', '08:00');
            if (now()->format('H:i') !== $time) return;
        }

        $today   = today()->toDateString();
        $openIds = TicketStatus::where('is_final', false)->pluck('id');

        $total   = Ticket::whereDate('scheduled_at', $today)->count();
        $open    = Ticket::whereDate('scheduled_at', $today)->whereIn('status_id', $openIds)->count();
        $closed  = $total - $open;
        $overdue = Ticket::whereIn('status_id', $openIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', today())
            ->count();

        $body = "На сегодня: {$total} заявок | Открытых: {$open} | Закрытых: {$closed}";
        if ($overdue > 0) $body .= " | ⚠ Просроченных: {$overdue}";

        // Push уведомления
        $users = User::whereHas('pushSubscriptions')->get();
        foreach ($users as $user) {
            $user->notify(new class($body) extends Notification {
                public function __construct(private string $body) {}
                public function via($n): array { return [WebPushChannel::class]; }
                public function toWebPush($n, $notification): WebPushMessage {
                    return (new WebPushMessage)
                        ->title('📋 Утренний отчёт HelpDesk')
                        ->body($this->body)
                        ->data(['url' => '/'])
                        ->tag('morning-report');
                }
            });
        }

        // Telegram
        $telegram->broadcast($telegram->formatDailyList());

        $this->info("Push: {$users->count()} | Telegram: отправлено");
    }
}
