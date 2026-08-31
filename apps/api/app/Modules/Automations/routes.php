<?php

use App\Modules\Automations\Http\Controllers\AutomationRuleController;
use App\Modules\Automations\Http\Controllers\CampaignController;
use App\Modules\Automations\Http\Controllers\ScheduledMessageController;
use Illuminate\Support\Facades\Route;

// Mensajes Programados
Route::get('scheduled-messages', [ScheduledMessageController::class, 'index'])->name('scheduled-messages.index');
Route::post('scheduled-messages', [ScheduledMessageController::class, 'store'])->name('scheduled-messages.store');
Route::delete('scheduled-messages/{id}', [ScheduledMessageController::class, 'destroy'])->name('scheduled-messages.destroy');

// Campañas de Disparo Masivo
Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
Route::post('campaigns/{id}/start', [CampaignController::class, 'start'])->name('campaigns.start');
Route::delete('campaigns/{id}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

// Reglas de Automatización
Route::apiResource('automation-rules', AutomationRuleController::class)
    ->parameters(['automation-rules' => 'automationRule'])
    ->except(['show']);
