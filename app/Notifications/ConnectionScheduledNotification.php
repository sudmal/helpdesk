<?php

namespace App\Notifications;

use App\Models\{ConnectionRequest, User};
use App\Services\{TelegramService, MaxService};
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Уведомление монтажнику при назначении даты подключения (по аналогии с
 * NewTicketNotification, но без фильтра "работает сегодня" -- дата визита
 * может быть любым будущим днём, а не только сегодняшним, как у тикетов).
 * См. память проекта, project-connection-feasibility.
 */
class ConnectionScheduledNotification extends Notification
{
    public function __construct(private ConnectionRequest $connectionRequest) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $cr   = $this->connectionRequest;
        $when = $cr->scheduled_at?->format('d.m.Y H:i');

        return (new WebPushMessage)
            ->title('📅 Подключение назначено')
            ->body($cr->address_string."\n".$when)
            ->data(['url' => '/connection-requests?search='.urlencode($cr->phone)])
            ->tag('connection-'.$cr->id);
    }

    public static function dispatch(ConnectionRequest $cr): void
    {
        if (!$cr->relationLoaded('brigade')) {
            $cr->load('brigade.members');
        }
        $brigade = $cr->brigade;
        if (!$brigade) return;

        $members = $brigade->members()
            ->where('is_active', true)
            ->whereHas('role', fn($q) => $q->whereIn('slug', ['technician', 'foreman']))
            ->get();

        if ($members->isEmpty()) return;

        $when = $cr->scheduled_at?->format('d.m.Y H:i');
        $text = "📅 Подключение назначено\n{$cr->name}\n{$cr->address_string}\n{$when}";

        try {
            $telegram = app(TelegramService::class);
            foreach ($members as $user) {
                if ($user->notify_telegram && $user->telegram_chat_id) {
                    $telegram->send($user->telegram_chat_id, $text);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Telegram notification (connection scheduled) failed: '.$e->getMessage());
        }

        try {
            $max = app(MaxService::class);
            foreach ($members as $user) {
                if ($user->notify_max && $user->max_chat_id) {
                    $max->send($user->max_chat_id, $text);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('MAX notification (connection scheduled) failed: '.$e->getMessage());
        }

        try {
            $pushUsers = User::whereIn('id', $members->pluck('id'))
                ->whereHas('pushSubscriptions')
                ->get();
            foreach ($pushUsers as $user) {
                $user->notify(new static($cr));
            }
        } catch (\Throwable $e) {
            \Log::error('Push notification (connection scheduled) failed: '.$e->getMessage());
        }
    }
}
