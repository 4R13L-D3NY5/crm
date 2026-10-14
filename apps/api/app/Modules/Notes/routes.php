<?php

use App\Modules\Notes\Http\Controllers\NoteAiController;
use App\Modules\Notes\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('notes')->group(function (): void {
    Route::get('/', [NoteController::class, 'index'])->name('api.notes.index');
    Route::post('/', [NoteController::class, 'store'])->name('api.notes.store');
    Route::post('/analyze', [NoteController::class, 'analyze'])->name('api.notes.analyze');
    Route::post('/ai/refine', [NoteAiController::class, 'refine'])->name('api.notes.ai.refine');
    Route::get('/{id}', [NoteController::class, 'show'])->name('api.notes.show');
    Route::put('/{id}', [NoteController::class, 'update'])->name('api.notes.update');
    Route::delete('/{id}', [NoteController::class, 'destroy'])->name('api.notes.destroy');
    Route::patch('/{id}/pin', [NoteController::class, 'togglePin'])->name('api.notes.pin');
    Route::patch('/{id}/archive', [NoteController::class, 'toggleArchive'])->name('api.notes.archive');
});
