<?php

use App\Modules\Users\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
Route::put('users/{id}', [UserManagementController::class, 'update'])->name('users.update');
Route::put('users/{id}/presence', [UserManagementController::class, 'updatePresence'])->name('users.presence');
Route::delete('users/{id}', [UserManagementController::class, 'destroy'])->name('users.destroy');
