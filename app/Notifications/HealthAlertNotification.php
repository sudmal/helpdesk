<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HealthAlertNotification extends Notification
{
    // MaxChannel шлёт, только если notify_max=1 ИЛИ isTest=true — алерт о
    // здоровье сервера должен долетать независимо от личных настроек
    // уведомлений получателя, а не быть выключаемым тем же тумблером,
    // что и обычные напоминания.
    public bool $isTest = true;

    public function __construct(private array $issues, private bool $resolved = false) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if (!empty($notifiable->email))            $channels[] = 'mail';
        if (!empty($notifiable->telegram_chat_id)) $channels[] = TelegramChannel::class;
        if (!empty($notifiable->max_chat_id))      $channels[] = MaxChannel::class;
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->resolved ? '✅ Здоровье сервера — проблемы устранены' : '🚨 Здоровье сервера — обнаружены проблемы');

        if ($this->resolved) {
            return $mail->greeting('Проблемы устранены')
                ->line('Ранее обнаруженные проблемы с сервером больше не наблюдаются.');
        }

        $mail->greeting('Обнаружены проблемы с сервером');
        foreach ($this->issues as $issue) {
            $mail->line("• {$issue}");
        }
        return $mail->action('Открыть здоровье сервера', url('/settings'));
    }

    public function toTelegram(object $notifiable): array
    {
        return ['chat_id' => $notifiable->telegram_chat_id, 'text' => $this->text(), 'parse_mode' => 'Markdown'];
    }

    public function toMax(object $notifiable): array
    {
        return ['text' => $this->text(), 'format' => 'markdown'];
    }

    private function text(): string
    {
        if ($this->resolved) {
            return "✅ *Здоровье сервера*\n\nРанее обнаруженные проблемы устранены.";
        }

        $lines = collect($this->issues)->map(fn ($i) => "• {$i}")->implode("\n");
        return "🚨 *Здоровье сервера — обнаружены проблемы*\n\n{$lines}";
    }
}
