<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:whatsapp-webhook');

Route::post('/whatsapp/baileys/webhook', [\App\Modules\WhatsApp\Http\Controllers\BaileysWebhookController::class, 'handleWebhook']);
