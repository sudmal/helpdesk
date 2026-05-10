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

    public function send(string $chatId, string $text, array $keyboard = []): void
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode([
                'keyboard'        => $keyboard,
                'resize_keyboard' => true,
                'persistent'      => true,
            ]);
        }

        Http::post("{$this->apiUrl}/sendMessage", $params);
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

    // Главная клавиатура
    private function mainKeyboard(): array
    {
        return [
            ['📋 Заявки на сегодня', '📊 Статистика'],
            ['🌐 Интернет', '📺 КТВ', '📡 ВОЛС'],
            ['❌ Отписаться'],
        ];
    }

    // Форматируем список заявок 
    public function formatDailyList(?int $serviceTypeId = null): string
    {
        $today = today()->toDateString();

        $query = Ticket::with(['address', 'serviceType', 'status'])
            ->whereDate('scheduled_at', $today)
            ->whereHas('status', fn($q) => $q->where('is_final', false))
            ->orderBy('scheduled_at');

        if ($serviceTypeId) $query->where('service_type_id', $serviceTypeId);

        $tickets = $query->get();

        $date = now()->format('d.m.Y');

        if ($tickets->isEmpty()) {
            return "📋 <u>Заявки на {$date}</u>\n" .
                   "Заявок нет";
        }

        $lines  = "📋 <u>Заявки на {$date} ({$tickets->count()})</u>\n";

        foreach ($tickets as $t) {
            $address = $t->address;
            $aptStr  = $t->apartment ? " {$t->apartment}" : '';
            $street  = $address ? mb_substr($address->street, 0, 15) : '—';
            $bld     = $address ? "д.{$address->building}{$aptStr}" : '';
            $time    = $t->scheduled_at ? Carbon::parse($t->scheduled_at)->format('H:i') : '--:--';
            $phone   = $t->phone ?? '—';
            $desc    = mb_substr($t->description ?? '—', 0, 255);

            $lines .= "<blockquote>{$t->number} {$time} ";
            $lines .= "  {$street} {$bld} ";
            $lines .= "  {$phone} ";
            $lines .= "  {$desc}</blockquote>\n\n";
        }

        return rtrim($lines);
    }

    // Статистика
    public function formatStats(): string
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

        $text  = "<code>📊 Статистика " . now()->format('d.m.Y') . "\n";
        $text .= "─────────────────────\n";
        $text .= "На сегодня:  {$total}\n";
        $text .= "Открытых:    {$open}\n";
        $text .= "Закрытых:    {$closed}\n";
        if ($overdue > 0) {
            $text .= "⚠ Просроч:   {$overdue}\n";
        }
        $text .= "─────────────────────\n";

        // По участкам
        $byType = Ticket::whereDate('scheduled_at', $today)
            ->with('serviceType')
            ->selectRaw('service_type_id, COUNT(*) as cnt')
            ->groupBy('service_type_id')
            ->get();

        foreach ($byType as $row) {
            $name = mb_substr($row->serviceType?->name ?? '—', 0, 10);
            $text .= str_pad($name, 12) . "{$row->cnt}\n";
        }

        return rtrim($text) . "</code>";
    }

    // Новая заявка (моноширинный)
    public function formatNewTicket(Ticket $ticket): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';
        $url     = config('app.url') . "/tickets/{$ticket->id}";

        return "<code>🆕 НОВАЯ ЗАЯВКА\n" .
               "─────────────────────────────\n" .
               "№ {$ticket->number}\n" .
               "⏰ {$time} | {$ticket->serviceType?->name}\n" .
               "📍 {$addrStr}\n" .
               "📞 {$ticket->phone}\n" .
               "💬 " . mb_substr($ticket->description ?? '—', 0, 80) . "\n" .
               "─────────────────────────────</code>\n" .
               "🔗 <a href=\"{$url}\">{$ticket->number}</a>";
    }

    // Отменена заявка (моноширинный)
    public function formatCancelledTicket(Ticket $ticket, ?string $reason = null): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';

        $text  = "<code>❌ ОТМЕНЕНА\n";
        $text .= "─────────────────────────────\n";
        $text .= "№ {$ticket->number}\n";
        $text .= "⏰ {$time} | {$ticket->serviceType?->name}\n";
        $text .= "📍 {$addrStr}\n";
        $text .= "📞 {$ticket->phone}\n";
        if ($reason) {
            $text .= "📝 " . mb_substr($reason, 0, 80) . "\n";
        }
        $text .= "─────────────────────────────</code>";

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
            str_starts_with($text, '/start'),
            $text === '🌐 Все участки'          => $this->handleStart($chatId, $username, $text),
            $text === '/stop',
            $text === '❌ Отписаться'            => $this->handleStop($chatId),
            $text === '/today',
            $text === '📋 Заявки на сегодня'    => $this->send($chatId, $this->formatDailyList()),
            $text === '📊 Статистика'            => $this->send($chatId, $this->formatStats()),
            $text === '🌐 Интернет'              => $this->handleFilteredList($chatId, 'Интернет'),
            $text === '📺 КТВ'                   => $this->handleFilteredList($chatId, 'КТВ'),
            $text === '📡 ВОЛС'                  => $this->handleFilteredList($chatId, 'ВОЛС'),
            $text === '/help'                    => $this->handleHelp($chatId),
            default                              => null,
        };
    }

    private function handleStart(string $chatId, string $username, string $text): void
    {
        $parts         = explode(' ', $text, 2);
        $serviceTypeId = null;

        if (isset($parts[1]) && $parts[1] !== 'Все участки') {
            $st = \App\Models\ServiceType::where('name', 'like', '%' . trim($parts[1]) . '%')->first();
            $serviceTypeId = $st?->id;
        }

        $this->subscribe($chatId, $username, $serviceTypeId);

        $filter = $serviceTypeId ? " (участок: {$parts[1]})" : " (все участки)";
        $this->send($chatId,
            "✅ <b>Подписка оформлена{$filter}</b>\n\n" .
            "Используйте кнопки ниже 👇",
            $this->mainKeyboard()
        );
    }

    private function handleStop(string $chatId): void
    {
        $this->unsubscribe($chatId);
        $this->send($chatId,
            "❌ Отписались от уведомлений.\nНапишите /start чтобы подписаться снова.",
            [['▶️ Подписаться снова']]
        );
    }

    private function handleFilteredList(string $chatId, string $typeName): void
    {
        $st = \App\Models\ServiceType::where('name', 'like', "%{$typeName}%")->first();
        $this->send($chatId, $this->formatDailyList($st?->id));
    }

    private function handleHelp(string $chatId): void
    {
        $this->send($chatId,
            "📖 <b>HelpDesk бот</b>\n\n" .
            "Используйте кнопки на клавиатуре или команды:\n\n" .
            "/start — подписаться\n" .
            "/today — заявки на сегодня\n" .
            "/stop — отписаться",
            $this->mainKeyboard()
        );
    }
}
