<?php

use App\Modules\Settings\Http\Controllers\WorkspaceSettingController;
use App\Modules\Auth\Http\Controllers\ApiTokenController;
use App\Modules\Contacts\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Configuración del Workspace
Route::get('/settings/workspace', [WorkspaceSettingController::class, 'show'])->name('api.settings.workspace.show');
Route::put('/settings/workspace', [WorkspaceSettingController::class, 'update'])->name('api.settings.workspace.update');

// Tokens de API
Route::get('/tokens', [ApiTokenController::class, 'index'])->name('api.tokens.index');
Route::post('/tokens', [ApiTokenController::class, 'store'])->name('api.tokens.store');
Route::delete('/tokens/{id}', [ApiTokenController::class, 'destroy'])->name('api.tokens.destroy');

// Etiquetas (Tags) con soporte de Color
Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
Route::post('/tags', [TagController::class, 'store'])->name('api.tags.store');
Route::put('/tags/{id}', [TagController::class, 'update'])->name('api.tags.update');
Route::delete('/tags/{id}', [TagController::class, 'destroy'])->name('api.tags.destroy');
