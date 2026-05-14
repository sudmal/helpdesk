<?php

namespace App\Services;

use App\Models\{Ticket, TicketStatus, User, ServiceType};
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MaxService
{
    private string $token;
    private string $apiUrl = 'https://platform-api.max.ru/messages';

    public function __construct()
    {
        $this->token = config('services.max.token');
    }

    public function send(string $userId, string $text): void
    {
        Http::withHeaders([
            'Authorization' => $this->token,
        ])->post($this->apiUrl . '?user_id=' . $userId, [
            'text'   => $text,
            'format' => 'markdown',
        ]);
    }

    public function broadcast(string $text, ?int $territoryId = null): void
    {
        User::whereNotNull('max_chat_id')
            ->where('notify_max', true)
            ->where('is_active', true)
            ->whereHas('brigades')
            ->when($territoryId, fn($q) => $q->whereHas('territories',
                fn($t) => $t->where('territories.id', $territoryId)
            ))
            ->get()
            ->each(fn($user) => $this->send($user->max_chat_id, $text));
    }

    public function formatNewTicket(Ticket $ticket): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';
        $url     = config('app.url') . '/tickets/' . $ticket->id;
        $desc    = str_replace(["\n", "\r"], ' ', $ticket->description ?? '—');

        return "🆕 **Новая заявка**\n"
            . "> {$ticket->number} {$time} | {$ticket->serviceType?->name}\n"
            . "> {$addrStr}\n"
            . "> {$ticket->phone}\n"
            . "> {$desc}\n"
            . "[{$ticket->number}]({$url})";
    }

    public function formatCancelledTicket(Ticket $ticket, ?string $reason = null): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';
        $desc    = str_replace(["\n", "\r"], ' ', $reason ?? '—');

        return "❌ **Отменена заявка**\n"
            . "> {$ticket->number} {$time} | {$ticket->serviceType?->name}\n"
            . "> {$addrStr}\n"
            . "> Причина: {$desc}";
    }
}
