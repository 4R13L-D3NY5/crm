<?php

use App\Modules\Conversations\Http\Controllers\ConversationAssignmentController;
use App\Modules\Conversations\Http\Controllers\ConversationController;
use App\Modules\Conversations\Http\Controllers\ConversationMessageController;
use App\Modules\Conversations\Http\Controllers\ConversationStatusController;
use App\Modules\Conversations\Http\Controllers\TicketLifecycleController;
use App\Modules\Conversations\Http\Controllers\InternalNoteController;
use App\Modules\Conversations\Http\Controllers\SupervisorMessageController;
use App\Modules\Conversations\Http\Controllers\TicketSlaAlertController;
use App\Modules\Conversations\Http\Controllers\VoiceTranscriptionController;
use Illuminate\Support\Facades\Route;

// Alertas SLA
Route::get('conversations/sla-alerts', [TicketSlaAlertController::class, 'index'])->name('conversations.sla-alerts');

// Ciclo de vida y Actions de Tickets
Route::post('conversations/{conversation}/accept', [TicketLifecycleController::class, 'accept'])->name('conversations.accept');
Route::post('conversations/{conversation}/transfer', [TicketLifecycleController::class, 'transfer'])->name('conversations.transfer');
Route::post('conversations/{conversation}/close', [TicketLifecycleController::class, 'close'])->name('conversations.close');
Route::post('conversations/{conversation}/reopen', [TicketLifecycleController::class, 'reopen'])->name('conversations.reopen');

// Notas internas y Modo Supervisor Fantasma
Route::post('conversations/{conversation}/messages/internal', [InternalNoteController::class, 'store'])->name('conversations.messages.internal');
Route::post('conversations/{conversation}/messages/supervisor', [SupervisorMessageController::class, 'store'])->name('conversations.messages.supervisor');

// Transcripción de Audio y Síntesis de Voz (Voice-to-Text / TTS)
Route::post('conversations/{conversation}/messages/{message}/transcribe', [VoiceTranscriptionController::class, 'transcribe'])->name('conversations.messages.transcribe');
Route::post('conversations/{conversation}/voice-notes/synthesize', [VoiceTranscriptionController::class, 'synthesize'])->name('conversations.voice-notes.synthesize');

// Rutas base
Route::put('conversations/{conversation}/assignment', [ConversationAssignmentController::class, 'update']);
Route::put('conversations/{conversation}/status', [ConversationStatusController::class, 'update']);
Route::apiResource('conversations', ConversationController::class)->only(['index', 'store', 'show']);
