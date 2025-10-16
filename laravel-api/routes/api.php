<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\MonitorController;

Route::get('/health', function () {
    return response()->json(['ok' => true]);
});

// Queues
Route::get('/queues', [QueueController::class, 'index']);

// Tickets
Route::post('/tickets', [TicketController::class, 'store']);
Route::get('/tickets/{extId}', [TicketController::class, 'show']);
Route::post('/tickets/{extId}/status', [TicketController::class, 'updateStatus']);

// Atendimento
Route::post('/queues/{queue}/next', [TicketController::class, 'next']);
Route::get('/queues/{queue}/current', [TicketController::class, 'current']);
Route::get('/queues/{queue}/waiting', [TicketController::class, 'waiting']);

// Monitor
Route::get('/monitor/{queue}', [MonitorController::class, 'show']);
