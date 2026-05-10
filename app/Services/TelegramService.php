<?php

namespace App\Services;

use App\Models\{Ticket, TicketStatus, SystemSetting};
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TelegramService
{
    private string $token;
    private string $apiUrl;

    public function __construct()
    {
        $this->token  = config('services.telegram.token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function send(string $chatId, string $text): void
    {
        Http::post("{$this->apiUrl}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    public function broadcast(string $text, ?int $serviceTypeId = null): void
    {
        foreach ($this->getSubscribers($serviceTypeId) as $chatId) {
            $this->send($chatId, $text);
        }
    }

    public function getSubscribers(?int $serviceTypeId = null): array
    {
        $all = json_decode(SystemSetting::get('telegram_subscribers', '[]'), true) ?? [];
        if (!$serviceTypeId) return array_column($all, 'chat_id');
        return array_column(
            array_filter($all, fn($s) => empty($s['service_type_id']) || $s['service_type_id'] == $serviceTypeId),
            'chat_id'
        );
    }

    public function subscribe(string $chatId, string $username, ?int $serviceTypeId = null): void
    {
        $subscribers = json_decode(SystemSetting::get('telegram_subscribers', '[]'), true) ?? [];
        $subscribers = array_values(array_filter($subscribers, fn($s) => $s['chat_id'] !== $chatId));
        $subscribers[] = [
            'chat_id'         => $chatId,
            'username'        => $username,
            'service_type_id' => $serviceTypeId,
            'subscribed_at'   => now()->toDateTimeString(),
        ];
        SystemSetting::set('telegram_subscribers', json_encode($subscribers));
    }

    public function unsubscribe(string $chatId): void
    {
        $subscribers = json_decode(SystemSetting::get('telegram_subscribers', '[]'), true) ?? [];
        SystemSetting::set('telegram_subscribers',
            json_encode(array_values(array_filter($subscribers, fn($s) => $s['chat_id'] !== $chatId)))
        );
    }

    // Форматируем одну заявку
    public function formatTicket(Ticket $ticket, string $prefix = '🆕'): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i') : '—';

        return "{$prefix} <b>{$ticket->number}</b> [{$time}]\n" .
               "📍 {$addrStr}\n" .
               "📞 {$ticket->phone}\n" .
               "💬 " . mb_substr($ticket->description ?? '—', 0, 100);
    }

    // Список заявок на сегодня
    public function formatDailyList(?int $serviceTypeId = null): string
    {
        $today = today()->toDateString();

        $query = Ticket::with(['address', 'type', 'serviceType', 'status'])
            ->whereDate('scheduled_at', $today)
            ->whereHas('status', fn($q) => $q->where('is_final', false))
            ->orderBy('scheduled_at');

        if ($serviceTypeId) {
            $query->where('service_type_id', $serviceTypeId);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            return "📋 <b>Заявки на сегодня " . now()->format('d.m.Y') . "</b>\n\nЗаявок нет";
        }

        $text = "📋 <b>Заявки на сегодня " . now()->format('d.m.Y') . "</b> ({$tickets->count()} шт.)\n\n";

        foreach ($tickets as $t) {
            $address = $t->address;
            $aptStr  = $t->apartment ? " кв.{$t->apartment}" : '';
            $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
            $time    = $t->scheduled_at ? Carbon::parse($t->scheduled_at)->format('H:i') : '—';

            $text .= "▫️ <b>{$t->number}</b> {$time}\n" .
                     "   📍 {$addrStr}\n" .
                     "   📞 {$t->phone}\n" .
                     "   💬 " . mb_substr($t->description ?? '—', 0, 60) . "\n\n";
        }

        return rtrim($text);
    }

    // Новая заявка на сегодня
    public function formatNewTicket(Ticket $ticket): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';
        $url     = config('app.url') . "/tickets/{$ticket->id}";

        return "🆕 <b>Новая заявка {$ticket->number}</b>\n" .
               "🕐 {$time} | {$ticket->serviceType?->name}\n" .
               "📍 {$addrStr}\n" .
               "📞 {$ticket->phone}\n" .
               "💬 " . mb_substr($ticket->description ?? '—', 0, 100) . "\n" .
               "🔗 <a href=\"{$url}\">{$ticket->number}</a>";
    }

    // Отменена заявка
    public function formatCancelledTicket(Ticket $ticket, ?string $reason = null): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';

        $text = "❌ <b>Отменена {$ticket->number}</b>\n" .
                "🕐 {$time} | {$ticket->serviceType?->name}\n" .
                "📍 {$addrStr}\n" .
                "📞 {$ticket->phone}\n";

        if ($reason) {
            $text .= "📝 Причина: {$reason}\n";
        }

        return $text;
    }

    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chatId   = (string) $message['chat']['id'];
        $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'Unknown';
        $text     = trim($message['text'] ?? '');

        match (true) {
            str_starts_with($text, '/start') => $this->handleStart($chatId, $username, $text),
            $text === '/stop'                 => $this->handleStop($chatId),
            $text === '/today'                => $this->send($chatId, $this->formatDailyList()),
            $text === '/status'               => $this->send($chatId, $this->formatDailyList()),
            $text === '/help'                 => $this->handleHelp($chatId),
            default => null,
        };
    }

    private function handleStart(string $chatId, string $username, string $text): void
    {
        $parts         = explode(' ', $text, 2);
        $serviceTypeId = null;

        if (isset($parts[1])) {
            $st = \App\Models\ServiceType::where('name', 'like', '%' . trim($parts[1]) . '%')->first();
            $serviceTypeId = $st?->id;
        }

        $this->subscribe($chatId, $username, $serviceTypeId);

        $filter = $serviceTypeId ? " (участок: {$parts[1]})" : " (все участки)";
        $this->send($chatId,
            "✅ <b>Подписка оформлена{$filter}</b>\n\n" .
            "/today — список заявок на сегодня\n" .
            "/stop — отписаться\n" .
            "/help — помощь"
        );
    }

    private function handleStop(string $chatId): void
    {
        $this->unsubscribe($chatId);
        $this->send($chatId, "❌ Отписались. Напишите /start чтобы подписаться снова.");
    }

    private function handleHelp(string $chatId): void
    {
        $this->send($chatId,
            "📖 <b>HelpDesk бот</b>\n\n" .
            "/start — подписаться (все уведомления)\n" .
            "/start Интернет — только Интернет\n" .
            "/start КТВ — только КТВ\n" .
            "/today — список заявок на сегодня\n" .
            "/stop — отписаться\n\n" .
            "Автоматически приходят:\n" .
            "• Новые заявки на сегодня\n" .
            "• Утренний список в 08:00\n" .
            "• Уведомление об отмене"
        );
    }
}
