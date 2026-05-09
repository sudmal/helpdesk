<?php

namespace App\Console\Commands;

// Этот класс используется как Notification для новых заявок
// Вызывается из TicketObserver

use App\Models\{Ticket, User};
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
        $addrStr = $address
            ? ($address->street . ' д.' . $address->building . $aptStr)
            : '—';

        return (new WebPushMessage)
            ->title('🆕 Новая заявка ' . $ticket->number)
            ->body($addrStr . "\n" . ($ticket->description ?? ''))
            ->data(['url' => '/tickets/' . $ticket->id])
            ->tag('ticket-' . $ticket->id);
    }

    public static function sendToRelevantUsers(Ticket $ticket): void
    {
        // Отправляем пользователям у которых есть подписки
        // и которые относятся к нужной территории/участку
        $territoryId  = $ticket->address?->territory_id;
        $serviceTypeId = $ticket->service_type_id;

        $users = User::whereHas('pushSubscriptions')->get()->filter(function ($user) use ($territoryId, $serviceTypeId) {
            // Администраторы получают все уведомления
            if ($user->hasPermission('*')) return true;
            return true; // Пока отправляем всем
        });

        foreach ($users as $user) {
            $user->notify(new static($ticket));
        }
    }
}
