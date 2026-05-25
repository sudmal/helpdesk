<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectionRequestController;
use App\Http\Controllers\Api\TicketController;
use App\Models\Material;
use App\Models\ServiceType;
use App\Models\Territory;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PbxController;

Route::post('/pbx/incoming', [PbxController::class, 'webhook']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',                    [AuthController::class,  'logout']);
    Route::get('/tickets',                         [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}',                [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/comments',      [TicketController::class, 'addComment']);
    Route::post('/tickets/{ticket}/close',         [TicketController::class, 'close']);
    Route::post('/tickets/{ticket}/attachments',   [TicketController::class, 'addAttachment']);
    Route::post('/tickets/{ticket}/reschedule',    [TicketController::class, 'reschedule']);

    Route::get('/connection-requests',                              [ConnectionRequestController::class, 'index']);
    Route::post('/connection-requests',                             [ConnectionRequestController::class, 'store']);
    Route::get('/connection-requests/{connectionRequest}',          [ConnectionRequestController::class, 'show']);
    Route::put('/connection-requests/{connectionRequest}',          [ConnectionRequestController::class, 'update']);
    Route::post('/connection-requests/{connectionRequest}/close',   [ConnectionRequestController::class, 'close']);
    Route::delete('/connection-requests/{connectionRequest}',       [ConnectionRequestController::class, 'destroy']);

    Route::get('/service_types', function () {
        return response()->json(
            ServiceType::active()->get(['id', 'name', 'color'])
        );
    });

    Route::get('/materials', function () {
        return response()->json(
            Material::active()->orderBy('sort_order')->get(['id', 'code', 'name', 'unit', 'price'])
        );
    });
});
