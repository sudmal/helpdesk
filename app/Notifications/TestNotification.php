<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TestNotification extends Notification
{
    public bool $isTest = true;

    public function __construct(private string $userName, private string $channel) {}

    public function via($notifiable): array
    {
        return match ($this->channel) {
            'telegram' => [TelegramChannel::class],
            'max'      => [MaxChannel::class],
            default    => ['mail'],
        };
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔔 Тест уведомлений — HelpDesk')
            ->greeting("Привет, {$this->userName}!")
            ->line('Это тестовое уведомление. Канал **Email** работает корректно.');
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chat_id'    => $notifiable->telegram_chat_id,
            'text'       => "👋 Привет, *{$this->userName}*!\n\nЭто тестовое уведомление — канал Telegram работает корректно.",
            'parse_mode' => 'Markdown',
        ];
    }

    public function toMax($notifiable): array
    {
        return [
            'text'   => "👋 Привет, **{$this->userName}**!\n\nЭто тестовое уведомление — канал Max работает корректно.",
            'format' => 'markdown',
        ];
    }
}
