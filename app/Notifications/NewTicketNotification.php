<?php

namespace App\Notifications;

use App\Models\Ticket;
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

    // Отправляем в Telegram и Push
    public static function dispatch(Ticket $ticket): void
    {
        // Уведомляем только если заявка запланирована на сегодня
        $schedDate = $ticket->scheduled_at?->toDateString();
        if ($schedDate && $schedDate !== now()->toDateString()) {
            return;
        }

        // Telegram
        try {
            $telegram = app(TelegramService::class);
            $telegram->broadcast(
                $telegram->formatNewTicket($ticket),
                $ticket->address?->territory_id
            );
        } catch (\Throwable $e) {
            \Log::error('Telegram notification failed: '.$e->getMessage());
        }

        // MAX
        try {
            $max = app(MaxService::class);
            $max->broadcast(
                $max->formatNewTicket($ticket),
                $ticket->address?->territory_id
            );
        } catch (\Throwable $e) {
            \Log::error('MAX notification failed: '.$e->getMessage());
        }

        // Push — только пользователям у которых есть эта территория
        try {
            $territoryId = $ticket->address?->territory_id;
            $users = \App\Models\User::whereHas('pushSubscriptions')
                ->when($territoryId, fn($q) => $q->whereHas('territories', fn($t) => $t->where('territories.id', $territoryId)))
                ->get();
            foreach ($users as $user) {
                $user->notify(new static($ticket));
            }
        } catch (\Throwable $e) {
            \Log::error('Push notification failed: '.$e->getMessage());
        }
    }
}
