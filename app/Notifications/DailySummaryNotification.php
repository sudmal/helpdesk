<?php

namespace App\Notifications;

use App\Models\{Brigade, Ticket};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Brigade    $brigade,
        private Collection $tickets,
        private string     $date,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->notify_email)    $channels[] = 'mail';
        if ($notifiable->notify_telegram) $channels[] = TelegramChannel::class;
        if ($notifiable->notify_max)      $channels[] = MaxChannel::class;
        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("📋 Заявки на {$this->date} — бригада «{$this->brigade->name}»")
            ->greeting("Добрый день, {$notifiable->name}!")
            ->line("Сводка заявок на сегодня для бригады «{$this->brigade->name}»:")
            ->line("Всего заявок: **{$this->tickets->count()}**");

        foreach ($this->tickets as $ticket) {
            $time    = $ticket->scheduled_at?->format('H:i') ?? '—';
            $address = $ticket->address?->full_address ?? 'Адрес не указан';
            $type    = $ticket->type->name;
            $mail->line("• {$time} | {$address} | {$type}");
        }

        return $mail->action('Открыть в системе', route('calendar.index'));
    }

    public function toTelegram(object $notifiable): array
    {
        $lines = ["📋 *Заявки на {$this->date}* — бригада «{$this->brigade->name}»\n"];

        foreach ($this->tickets->sortBy('scheduled_at') as $i => $ticket) {
            $num     = $i + 1;
            $time    = $ticket->scheduled_at?->format('H:i') ?? '—';
            $address = $ticket->address?->full_address ?? 'Адрес не указан';
            $type    = $ticket->type->name;
            $status  = $ticket->status->name;
            $lines[] = "{$num}. ⏰ {$time}\n📍 {$address}\n🔧 {$type} | {$status}\n";
        }

        $lines[] = "Всего: {$this->tickets->count()} заявок";

        return [
            'chat_id' => $notifiable->telegram_chat_id,
            'text'    => implode("\n", $lines),
            'parse_mode' => 'Markdown',
        ];
    }

    public function toMax(object $notifiable): array
    {
        $lines = ["📋 **Заявки на {$this->date}** — бригада «{$this->brigade->name}»\n"];

        foreach ($this->tickets->sortBy('scheduled_at') as $i => $ticket) {
            $num     = $i + 1;
            $time    = $ticket->scheduled_at?->format('H:i') ?? '—';
            $address = $ticket->address?->full_address ?? 'Адрес не указан';
            $type    = $ticket->type->name;
            $status  = $ticket->status->name;
            $lines[] = "{$num}. ⏰ {$time}\n📍 {$address}\n🔧 {$type} | {$status}\n";
        }

        $lines[] = "Всего: {$this->tickets->count()} заявок";

        return [
            'text'   => implode("\n", $lines),
            'format' => 'markdown',
        ];
    }
}
