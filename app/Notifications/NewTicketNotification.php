<?php

namespace App\Notifications;

use App\Models\Ticket;
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
}
