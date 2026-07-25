<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Саморедактирование собственного профиля -- любой авторизованный пользователь,
 * без прав settings.manage (это не то же самое, что SettingsController::updateUser,
 * который меняет ЧУЖИЕ учётки и требует manage-settings). Здесь всегда правится
 * только $request->user() -- id из формы игнорируется, роль/логин/пароль/
 * территории/бригада/активность через этот путь не меняются вообще.
 */
class ProfileController extends Controller
{
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

        return back()->with('success', 'Профиль обновлён');
    }
}
