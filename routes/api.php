<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',                    [AuthController::class,  'logout']);
    Route::get('/tickets',                         [TicketController::class, 'index']);
    Route::post('/tickets/{ticket}/comments',      [TicketController::class, 'addComment']);
});