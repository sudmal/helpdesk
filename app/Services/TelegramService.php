<?php

namespace App\Services;

use App\Models\{Ticket, TicketStatus, SystemSetting};
use Illuminate\Support\Facades\Http;

class TelegramService
{
    private string $token;
    private string $apiUrl;

    public function __construct()
    {
        $this->token  = config('services.telegram.token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    // Отправить сообщение одному chat_id
    public function send(string $chatId, string $text): void
    {
        Http::post("{$this->apiUrl}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    // Отправить всем подписанным пользователям
    public function broadcast(string $text, ?int $serviceTypeId = null): void
    {
        $subscribers = $this->getSubscribers($serviceTypeId);
        foreach ($subscribers as $chatId) {
            $this->send($chatId, $text);
        }
    }

    // Получить подписчиков (с фильтром по участку если нужно)
    public function getSubscribers(?int $serviceTypeId = null): array
    {
        $setting = SystemSetting::get('telegram_subscribers', '[]');
        $all     = json_decode($setting, true) ?? [];

        if (!$serviceTypeId) return array_column($all, 'chat_id');

        return array_column(
            array_filter($all, fn($s) => empty($s['service_type_id']) || $s['service_type_id'] == $serviceTypeId),
            'chat_id'
        );
    }

    // Добавить подписчика
    public function subscribe(string $chatId, string $username, ?int $serviceTypeId = null): void
    {
        $setting     = SystemSetting::get('telegram_subscribers', '[]');
        $subscribers = json_decode($setting, true) ?? [];

        // Удаляем старую запись если есть
        $subscribers = array_filter($subscribers, fn($s) => $s['chat_id'] !== $chatId);
        $subscribers = array_values($subscribers);

        $subscribers[] = [
            'chat_id'         => $chatId,
            'username'        => $username,
            'service_type_id' => $serviceTypeId,
            'subscribed_at'   => now()->toDateTimeString(),
        ];

        SystemSetting::set('telegram_subscribers', json_encode($subscribers));
    }

    // Отписать
    public function unsubscribe(string $chatId): void
    {
        $setting     = SystemSetting::get('telegram_subscribers', '[]');
        $subscribers = json_decode($setting, true) ?? [];
        $subscribers = array_values(array_filter($subscribers, fn($s) => $s['chat_id'] !== $chatId));
        SystemSetting::set('telegram_subscribers', json_encode($subscribers));
    }

    // Форматируем новую заявку
    public function formatNewTicket(Ticket $ticket): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $url     = config('app.url') . "/tickets/{$ticket->id}";

        return "🆕 <b>Новая заявка {$ticket->number}</b>\n" .
               "📍 {$addrStr}\n" .
               "📋 {$ticket->type?->name} | {$ticket->serviceType?->name}\n" .
               "📞 {$ticket->phone}\n" .
               "🕐 " . \Carbon\Carbon::parse($ticket->scheduled_at)->format('d.m.Y H:i') . "\n" .
               "💬 " . ($ticket->description ?? '—') . "\n\n" .
               "🔗 <a href=\"{$url}\">Открыть заявку</a>";
    }

    // Утренняя сводка
    public function formatMorningSummary(): string
    {
        $today   = today()->toDateString();
        $openIds = TicketStatus::where('is_final', false)->pluck('id');

        $total   = Ticket::whereDate('scheduled_at', $today)->count();
        $open    = Ticket::whereDate('scheduled_at', $today)->whereIn('status_id', $openIds)->count();
        $closed  = $total - $open;
        $overdue = Ticket::whereIn('status_id', $openIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', today())
            ->count();

        $text = "📋 <b>Утренняя сводка " . now()->format('d.m.Y') . "</b>\n\n" .
                "На сегодня: <b>{$total}</b> заявок\n" .
                "✅ Открытых: <b>{$open}</b>\n" .
                "✔️ Закрытых: <b>{$closed}</b>\n";

        if ($overdue > 0) {
            $text .= "⚠️ Просроченных: <b>{$overdue}</b>\n";
        }

        // По участкам
        $byType = Ticket::whereDate('scheduled_at', $today)
            ->with('serviceType')
            ->selectRaw('service_type_id, COUNT(*) as cnt')
            ->groupBy('service_type_id')
            ->get();

        if ($byType->count() > 1) {
            $text .= "\n<b>По участкам:</b>\n";
            foreach ($byType as $row) {
                $text .= "• {$row->serviceType?->name}: {$row->cnt}\n";
            }
        }

        return $text;
    }

    // Сводка по запросу
    public function formatStatusSummary(): string
    {
        return $this->formatMorningSummary();
    }

    // Обрабатываем входящее сообщение от пользователя
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
            $text === '/status'               => $this->handleStatus($chatId),
            $text === '/help'                 => $this->handleHelp($chatId),
            default                           => $this->send($chatId, "Неизвестная команда. Напишите /help"),
        };
    }

    private function handleStart(string $chatId, string $username, string $text): void
    {
        // /start или /start Интернет
        $parts         = explode(' ', $text, 2);
        $serviceTypeId = null;

        if (isset($parts[1])) {
            $st = \App\Models\ServiceType::where('name', 'like', '%' . trim($parts[1]) . '%')->first();
            $serviceTypeId = $st?->id;
        }

        $this->subscribe($chatId, $username, $serviceTypeId);

        $filter = $serviceTypeId ? " (только участок: {$parts[1]})" : " (все участки)";
        $this->send($chatId,
            "✅ <b>Подписка оформлена{$filter}</b>\n\n" .
            "Вы будете получать уведомления о новых заявках.\n\n" .
            "Команды:\n" .
            "/status — текущая сводка\n" .
            "/stop — отписаться\n" .
            "/help — помощь"
        );
    }

    private function handleStop(string $chatId): void
    {
        $this->unsubscribe($chatId);
        $this->send($chatId, "❌ Вы отписались от уведомлений.\nЧтобы подписаться снова — напишите /start");
    }

    private function handleStatus(string $chatId): void
    {
        $this->send($chatId, $this->formatStatusSummary());
    }

    private function handleHelp(string $chatId): void
    {
        $this->send($chatId,
            "📖 <b>Команды бота HelpDesk</b>\n\n" .
            "/start — подписаться на все уведомления\n" .
            "/start Интернет — только заявки по Интернету\n" .
            "/start КТВ — только заявки по КТВ\n" .
            "/status — сводка по заявкам прямо сейчас\n" .
            "/stop — отписаться\n" .
            "/help — эта справка"
        );
    }
}
