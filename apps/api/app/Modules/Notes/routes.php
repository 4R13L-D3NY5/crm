<?php

use App\Modules\Notes\Http\Controllers\NoteAiController;
use App\Modules\Notes\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('notes')->group(function (): void {
    Route::get('/', [NoteController::class, 'index'])->name('api.notes.index');
    Route::post('/', [NoteController::class, 'store'])->name('api.notes.store');
    Route::post('/analyze', [NoteController::class, 'analyze'])->name('api.notes.analyze');
    Route::post('/similar', [NoteAiController::class, 'similarDraft'])->name('api.notes.similar-draft');
    Route::post('/similar-matrix', [NoteAiController::class, 'similarMatrix'])->name('api.notes.similar-matrix');
    Route::post('/ai/refine', [NoteAiController::class, 'refine'])->name('api.notes.ai.refine');
    Route::post('/ai/suggest-entities', [NoteAiController::class, 'suggestEntities'])->name('api.notes.ai.suggest-entities');
    Route::post('/preview-keep', [NoteController::class, 'previewKeep'])->name('api.notes.preview-keep');
    Route::post('/import-batch', [NoteController::class, 'importBatch'])->name('api.notes.import-batch');
    Route::post('/upload-document', [NoteController::class, 'uploadDocument'])->name('api.notes.upload-document');
    Route::post('/upload-image', [NoteController::class, 'uploadImage'])->name('api.notes.upload-image');
    Route::get('/{id}', [NoteController::class, 'show'])->name('api.notes.show');
    Route::get('/{id}/similar', [NoteAiController::class, 'similar'])->name('api.notes.similar');
    Route::put('/{id}', [NoteController::class, 'update'])->name('api.notes.update');
    Route::delete('/{id}', [NoteController::class, 'destroy'])->name('api.notes.destroy');
    Route::patch('/{id}/pin', [NoteController::class, 'togglePin'])->name('api.notes.pin');
    Route::patch('/{id}/archive', [NoteController::class, 'toggleArchive'])->name('api.notes.archive');
});
