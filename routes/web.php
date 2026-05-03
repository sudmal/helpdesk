<?php

use App\Http\Controllers\{
    DashboardController,
    TicketController,
    CalendarController,
    TerritoryController,
    BrigadeController,
    AddressController,
    SettingsController,
    AttachmentController,
    LanBillingController,
};
use Illuminate\Support\Facades\Route;

// Auth routes (Laravel Breeze / Fortify)
require __DIR__ . '/auth.php';

Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Заявки
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/',                [TicketController::class, 'index'])->name('index');
        Route::get('/create',          [TicketController::class, 'create'])->name('create');
        Route::post('/',               [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket}',        [TicketController::class, 'show'])->name('show');
        Route::get('/{ticket}/edit',   [TicketController::class, 'edit'])->name('edit');
        Route::put('/{ticket}',        [TicketController::class, 'update'])->name('update');
        Route::delete('/{ticket}',     [TicketController::class, 'destroy'])->name('destroy');

        // Действия по заявке (монтажник/бригадир)
        Route::post('/{ticket}/start',    [TicketController::class, 'start'])->name('start');
        Route::post('/{ticket}/pause',    [TicketController::class, 'pause'])->name('pause');
        Route::post('/{ticket}/close',    [TicketController::class, 'close'])->name('close');
        Route::post('/{ticket}/reopen',   [TicketController::class, 'reopen'])->name('reopen');
        Route::post('/{ticket}/assign',   [TicketController::class, 'assign'])->name('assign');

        // Комментарии
        Route::post('/{ticket}/comments', [TicketController::class, 'addComment'])->name('comment');
    });

    // Вложения
    Route::post('/attachments',           [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{id}',    [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    // Календарь
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/',               [CalendarController::class, 'index'])->name('index');
        Route::get('/events',         [CalendarController::class, 'events'])->name('events'); // API для FullCalendar
    });

    // Территории (Админ + Нач. ТП)
    Route::middleware('can:manage-settings')->prefix('territories')->name('territories.')->group(function () {
        Route::get('/',               [TerritoryController::class, 'index'])->name('index');
        Route::post('/',              [TerritoryController::class, 'store'])->name('store');
        Route::put('/{territory}',    [TerritoryController::class, 'update'])->name('update');
        Route::delete('/{territory}', [TerritoryController::class, 'destroy'])->name('destroy');
    });

    // Бригады (Админ + Нач. ТП)
    Route::middleware('can:manage-settings')->prefix('brigades')->name('brigades.')->group(function () {
        Route::get('/',              [BrigadeController::class, 'index'])->name('index');
        Route::post('/',             [BrigadeController::class, 'store'])->name('store');
        Route::put('/{brigade}',     [BrigadeController::class, 'update'])->name('update');
        Route::delete('/{brigade}',  [BrigadeController::class, 'destroy'])->name('destroy');
    });

    // Адреса
    Route::prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/',              [AddressController::class, 'index'])->name('index');
        Route::post('/',             [AddressController::class, 'store'])->name('store');
        Route::put('/{address}',     [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}',  [AddressController::class, 'destroy'])->name('destroy');
        Route::post('/import',       [AddressController::class, 'import'])->name('import');  // CSV/XLS
        Route::get('/search',        [AddressController::class, 'search'])->name('search'); // AJAX
    });

    // LANBilling API
    Route::prefix('lanbilling')->name('lanbilling.')->group(function () {
        Route::get('/lookup',        [LanBillingController::class, 'lookup'])->name('lookup'); // ?phone= или ?contract=
    });

    // Настройки (только Админ + Нач. ТП)
    Route::middleware('can:manage-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/',                       [SettingsController::class, 'index'])->name('index');
        Route::apiResource('ticket-types',    SettingsController::class . '@ticketTypes');
        Route::apiResource('ticket-statuses', SettingsController::class . '@ticketStatuses');
        Route::get('/users',                  [SettingsController::class, 'users'])->name('users');
        Route::post('/users',                 [SettingsController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}',           [SettingsController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}',        [SettingsController::class, 'destroyUser'])->name('users.destroy');
        Route::get('/lanbilling',             [SettingsController::class, 'lanbilling'])->name('lanbilling');
        Route::put('/lanbilling',             [SettingsController::class, 'updateLanbilling'])->name('lanbilling.update');
    });
});
