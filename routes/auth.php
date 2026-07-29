<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CaptchaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login',  [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login',   [AuthenticatedSessionController::class, 'store']);
    Route::get('captcha',  [CaptchaController::class, 'generate'])->name('captcha');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    // Выход из системы -- только по POST (кнопка в интерфейсе), это защита от CSRF
    // через простую ссылку. GET сюда попадает не через приложение, а из закладки
    // браузера/автодополнения адресной строки -- вместо страшной 405-ошибки просто
    // уводим на главную, ничего не разлогинивая.
    Route::get('logout', fn () => redirect('/'));
});
