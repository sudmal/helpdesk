<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function webhook(Request $request, TelegramService $telegram)
    {
        $update = $request->all();
        $telegram->handleUpdate($update);
        return response()->json(['ok' => true]);
    }

    // Установить webhook
    public function setWebhook(TelegramService $telegram)
    {
        $url    = config('app.url') . '/telegram/webhook';
        $result = \Illuminate\Support\Facades\Http::post(
            "https://api.telegram.org/bot" . config('services.telegram.token') . "/setWebhook",
            ['url' => $url]
        );
        return response()->json($result->json());
    }
}
