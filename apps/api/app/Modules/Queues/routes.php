<?php

use App\Modules\Queues\Http\Controllers\BotFlowController;
use App\Modules\Queues\Http\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::apiResource('queues', QueueController::class);

// Flujos de Chatbot y Árbol de Decisión
Route::get('bot-flows', [BotFlowController::class, 'index'])->name('bot-flows.index');
Route::post('bot-flows', [BotFlowController::class, 'store'])->name('bot-flows.store');
Route::put('bot-flows/{id}', [BotFlowController::class, 'update'])->name('bot-flows.update');
Route::post('bot-flows/{id}/process-message', [BotFlowController::class, 'processMessage'])->name('bot-flows.process-message');
Route::delete('bot-flows/{id}', [BotFlowController::class, 'destroy'])->name('bot-flows.destroy');
