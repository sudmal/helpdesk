<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        // Пробуем войти по логину, потом по email
        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'login';
        $credentials = [$loginField => $request->email, 'password' => $request->password];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Неверный логин/email или пароль',
            ]);
        }

        // Проверяем активность пользователя
        if (!Auth::user()->is_active) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Ваш аккаунт деактивирован. Обратитесь к администратору.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
