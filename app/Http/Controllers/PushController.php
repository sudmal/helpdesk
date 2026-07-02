<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

class PushController extends Controller
{
    // Сохраняем подписку
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint'        => 'required|url',
            'keys.auth'       => 'required|string',
            'keys.p256dh'     => 'required|string',
        ]);

        $user = auth()->user();
        $user->updatePushSubscription(
            $request->endpoint,
            $request->keys['p256dh'],
            $request->keys['auth']
        );

        return response()->json(['ok' => true]);
    }

    // Удаляем подписку
    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required|url']);
        auth()->user()->deletePushSubscription($request->endpoint);
        return response()->json(['ok' => true]);
    }

    // VAPID публичный ключ для фронта
    public function vapidKey()
    {
        return response()->json([
            'key' => config('webpush.vapid.public_key')
        ]);
    }
}
