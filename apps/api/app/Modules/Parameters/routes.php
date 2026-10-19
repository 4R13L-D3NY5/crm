<?php

use App\Modules\Parameters\Http\Controllers\CategoryController;
use App\Modules\Parameters\Http\Controllers\ConversationCategoryController;
use App\Modules\Parameters\Http\Controllers\TimeAlertRuleController;
use Illuminate\Support\Facades\Route;

// Rutas de Categorías & Subcategorías (Lectura permitida a operadores para clasificación de chats)
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

// Rutas de Vinculación con Conversaciones / Leads
Route::get('conversations/{conversation}/categories', [ConversationCategoryController::class, 'index'])->name('conversations.categories.index');
Route::post('conversations/{conversation}/categories', [ConversationCategoryController::class, 'store'])->name('conversations.categories.store');
Route::delete('conversations/{conversation}/categories/{category}', [ConversationCategoryController::class, 'destroy'])->name('conversations.categories.destroy');

// Rutas de Estados Personalizados & Máquina de Estados (Lectura y asignación en chats)
use App\Modules\Parameters\Http\Controllers\CustomStatusController;
use App\Modules\Parameters\Http\Controllers\ConversationCustomStatusController;

Route::get('custom-statuses', [CustomStatusController::class, 'index'])->name('custom-statuses.index');
Route::put('conversations/{conversation}/custom-status', [ConversationCustomStatusController::class, 'update'])->name('conversations.custom-status.update');

// Estado público de Horario Laboral
use App\Modules\Parameters\Http\Controllers\BusinessHoursController;
Route::get('business-hours/status', [BusinessHoursController::class, 'status'])->name('business-hours.status');
Route::get('business-hours', [BusinessHoursController::class, 'show'])->name('business-hours.show');
Route::get('business-hours/configs', [BusinessHoursController::class, 'index'])->name('business-hours.configs.index');

// Rutas de Configuración y Parametrización — Exclusivas para Administradores de Organización
Route::middleware('require.admin')->group(function () {
    // Categorías (Creación, modificación, jerarquía de sedes/carreras)
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('categories/seed-template', [CategoryController::class, 'seedTemplate'])->name('categories.seed-template');
    Route::put('categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/{id}/link-parent', [CategoryController::class, 'linkParent'])->name('categories.link-parent');
    Route::delete('categories/{id}/unlink-parent/{parentId}', [CategoryController::class, 'unlinkParent'])->name('categories.unlink-parent');

    // Estados Personalizados (Creación y ajuste de ciclo de vida)
    Route::post('custom-statuses', [CustomStatusController::class, 'store'])->name('custom-statuses.store');
    Route::post('custom-statuses/seed-academic', [CustomStatusController::class, 'seedAcademic'])->name('custom-statuses.seed-academic');
    Route::put('custom-statuses/{id}', [CustomStatusController::class, 'update'])->name('custom-statuses.update');
    Route::delete('custom-statuses/{id}', [CustomStatusController::class, 'destroy'])->name('custom-statuses.destroy');

    // Reglas SLA y Alertas de Inactividad
    Route::get('time-alert-rules', [TimeAlertRuleController::class, 'index'])->name('time-alert-rules.index');
    Route::post('time-alert-rules', [TimeAlertRuleController::class, 'store'])->name('time-alert-rules.store');
    Route::post('time-alert-rules/seed-default', [TimeAlertRuleController::class, 'seedDefault'])->name('time-alert-rules.seed-default');
    Route::put('time-alert-rules/{id}', [TimeAlertRuleController::class, 'update'])->name('time-alert-rules.update');
    Route::patch('time-alert-rules/{id}/toggle', [TimeAlertRuleController::class, 'toggleActive'])->name('time-alert-rules.toggle');
    Route::delete('time-alert-rules/{id}', [TimeAlertRuleController::class, 'destroy'])->name('time-alert-rules.destroy');

    // Horarios Laborales y Auto-respuesta
    Route::put('business-hours', [BusinessHoursController::class, 'update'])->name('business-hours.update');
    Route::post('business-hours/configs', [BusinessHoursController::class, 'store'])->name('business-hours.configs.store');
    Route::put('business-hours/configs/{id}', [BusinessHoursController::class, 'updateConfig'])->name('business-hours.configs.update');
    Route::delete('business-hours/configs/{id}', [BusinessHoursController::class, 'destroy'])->name('business-hours.configs.destroy');
    Route::patch('business-hours/configs/{id}/toggle', [BusinessHoursController::class, 'toggleConfigActive'])->name('business-hours.configs.toggle');
    Route::patch('business-hours/toggle', [BusinessHoursController::class, 'toggleActive'])->name('business-hours.toggle');
    Route::patch('business-hours/toggle-auto-reply', [BusinessHoursController::class, 'toggleAutoReply'])->name('business-hours.toggle-auto-reply');
});

