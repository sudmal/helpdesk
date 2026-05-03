<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\{Http, Log};
use App\Models\NotificationsLog;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toTelegram')) return;
        if (empty($notifiable->telegram_chat_id)) return;

        $payload = $notification->toTelegram($notifiable);

        try {
            $response = Http::post(
                'https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMessage',
                $payload
            );

            NotificationsLog::create([
                'user_id' => $notifiable->id,
                'channel' => 'telegram',
                'type'    => class_basename($notification),
                'payload' => $payload['text'] ?? '',
                'success' => $response->successful(),
                'error'   => $response->successful() ? null : $response->body(),
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage());
        }
    }
}
