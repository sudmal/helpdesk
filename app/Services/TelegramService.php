<?php

namespace App\Services;

use App\Models\{Ticket, TicketStatus, User, ServiceType};
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

    public function broadcast(string $text, ?int $territoryId = null): void
    {
        User::whereNotNull('telegram_chat_id')
            ->where('is_active', true)
            ->whereHas('brigades') // только члены бригад
            ->when($territoryId, fn($q) => $q->whereHas('territories', fn($t) => $t->where('territories.id', $territoryId)))
            ->get()
            ->each(fn($user) => $this->send($user->telegram_chat_id, $text));
    }

    private function findUser(string $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->where('is_active', true)->first();
    }

    private function mainKeyboard(): array
    {
        return [
            ['📋 Заявки на сегодня', '📊 Статистика'],
            ['🌐 Интернет', '📺 КТВ', '📡 ВОЛС'],
        ];
    }

    public function formatDailyList(?int $serviceTypeId = null, array $territoryIds = []): string
    {
        $today = today()->toDateString();

        $query = Ticket::with(['address', 'serviceType', 'status'])
            ->whereDate('scheduled_at', $today)
            ->whereHas('status', fn($q) => $q->where('is_final', false))
            ->whereHas('address', fn($a) => $a->whereIn('territory_id', $territoryIds))
            ->orderBy('scheduled_at');

        if ($serviceTypeId) $query->where('service_type_id', $serviceTypeId);

        $tickets = $query->get();
        $date    = now()->format('d.m.Y');

        if ($tickets->isEmpty()) {
            return "📋 <u>Заявки на {$date}</u>\nЗаявок нет";
        }

        $lines = "📋 <u>Заявки на {$date} ({$tickets->count()})</u>\n";

        foreach ($tickets as $t) {
            $address = $t->address;
            $aptStr  = $t->apartment ? " кв.{$t->apartment}" : '';
            $street  = $address ? $address->street : '—';
            $bld     = $address ? "д.{$address->building}{$aptStr}" : '';
            $time    = $t->scheduled_at ? Carbon::parse($t->scheduled_at)->format('H:i') : '--:--';
            $phone   = $t->phone ?? '—';
            $desc    = str_replace(["\n", "\r"], ' ', $t->description ?? '—');

            $lines .= "<blockquote>{$t->number} {$time} {$street} {$bld} {$phone} {$desc}</blockquote>\n\n";
        }

        return rtrim($lines);
    }

    public function formatStats(array $territoryIds = []): string
    {
        $today   = today()->toDateString();
        $openIds = TicketStatus::where('is_final', false)->pluck('id');
        $base    = fn($q) => $q->whereDate('scheduled_at', $today)
            ->whereHas('address', fn($a) => $a->whereIn('territory_id', $territoryIds));
        $total   = Ticket::query()->tap($base)->count();
        $open    = Ticket::query()->tap($base)->whereIn('status_id', $openIds)->count();
        $closed  = $total - $open;
        $overdue = Ticket::whereIn('status_id', $openIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', today())
            ->whereHas('address', fn($a) => $a->whereIn('territory_id', $territoryIds))
            ->count();

        $text  = "📊 <u>Статистика " . now()->format('d.m.Y') . "</u>\n\n";
        $text .= "На сегодня: <b>{$total}</b>\n";
        $text .= "Открытых: <b>{$open}</b>\n";
        $text .= "Закрытых: <b>{$closed}</b>\n";
        if ($overdue > 0) $text .= "⚠ Просроченных: <b>{$overdue}</b>\n";
        $text .= "\n";

        Ticket::whereDate('scheduled_at', $today)
            ->whereHas('address', fn($a) => $a->whereIn('territory_id', $territoryIds))
            ->with('serviceType')
            ->selectRaw('service_type_id, COUNT(*) as cnt')
            ->groupBy('service_type_id')
            ->get()
            ->each(fn($r) => $text .= "• {$r->serviceType?->name}: <b>{$r->cnt}</b>\n");

        return rtrim($text);
    }

    public function formatNewTicket(Ticket $ticket): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';
        $url     = config('app.url') . "/tickets/{$ticket->id}";
        $desc    = str_replace(["\n", "\r"], ' ', $ticket->description ?? '—');

        return "🆕 <u>Новая заявка</u>\n" .
               "<blockquote>{$ticket->number} {$time} | {$ticket->serviceType?->name}\n" .
               "{$addrStr}\n" .
               "{$ticket->phone}\n" .
               "{$desc}</blockquote>\n" .
               "🔗 <a href=\"{$url}\">{$ticket->number}</a>";
    }

    public function formatCancelledTicket(Ticket $ticket, ?string $reason = null): string
    {
        $address = $ticket->address;
        $aptStr  = $ticket->apartment ? " кв.{$ticket->apartment}" : '';
        $addrStr = $address ? "{$address->street} д.{$address->building}{$aptStr}" : '—';
        $time    = $ticket->scheduled_at ? Carbon::parse($ticket->scheduled_at)->format('H:i d.m') : '—';
        $desc    = str_replace(["\n", "\r"], ' ', $reason ?? '—');

        return "❌ <u>Отменена заявка</u>\n" .
               "<blockquote>{$ticket->number} {$time} | {$ticket->serviceType?->name}\n" .
               "{$addrStr}\n" .
               "{$ticket->phone}\n" .
               "Причина: {$desc}</blockquote>";
    }

    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chatId = (string) $message['chat']['id'];
        $text   = trim($message['text'] ?? '');
        $user   = $this->findUser($chatId);

        if (!$user) {
            $this->send($chatId,
                "⛔ <b>Доступ запрещён</b>\n\n" .
                "Ваш Telegram ID не привязан к системе.\n" .
                "Обратитесь к администратору.\n\n" .
                "Ваш ID: <code>{$chatId}</code>"
            );
            return;
        }

        $tIds = $user->territories()->pluck('territories.id')->toArray();

        match (true) {
            str_starts_with($text, '/start')  => $this->send($chatId, "✅ <b>{$user->name}</b>, добро пожаловать!", $this->mainKeyboard()),
            $text === '/today',
            $text === '📋 Заявки на сегодня'  => $this->send($chatId, $this->formatDailyList(null, $tIds), $this->mainKeyboard()),
            $text === '📊 Статистика'         => $this->send($chatId, $this->formatStats($tIds), $this->mainKeyboard()),
            $text === '🌐 Интернет'           => $this->send($chatId, $this->formatDailyList(ServiceType::where('name', 'like', '%Интернет%')->value('id'), $tIds), $this->mainKeyboard()),
            $text === '📺 КТВ'                => $this->send($chatId, $this->formatDailyList(ServiceType::where('name', 'like', '%КТВ%')->value('id'), $tIds), $this->mainKeyboard()),
            $text === '📡 ВОЛС'               => $this->send($chatId, $this->formatDailyList(ServiceType::where('name', 'like', '%ВОЛС%')->value('id'), $tIds), $this->mainKeyboard()),
            $text === '/help'                 => $this->send($chatId, "📖 <b>HelpDesk</b>\n👤 {$user->name}\n\n/today — заявки на сегодня\n/help — справка", $this->mainKeyboard()),
            default                           => $this->send($chatId, "Используйте кнопки 👇", $this->mainKeyboard()),
        };
    }
}
