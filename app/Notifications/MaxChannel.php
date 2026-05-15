<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\{Http, Log};
use App\Models\NotificationsLog;

class MaxChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toMax')) return;
        if (empty($notifiable->max_chat_id)) return;
        $isTest = property_exists($notification, 'isTest') && $notification->isTest;
        if (!$isTest && !$notifiable->notify_max) return;

        $payload = $notification->toMax($notifiable);

        try {
            $response = Http::withHeaders([
                'Authorization' => config('services.max.token'),
            ])->post(
                'https://platform-api.max.ru/messages?user_id=' . $notifiable->max_chat_id,
                $payload
            );

            NotificationsLog::create([
                'user_id' => $notifiable->id,
                'channel' => 'max',
                'type'    => class_basename($notification),
                'payload' => $payload['text'] ?? '',
                'success' => $response->successful(),
                'error'   => $response->successful() ? null : $response->body(),
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('MAX notification failed: ' . $e->getMessage());
        }
    }
}
