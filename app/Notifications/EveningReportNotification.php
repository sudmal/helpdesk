<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class EveningReportNotification extends Notification
{
    public function __construct(private array $stats, private Collection $tickets) {}

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
        $date = now()->format('d.m.Y');

        return (new MailMessage)
            ->subject("📊 Итоги дня {$date}")
            ->greeting("Добрый вечер, {$notifiable->name}!")
            ->line("**Итоги работы за {$date}:**")
            ->line("✅ Закрыто заявок: {$this->stats['closed']}")
            ->line("🔄 Осталось открытых: {$this->stats['open']}")
            ->line("🆕 Создано новых: {$this->stats['created']}")
            ->line("🚨 Срочных открытых: {$this->stats['urgent']}")
            ->action('Открыть систему', url('/'));
    }

    public function toTelegram(object $notifiable): array
    {
        $date = now()->format('d.m.Y');

        $text = "📊 *Итоги дня {$date}*\n\n"
            . "✅ Закрыто: {$this->stats['closed']}\n"
            . "🔄 Открытых: {$this->stats['open']}\n"
            . "🆕 Создано: {$this->stats['created']}\n"
            . "🚨 Срочных: {$this->stats['urgent']}";

        return [
            'chat_id'    => $notifiable->telegram_chat_id,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];
    }

    public function toMax(object $notifiable): array
    {
        $date = now()->format('d.m.Y');

        $text = "📊 **Итоги дня {$date}**\n\n"
            . "✅ Закрыто: {$this->stats['closed']}\n"
            . "🔄 Открытых: {$this->stats['open']}\n"
            . "🆕 Создано: {$this->stats['created']}\n"
            . "🚨 Срочных: {$this->stats['urgent']}";

        return [
            'text'   => $text,
            'format' => 'markdown',
        ];
    }
}
