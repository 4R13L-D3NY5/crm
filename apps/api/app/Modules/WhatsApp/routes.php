<?php

use App\Modules\WhatsApp\Http\Controllers\AssignUsersToWhatsAppAccountController;
use App\Modules\WhatsApp\Http\Controllers\ConversationWhatsAppMessageController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppAccountController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppQrSessionController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppWebhookEventController;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp/accounts', [WhatsAppAccountController::class, 'index']);
Route::post('whatsapp/accounts', [WhatsAppAccountController::class, 'store']);
Route::delete('whatsapp/accounts/{id}', [WhatsAppAccountController::class, 'destroy']);
Route::get('whatsapp/accounts/current', [WhatsAppAccountController::class, 'show']);
Route::put('whatsapp/accounts/current', [WhatsAppAccountController::class, 'update']);

Route::put('whatsapp/accounts/{id}/users', AssignUsersToWhatsAppAccountController::class);
Route::get('whatsapp/events', [WhatsAppWebhookEventController::class, 'index']);
Route::post('conversations/{conversation}/messages/whatsapp', [ConversationWhatsAppMessageController::class, 'store']);
Route::post('conversations/{conversation}/messages/{message}/whatsapp-retry', [ConversationWhatsAppMessageController::class, 'retry']);

// Sesiones de QR y Pasarela de Conexión
Route::get('whatsapp/accounts/{id}/qr', [WhatsAppQrSessionController::class, 'getQr'])->name('whatsapp.qr');
Route::post('whatsapp/accounts/{id}/simulate-scan', [WhatsAppQrSessionController::class, 'simulateScan'])->name('whatsapp.simulate-scan');
Route::post('whatsapp/accounts/{id}/disconnect', [WhatsAppQrSessionController::class, 'disconnect'])->name('whatsapp.disconnect');
Route::post('whatsapp/accounts/{id}/simulate-incoming', [WhatsAppQrSessionController::class, 'simulateIncoming'])->name('whatsapp.simulate-incoming');
Route::post('whatsapp/accounts/{id}/pairing-code', [WhatsAppQrSessionController::class, 'getPairingCode'])->name('whatsapp.pairing-code');
