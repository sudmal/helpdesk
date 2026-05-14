<?php

use App\Http\Controllers\{
    DashboardController,
    SyncController,
    ServiceTypeController,
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

require __DIR__ . '/auth.php';

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/new-since', [DashboardController::class, 'newTicketsSince'])->name('dashboard.new-since');

    // Заявки
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/',              [TicketController::class, 'index'])->name('index');
        Route::get('/create',        [TicketController::class, 'create'])->name('create');
        Route::get('/free-slot',      [TicketController::class, 'freeSlot'])->name('free-slot');
        Route::post('/',             [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket}',      [TicketController::class, 'show'])->name('show');
        Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->name('edit');
        Route::put('/{ticket}',      [TicketController::class, 'update'])->name('update');
        Route::delete('/{ticket}',   [TicketController::class, 'destroy'])->name('destroy');
        Route::post('/{ticket}/start',   [TicketController::class, 'start'])->name('start');
        Route::post('/{ticket}/pause',   [TicketController::class, 'pause'])->name('pause');
        Route::post('/{ticket}/close',   [TicketController::class, 'close'])->name('close');
        Route::post('/{ticket}/reopen',   [TicketController::class, 'reopen'])->name('reopen');
        Route::post('/{ticket}/postpone', [TicketController::class, 'postpone'])->name('postpone');
        Route::post('/{ticket}/assign',  [TicketController::class, 'assign'])->name('assign');
        Route::post('/{ticket}/comments',[TicketController::class, 'addComment'])->name('comment');
    });

    // Вложения
    Route::post('/attachments',              [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{id}',       [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    // Календарь
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/',       [CalendarController::class, 'index'])->name('index');
        Route::get('/events', [CalendarController::class, 'events'])->name('events');
    });

    // Территории
    Route::middleware('can:manage-settings')->prefix('territories')->name('territories.')->group(function () {
        Route::get('/',               [TerritoryController::class, 'index'])->name('index');
        Route::post('/',              [TerritoryController::class, 'store'])->name('store');
        Route::put('/{territory}',    [TerritoryController::class, 'update'])->name('update');
        Route::delete('/{territory}', [TerritoryController::class, 'destroy'])->name('destroy');
    });

    // Расписание бригад — доступно бригадиру своей бригады (авторизация в контроллере)
    Route::prefix('brigades/{brigade}/schedule')->name('brigades.schedule.')->group(function () {
        Route::get('/',          [App\Http\Controllers\BrigadeScheduleController::class, 'show'])->name('show');
        Route::post('/save',     [App\Http\Controllers\BrigadeScheduleController::class, 'save'])->name('save');
        Route::post('/generate', [App\Http\Controllers\BrigadeScheduleController::class, 'generate'])->name('generate');
    });
    Route::post('/schedule/holiday', [App\Http\Controllers\BrigadeScheduleController::class, 'toggleHoliday'])->name('brigades.schedule.holiday');
    Route::patch('/brigades/{brigade}/min-workers', [BrigadeController::class, 'updateMinWorkers'])->name('brigades.min-workers');

    // Бригады — только управляющие настройками
    Route::middleware('can:manage-settings')->prefix('brigades')->name('brigades.')->group(function () {
        Route::get('/',             [BrigadeController::class, 'index'])->name('index');
        Route::post('/',            [BrigadeController::class, 'store'])->name('store');
        Route::put('/{brigade}',    [BrigadeController::class, 'update'])->name('update');
        Route::delete('/{brigade}', [BrigadeController::class, 'destroy'])->name('destroy');
    });

    // Адреса
    Route::prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/',             [AddressController::class, 'index'])->name('index');
        Route::post('/',            [AddressController::class, 'store'])->name('store');
        Route::put('/{address}',    [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
        Route::post('/import',      [AddressController::class, 'import'])->name('import');
        Route::get('/search',       [AddressController::class, 'search'])->name('search');
        Route::get('/hierarchy',    [AddressController::class, 'hierarchy'])->name('hierarchy');
    });

    // LANBilling
    Route::get('/lanbilling/lookup', [LanBillingController::class, 'lookup'])->name('lanbilling.lookup');

    // Настройки
    Route::middleware('can:manage-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');

        // Типы заявок
        Route::post('/ticket-types',          [SettingsController::class, 'storeType'])->name('ticket-types.store');
        Route::put('/ticket-types/{ticketType}',    [SettingsController::class, 'updateType'])->name('ticket-types.update');
        Route::delete('/ticket-types/{ticketType}', [SettingsController::class, 'destroyType'])->name('ticket-types.destroy');

        // Статусы
        Route::post('/ticket-statuses',             [SettingsController::class, 'storeStatus'])->name('ticket-statuses.store');
        Route::put('/ticket-statuses/{ticketStatus}',    [SettingsController::class, 'updateStatus'])->name('ticket-statuses.update');
        Route::delete('/ticket-statuses/{ticketStatus}', [SettingsController::class, 'destroyStatus'])->name('ticket-statuses.destroy');

        // Пользователи
        Route::post('/users',        [SettingsController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}',  [SettingsController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [SettingsController::class, 'destroyUser'])->name('users.destroy');

        // Участки
        Route::post('/services',             [ServiceTypeController::class, 'store'])->name('services.store');
        Route::put('/services/{serviceType}',    [ServiceTypeController::class, 'update'])->name('services.update');
        Route::delete('/services/{serviceType}', [ServiceTypeController::class, 'destroy'])->name('services.destroy');

        // Роли
        Route::put('/roles/{role}',  [SettingsController::class, 'updateRole'])->name('roles.update');

        // Общие настройки
        Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('general.update');

        // Сортировка
        Route::post('/sort/service-types', [SettingsController::class, 'sortServiceTypes'])->name('sort.service-types');
        Route::post('/sort/territories',   [SettingsController::class, 'sortTerritories'])->name('sort.territories');

        // Уведомления — ручной запуск
        Route::post('/notifications/daily-summary', [SettingsController::class, 'sendDailySummary'])->name('notifications.send-summary');
        Route::post('/notifications/evening-report',[SettingsController::class, 'sendEveningReport'])->name('notifications.send-report');

        // LANBilling
        Route::get('/lanbilling',    [SettingsController::class, 'lanbilling'])->name('lanbilling');
        Route::put('/lanbilling',    [SettingsController::class, 'updateLanbilling'])->name('lanbilling.update');
    });
    Route::middleware('can:manage-settings')->get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/help', [App\Http\Controllers\HelpController::class, 'index'])->name('help');

});

// Материалы (справочник)
Route::resource('materials', App\Http\Controllers\MaterialController::class)
    ->except(['show', 'create', 'edit'])
    ->middleware('auth');

// Расходники по заявке
Route::post('tickets/{ticket}/materials', [App\Http\Controllers\MaterialController::class, 'storeForTicket'])
    ->name('tickets.materials.store')
    ->middleware('auth');

// Push уведомления
Route::middleware('auth')->prefix('push')->group(function () {
    Route::get('/vapid-key',    [App\Http\Controllers\PushController::class, 'vapidKey'])->name('push.vapid-key');
    Route::post('/subscribe',   [App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/unsubscribe', [App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');
});

// Sync API (для скрипта синхронизации)
Route::post('/sync/ticket', [SyncController::class, 'store'])->name('sync.ticket');

// Telegram Bot
Route::post('/telegram/webhook', [App\Http\Controllers\TelegramController::class, 'webhook'])
    ->name('telegram.webhook');
Route::get('/telegram/set-webhook', [App\Http\Controllers\TelegramController::class, 'setWebhook'])
    ->middleware('auth')
    ->name('telegram.set-webhook');
