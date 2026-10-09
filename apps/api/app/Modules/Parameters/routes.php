<?php

use App\Modules\Parameters\Http\Controllers\CategoryController;
use App\Modules\Parameters\Http\Controllers\ConversationCategoryController;
use Illuminate\Support\Facades\Route;

// Rutas de Categorías & Subcategorías
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
Route::post('categories/seed-template', [CategoryController::class, 'seedTemplate'])->name('categories.seed-template');
Route::put('categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

// Rutas avanzadas para gestión de dependencias y ramas (Grafo / Multi-padre)
Route::post('categories/{id}/link-parent', [CategoryController::class, 'linkParent'])->name('categories.link-parent');
Route::delete('categories/{id}/unlink-parent/{parentId}', [CategoryController::class, 'unlinkParent'])->name('categories.unlink-parent');

// Rutas de Vinculación con Conversaciones / Leads
Route::get('conversations/{conversation}/categories', [ConversationCategoryController::class, 'index'])->name('conversations.categories.index');
Route::post('conversations/{conversation}/categories', [ConversationCategoryController::class, 'store'])->name('conversations.categories.store');
Route::delete('conversations/{conversation}/categories/{category}', [ConversationCategoryController::class, 'destroy'])->name('conversations.categories.destroy');

// Rutas de Estados Personalizados & Máquina de Estados
use App\Modules\Parameters\Http\Controllers\CustomStatusController;
use App\Modules\Parameters\Http\Controllers\ConversationCustomStatusController;

Route::get('custom-statuses', [CustomStatusController::class, 'index'])->name('custom-statuses.index');
Route::post('custom-statuses', [CustomStatusController::class, 'store'])->name('custom-statuses.store');
Route::post('custom-statuses/seed-academic', [CustomStatusController::class, 'seedAcademic'])->name('custom-statuses.seed-academic');
Route::put('custom-statuses/{id}', [CustomStatusController::class, 'update'])->name('custom-statuses.update');
Route::delete('custom-statuses/{id}', [CustomStatusController::class, 'destroy'])->name('custom-statuses.destroy');

Route::put('conversations/{conversation}/custom-status', [ConversationCustomStatusController::class, 'update'])->name('conversations.custom-status.update');
