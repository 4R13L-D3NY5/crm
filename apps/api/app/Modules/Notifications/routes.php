<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->name('api.notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/test', [NotificationController::class, 'sendTest'])->name('test');
    Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read_all');
    Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark_read');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
});
