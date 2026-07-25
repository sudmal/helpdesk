<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Саморедактирование собственного профиля -- мобильный аналог веб-версии
 * (App\Http\Controllers\ProfileController). Всегда правит только
 * $request->user() (аутентифицирован Sanctum-токеном), роль/логин/пароль/
 * территории/бригаду/активность через этот путь поменять нельзя.
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|unique:users,email,' . $user->id,
            'telegram_chat_id'   => 'nullable|string|max:50',
            'max_chat_id'        => 'nullable|string|max:50',
            'notify_on_days_off' => 'boolean',
        ]);

        $user->update($data);

        return response()->json($this->formatUser($user->fresh()));
    }

    private function formatUser($user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'phone'              => $user->phone,
            'email'              => $user->email,
            'telegram_chat_id'   => $user->telegram_chat_id,
            'max_chat_id'        => $user->max_chat_id,
            'notify_on_days_off' => $user->notify_on_days_off,
            'role'               => $user->role?->slug,
        ];
    }
}
