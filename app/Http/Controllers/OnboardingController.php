<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    // Отмечает у текущего пользователя, что интерактивный тур с этим ключом
    // пройден или явно скрыт ("больше не показывать") — см. память проекта,
    // useTour.js на фронте вызывает это при завершении/пропуске тура.
    public function markSeen(Request $request, string $tour): JsonResponse
    {
        $user = $request->user();
        $seen = $user->onboarding_seen ?? [];

        if (!in_array($tour, $seen, true)) {
            $seen[] = $tour;
            $user->onboarding_seen = $seen;
            $user->save();
        }

        return response()->json(['ok' => true, 'onboarding_seen' => $seen]);
    }
}
