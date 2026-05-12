<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        $ip       = $request->ip();
        $attempts = (int) Cache::get("login_fails:{$ip}", 0);
        $captchaT = (int) SystemSetting::get('login_captcha_attempts', 3);
        $blockT   = (int) SystemSetting::get('login_block_attempts', 6);
        $blockMin = (int) SystemSetting::get('login_block_minutes', 60);

        return Inertia::render('Auth/Login', [
            'showCaptcha'  => $attempts >= $captchaT && $attempts < $blockT,
            'isBlocked'    => $attempts >= $blockT,
            'blockMinutes' => $blockMin,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $ip       = $request->ip();
        $key      = "login_fails:{$ip}";
        $captchaT = (int) SystemSetting::get('login_captcha_attempts', 3);
        $blockT   = (int) SystemSetting::get('login_block_attempts', 6);
        $blockMin = (int) SystemSetting::get('login_block_minutes', 60);
        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $blockT) {
            return back()->withErrors(['email' => "IP-адрес заблокирован на {$blockMin} мин. из-за многократных неверных попыток."]);
        }

        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($attempts >= $captchaT) {
            $given  = (int) $request->input('captcha', -1);
            $stored = (int) $request->session()->get('captcha_answer', -999);
            $request->session()->forget('captcha_answer');

            if ($given !== $stored || $stored === -999) {
                $this->incrementAttempts($key, $blockMin);
                return back()->withErrors(['captcha' => 'Неверная капча. Попробуйте ещё раз.']);
            }
        }

        $loginField  = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'login';
        $credentials = [$loginField => $request->email, 'password' => $request->password];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            $newAttempts = $this->incrementAttempts($key, $blockMin);
            if ($newAttempts >= $blockT) {
                return back()->withErrors(['email' => "IP-адрес заблокирован на {$blockMin} мин."]);
            }
            return back()->withErrors(['email' => 'Неверный логин/email или пароль']);
        }

        if (!Auth::user()->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Ваш аккаунт деактивирован. Обратитесь к администратору.']);
        }

        Cache::forget($key);
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

    private function incrementAttempts(string $key, int $blockMin): int
    {
        Cache::add($key, 0, now()->addMinutes($blockMin));
        return (int) Cache::increment($key);
    }
}