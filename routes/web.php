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
    ConnectionRequestController,
    ServiceRequestController,
};
use Illuminate\Support\Facades\Route;

require __DIR__ . '/auth.php';

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/pbx/lookup', [\App\Http\Controllers\PbxController::class, 'lookup'])->name('pbx.lookup');
    Route::get('/calls', [\App\Http\Controllers\CallLogController::class, 'index'])->name('calls.index');

    // Р—Р°СЏРІРєРё РЅР° РїРѕРґРєР»СЋС‡РµРЅРёРµ
    Route::prefix('connection-requests')->name('connection-requests.')->group(function () {
        Route::get('/',                                   [ConnectionRequestController::class, 'index'])->name('index');
        Route::post('/',                                  [ConnectionRequestController::class, 'store'])->name('store');
        Route::put('/{connectionRequest}',                [ConnectionRequestController::class, 'update'])->name('update');
        Route::post('/{connectionRequest}/close',         [ConnectionRequestController::class, 'close'])->name('close');
        Route::post('/{connectionRequest}/mark-called',   [ConnectionRequestController::class, 'markCalled'])->name('mark-called');
        Route::get('/{connectionRequest}/detail',          [ConnectionRequestController::class, 'detail'])->name('detail');
        Route::delete('/{connectionRequest}',             [ConnectionRequestController::class, 'destroy'])->name('destroy');
    });

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/new-since', [DashboardController::class, 'newTicketsSince'])->name('dashboard.new-since');

    // Р—Р°СЏРІРєРё
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/',              [TicketController::class, 'index'])->name('index');
        Route::get('/create',        [TicketController::class, 'create'])->name('create');
        Route::get('/map',            [TicketController::class, 'map'])->name('map');
        Route::get('/map-data',        [TicketController::class, 'mapData'])->name('map-data');
        Route::get('/free-slot',      [TicketController::class, 'freeSlot'])->name('free-slot');
        Route::post('/',             [TicketController::class, 'store'])->name('store');
        Route::post('/bulk/close',      [TicketController::class, 'bulkClose'])->name('bulk.close');
        Route::post('/bulk/reschedule', [TicketController::class, 'bulkReschedule'])->name('bulk.reschedule');
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

    // Р’Р»РѕР¶РµРЅРёСЏ
    Route::post('/attachments',              [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{id}',       [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    // РљР°Р»РµРЅРґР°СЂСЊ
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('/',       [CalendarController::class, 'index'])->name('index');
        Route::get('/events', [CalendarController::class, 'events'])->name('events');
    });

    // РўРµСЂСЂРёС‚РѕСЂРёРё
    Route::middleware('can:manage-settings')->prefix('territories')->name('territories.')->group(function () {
        Route::get('/',               [TerritoryController::class, 'index'])->name('index');
        Route::post('/',              [TerritoryController::class, 'store'])->name('store');
        Route::put('/{territory}',    [TerritoryController::class, 'update'])->name('update');
        Route::delete('/{territory}', [TerritoryController::class, 'destroy'])->name('destroy');
    });

    // РџСЂРѕСЃРјРѕС‚СЂ Р±СЂРёРіР°РґС‹ вЂ” РґРѕСЃС‚СѓРїРЅРѕ Р±СЂРёРіР°РґРёСЂСѓ СЃРІРѕРµР№ Р±СЂРёРіР°РґС‹
    Route::get('/brigades/{brigade}', [BrigadeController::class, 'show'])->name('brigades.show');

    // РЎРѕСЃС‚Р°РІ Р±СЂРёРіР°РґС‹ вЂ” РґРѕСЃС‚СѓРїРЅРѕ Р±СЂРёРіР°РґРёСЂСѓ СЃРІРѕРµР№ Р±СЂРёРіР°РґС‹
    Route::put('/brigades/{brigade}/members', [BrigadeController::class, 'updateMembers'])->name('brigades.members.update');

    // Р Р°СЃРїРёСЃР°РЅРёРµ Р±СЂРёРіР°Рґ вЂ” РґРѕСЃС‚СѓРїРЅРѕ Р±СЂРёРіР°РґРёСЂСѓ СЃРІРѕРµР№ Р±СЂРёРіР°РґС‹ (Р°РІС‚РѕСЂРёР·Р°С†РёСЏ РІ РєРѕРЅС‚СЂРѕР»Р»РµСЂРµ)
    Route::prefix('brigades/{brigade}/schedule')->name('brigades.schedule.')->group(function () {
        Route::get('/',          [App\Http\Controllers\BrigadeScheduleController::class, 'show'])->name('show');
        Route::get('/export',    [App\Http\Controllers\BrigadeScheduleController::class, 'export'])->name('export');
        Route::post('/save',     [App\Http\Controllers\BrigadeScheduleController::class, 'save'])->name('save');
        Route::post('/generate',       [App\Http\Controllers\BrigadeScheduleController::class, 'generate'])->name('generate');
        Route::post('/toggle-exclude', [App\Http\Controllers\BrigadeScheduleController::class, 'toggleExclude'])->name('toggle-exclude');
    });
    Route::post('/schedule/holiday', [App\Http\Controllers\BrigadeScheduleController::class, 'toggleHoliday'])->name('brigades.schedule.holiday');
    Route::patch('/brigades/{brigade}/min-workers', [BrigadeController::class, 'updateMinWorkers'])->name('brigades.min-workers');

    // Р‘СЂРёРіР°РґС‹ вЂ” С‚РѕР»СЊРєРѕ СѓРїСЂР°РІР»СЏСЋС‰РёРµ РЅР°СЃС‚СЂРѕР№РєР°РјРё
    Route::middleware('can:manage-settings')->prefix('brigades')->name('brigades.')->group(function () {
        Route::get('/',             [BrigadeController::class, 'index'])->name('index');
        Route::post('/',            [BrigadeController::class, 'store'])->name('store');
        Route::put('/{brigade}',    [BrigadeController::class, 'update'])->name('update');
        Route::delete('/{brigade}', [BrigadeController::class, 'destroy'])->name('destroy');
    });

    // РђРґСЂРµСЃР°
    Route::prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/',             [AddressController::class, 'index'])->name('index');
        Route::post('/',            [AddressController::class, 'store'])->name('store');
        Route::put('/{address}',    [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
        Route::patch('/{address}/geocode', [AddressController::class, 'storeGeocode'])->name('geocode');
        Route::post('/import',      [AddressController::class, 'import'])->name('import');
        Route::post('/bulk-set-type', [AddressController::class, 'bulkSetType'])->name('bulk-set-type');
        Route::get('/search',       [AddressController::class, 'search'])->name('search');
        Route::get('/hierarchy',    [AddressController::class, 'hierarchy'])->name('hierarchy');
    });

    // LANBilling
    Route::get('/lanbilling/lookup', [LanBillingController::class, 'lookup'])->name('lanbilling.lookup');

    // РќР°СЃС‚СЂРѕР№РєРё
    Route::middleware('can:manage-settings')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');

        // РўРёРїС‹ Р·Р°СЏРІРѕРє
        Route::post('/ticket-types',          [SettingsController::class, 'storeType'])->name('ticket-types.store');
        Route::put('/ticket-types/{ticketType}',    [SettingsController::class, 'updateType'])->name('ticket-types.update');
        Route::delete('/ticket-types/{ticketType}', [SettingsController::class, 'destroyType'])->name('ticket-types.destroy');

        // РЎС‚Р°С‚СѓСЃС‹
        Route::post('/ticket-statuses',             [SettingsController::class, 'storeStatus'])->name('ticket-statuses.store');
        Route::put('/ticket-statuses/{ticketStatus}',    [SettingsController::class, 'updateStatus'])->name('ticket-statuses.update');
        Route::delete('/ticket-statuses/{ticketStatus}', [SettingsController::class, 'destroyStatus'])->name('ticket-statuses.destroy');

        // РџРѕР»СЊР·РѕРІР°С‚РµР»Рё
        Route::post('/users',        [SettingsController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}',  [SettingsController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [SettingsController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/test-notify', [SettingsController::class, 'testNotify'])->name('users.test-notify');

        // РЈС‡Р°СЃС‚РєРё
        Route::post('/services',             [ServiceTypeController::class, 'store'])->name('services.store');
        Route::put('/services/{serviceType}',    [ServiceTypeController::class, 'update'])->name('services.update');
        Route::delete('/services/{serviceType}', [ServiceTypeController::class, 'destroy'])->name('services.destroy');

        // Р РѕР»Рё
        Route::put('/roles/{role}',  [SettingsController::class, 'updateRole'])->name('roles.update');

        // РћР±С‰РёРµ РЅР°СЃС‚СЂРѕР№РєРё
        Route::put('/general', [SettingsController::class, 'updateGeneral'])->name('general.update');

        // РЎРѕСЂС‚РёСЂРѕРІРєР°
        Route::post('/sort/service-types', [SettingsController::class, 'sortServiceTypes'])->name('sort.service-types');
        Route::post('/sort/territories',   [SettingsController::class, 'sortTerritories'])->name('sort.territories');

        // РЈРІРµРґРѕРјР»РµРЅРёСЏ вЂ” СЂСѓС‡РЅРѕР№ Р·Р°РїСѓСЃРє
        Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');
        Route::post('/notifications/daily-summary', [SettingsController::class, 'sendDailySummary'])->name('notifications.send-summary');
        Route::post('/notifications/evening-report',[SettingsController::class, 'sendEveningReport'])->name('notifications.send-report');

        // LANBilling
        Route::get('/lanbilling',    [SettingsController::class, 'lanbilling'])->name('lanbilling');
        Route::put('/lanbilling',    [SettingsController::class, 'updateLanbilling'])->name('lanbilling.update');

        // Р‘РµР·РѕРїР°СЃРЅРѕСЃС‚СЊ
        Route::get('/security/data',    [SettingsController::class, 'securityData'])->name('security.data');
        Route::put('/service-request-services', [SettingsController::class, 'updateServiceRequestServices'])->name('service-request-services.update');

        Route::post('/security/unblock', [SettingsController::class, 'unblockIp'])->name('security.unblock');
    });

    Route::middleware('can:manage-settings')->get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::middleware('can:manage-settings')->get('/reports/brigade-load', [App\Http\Controllers\ReportsController::class, 'brigadeLoadData'])->name('reports.brigade-load');
    Route::middleware('can:manage-settings')->get('/reports/territory-frequency', [App\Http\Controllers\ReportsController::class, 'territoryFrequencyData'])->name('reports.territory-frequency');
    Route::middleware('can:manage-settings')->get('/reports/material-dynamics', [App\Http\Controllers\ReportsController::class, 'materialDynamicsData'])->name('reports.material-dynamics');
    Route::middleware('can:manage-settings')->get('/reports/deadline-compliance', [App\Http\Controllers\ReportsController::class, 'deadlineComplianceData'])->name('reports.deadline-compliance');
    Route::middleware('can:manage-settings')->get('/reports/distribution', [App\Http\Controllers\ReportsController::class, 'distributionData'])->name('reports.distribution');
    Route::middleware('can:manage-settings')->get('/reports/call-stats', [App\Http\Controllers\ReportsController::class, 'callStatsData'])->name('reports.call-stats');
    Route::middleware(['auth', 'active'])->prefix('acts')->name('acts.')->group(function () {
        Route::get('/', [App\Http\Controllers\ActController::class, 'index'])->name('index');
        Route::get('/{act}', [App\Http\Controllers\ActController::class, 'show'])->name('show');
        Route::post('/{act}/approve', [App\Http\Controllers\ActController::class, 'approve'])->name('approve');
        Route::post('/{act}/process-peo', [App\Http\Controllers\ActController::class, 'processPeo'])->name('process-peo');
        Route::post('/{act}/process-logistics', [App\Http\Controllers\ActController::class, 'processLogistics'])->name('process-logistics');
        Route::post('/{act}/complete', [App\Http\Controllers\ActController::class, 'complete'])->name('complete');
        Route::post('/{act}/materials', [App\Http\Controllers\ActController::class, 'addMaterial'])->name('materials.store');
        Route::put('/{act}/materials/{material}', [App\Http\Controllers\ActController::class, 'updateMaterial'])->name('materials.update');
        Route::delete('/{act}/materials/{material}', [App\Http\Controllers\ActController::class, 'removeMaterial'])->name('materials.destroy');
        Route::post('/{act}/acknowledge', [App\Http\Controllers\ActController::class, 'acknowledge'])->name('acknowledge');
    });

    Route::get('/help', [App\Http\Controllers\HelpController::class, 'index'])->name('help');

});

// РњР°С‚РµСЂРёР°Р»С‹ (СЃРїСЂР°РІРѕС‡РЅРёРє)
Route::resource('materials', App\Http\Controllers\MaterialController::class)
    ->except(['show', 'create', 'edit'])
    ->middleware('auth');

Route::middleware('can:manage-settings')->prefix('materials/report')->name('materials.report.')->group(function () {
    Route::get('/consumption', [App\Http\Controllers\MaterialReportController::class, 'consumption'])->name('consumption');
    Route::get('/monthly-matrix', [App\Http\Controllers\MaterialReportController::class, 'monthlyMatrix'])->name('monthly-matrix');
    Route::get('/forecast', [App\Http\Controllers\MaterialReportController::class, 'forecast'])->name('forecast');
    Route::get('/export', [App\Http\Controllers\MaterialReportController::class, 'exportCsv'])->name('export');
});

// Р Р°СЃС…РѕРґРЅРёРєРё РїРѕ Р·Р°СЏРІРєРµ
Route::post('tickets/{ticket}/materials', [App\Http\Controllers\MaterialController::class, 'storeForTicket'])
    ->name('tickets.materials.store')
    ->middleware('auth');

// Push СѓРІРµРґРѕРјР»РµРЅРёСЏ
Route::middleware('auth')->prefix('push')->group(function () {
    Route::get('/vapid-key',    [App\Http\Controllers\PushController::class, 'vapidKey'])->name('push.vapid-key');
    Route::post('/subscribe',   [App\Http\Controllers\PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/unsubscribe', [App\Http\Controllers\PushController::class, 'unsubscribe'])->name('push.unsubscribe');
});


// Запросы на услуги
Route::middleware(['auth', 'active'])->prefix('service-requests')->name('service-requests.')->group(function () {
    Route::get('/',                         [ServiceRequestController::class, 'index'])->name('index');
    Route::post('/',                        [ServiceRequestController::class, 'store'])->name('store');
    Route::put('/{serviceRequest}',           [ServiceRequestController::class, 'update'])->name('update');
    Route::post('/{serviceRequest}/accept', [ServiceRequestController::class, 'accept'])->name('accept');
    Route::post('/{serviceRequest}/reject', [ServiceRequestController::class, 'reject'])->name('reject');
    Route::get('/{serviceRequest}/detail',  [ServiceRequestController::class, 'detail'])->name('detail');
    Route::delete('/{serviceRequest}',      [ServiceRequestController::class, 'destroy'])->name('destroy');
});
// Sync API (РґР»СЏ СЃРєСЂРёРїС‚Р° СЃРёРЅС…СЂРѕРЅРёР·Р°С†РёРё)
Route::post('/sync/ticket',        [SyncController::class, 'store'])->name('sync.ticket');
Route::post('/sync/legacy-ticket', [SyncController::class, 'storeLegacy'])->name('sync.legacy-ticket');

// АТС — состояние очереди (без CSRF, защищено токеном)
Route::post('/pbx/queue-status', [\App\Http\Controllers\PbxController::class, 'queueStatus'])->name('pbx.queue-status');
Route::middleware(['auth', 'active'])->get('/pbx/queue-history', [\App\Http\Controllers\PbxController::class, 'queueHistory'])->name('pbx.queue-history');
Route::middleware(['auth', 'active'])->post('/pbx/trigger-cmd', [\App\Http\Controllers\PbxController::class, 'triggerCmd'])->name('pbx.trigger-cmd');
Route::post('/pbx/ivr-log', [\App\Http\Controllers\PbxController::class, 'ivrLog'])->name('pbx.ivr-log');
Route::post('/pbx/alert', [\App\Http\Controllers\PbxController::class, 'alert'])->name('pbx.alert');
Route::get('/pbx/dnd-status', [\App\Http\Controllers\PbxController::class, 'dndStatus'])->name('pbx.dnd-status');
Route::middleware(['auth', 'active'])->get('/ivr-log', [\App\Http\Controllers\IvrLogController::class, 'index'])->name('ivr-log.index');
Route::middleware(['auth', 'active'])->get('/pbx/ivr-log-data', [\App\Http\Controllers\IvrLogController::class, 'data'])->name('ivr-log.data');
Route::post('/pbx/dnd-log', [\App\Http\Controllers\PbxController::class, 'dndLog'])->name('pbx.dnd-log');

// Отчёты по сменам
Route::middleware(['auth', 'active'])->prefix('pbx/shift-reports')->name('pbx.shift-reports.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ShiftReportController::class, 'index'])->name('index');
    // ВАЖНО: до {shiftReport} -- иначе "current" пытается забиндиться как ID
    Route::get('/current', [\App\Http\Controllers\ShiftReportController::class, 'current'])->name('current');
    Route::get('/audit', [\App\Http\Controllers\ShiftReportController::class, 'audit'])->name('audit');
    Route::get('/{shiftReport}', [\App\Http\Controllers\ShiftReportController::class, 'show'])->name('show');
    Route::middleware('can:manage-settings')->post('/regenerate', [\App\Http\Controllers\ShiftReportController::class, 'regenerate'])->name('regenerate');
});
Route::middleware(['auth', 'active'])->prefix('pbx/shift-definitions')->name('pbx.shift-definitions.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ShiftReportController::class, 'definitions'])->name('index');
    Route::middleware('can:manage-settings')->post('/', [\App\Http\Controllers\ShiftReportController::class, 'storeDefinition'])->name('store');
    Route::middleware('can:manage-settings')->put('/{definition}', [\App\Http\Controllers\ShiftReportController::class, 'updateDefinition'])->name('update');
    Route::middleware('can:manage-settings')->delete('/{definition}', [\App\Http\Controllers\ShiftReportController::class, 'destroyDefinition'])->name('destroy');
});

// Telegram Bot
Route::post('/telegram/webhook', [App\Http\Controllers\TelegramController::class, 'webhook'])
    ->name('telegram.webhook');
Route::get('/telegram/set-webhook', [App\Http\Controllers\TelegramController::class, 'setWebhook'])
    ->middleware('auth')
    ->name('telegram.set-webhook');




