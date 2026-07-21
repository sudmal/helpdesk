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
        return $next($request);
    }
}
