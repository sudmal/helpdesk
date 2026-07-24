<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && !auth()->user()->is_active) {
            // Токен мобильного API (Android/PWA) отзываем сразу -- иначе он продолжит
            // проходить auth:sanctum на каждый следующий запрос до этой же проверки.
            $request->user()->currentAccessToken()?->delete();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Аккаунт деактивирован. Обратитесь к администратору.'], 403);
            }

            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Ваш аккаунт деактивирован. Обратитесь к администратору.']);
        }

        // "Кто сейчас активен" в Настройках -> Пользователи (веб-канал). API/PWA
        // канал не нуждается в этом -- Sanctum сам обновляет last_used_at токена
        // на каждый запрос. Троттлинг в минуту, чтобы не долбить UPDATE на
        // каждый запрос активного пользователя.
        if (auth()->check() && !$request->is('api/*')) {
            $u = auth()->user();
            if (!$u->last_web_seen_at || $u->last_web_seen_at->lt(now()->subMinute())) {
                \App\Models\User::where('id', $u->id)->update(['last_web_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
