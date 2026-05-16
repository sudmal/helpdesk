<?php

namespace App\Notifications;

use App\Models\{Ticket, BrigadeSchedule};
use App\Services\{TelegramService, MaxService};
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewTicketNotification extends Notification
{
    public function __construct(private Ticket $ticket) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $ticket  = $this->ticket;
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? ' кв.'.$ticket->apartment : '';
        $addrStr = $address ? ($address->street.' д.'.$address->building.$aptStr) : '—';

        return (new WebPushMessage)
            ->title('🆕 Новая заявка '.$ticket->number)
            ->body($addrStr."\n".($ticket->description ?? ''))
            ->data(['url' => '/tickets/'.$ticket->id])
            ->tag('ticket-'.$ticket->id);
    }

    public static function dispatch(Ticket $ticket): void
    {
        // Уведомляем только если заявка запланирована на сегодня
        $schedDate = $ticket->scheduled_at?->toDateString();
        if ($schedDate && $schedDate !== now()->toDateString()) {
            return;
        }

        // Получаем членов бригады, которые сегодня работают (нет статуса 'off')
        if (!$ticket->relationLoaded('brigade')) {
            $ticket->load('brigade.members');
        }

        $brigade = $ticket->brigade;
        $workingMembers = collect();

        if ($brigade) {
            $offUserIds = BrigadeSchedule::where('brigade_id', $brigade->id)
                ->whereDate('date', now())
                ->where('status', 'off')
                ->pluck('user_id');

            $workingMembers = $brigade->members()
                ->where('is_active', true)
                ->whereNotIn('id', $offUserIds)
                ->get();
        }

        // Telegram
        try {
            $telegram = app(TelegramService::class);
            $text = $telegram->formatNewTicket($ticket);
            foreach ($workingMembers as $user) {
                if ($user->notify_telegram && $user->telegram_chat_id) {
                    $telegram->send($user->telegram_chat_id, $text);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Telegram notification failed: '.$e->getMessage());
        }

        // MAX
        try {
            $max = app(MaxService::class);
            $text = $max->formatNewTicket($ticket);
            foreach ($workingMembers as $user) {
                if ($user->notify_max && $user->max_chat_id) {
                    $max->send($user->max_chat_id, $text);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('MAX notification failed: '.$e->getMessage());
        }

        // Push — членам бригады с push-подписками
        try {
            $memberIds = $workingMembers->pluck('id');
            $pushUsers = \App\Models\User::whereIn('id', $memberIds)
                ->whereHas('pushSubscriptions')
                ->get();
            foreach ($pushUsers as $user) {
                $user->notify(new static($ticket));
            }
        } catch (\Throwable $e) {
            \Log::error('Push notification failed: '.$e->getMessage());
        }
    }
}