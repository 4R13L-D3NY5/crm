<?php

use App\Modules\HentleAi\Http\Controllers\HentleAiCopilotController;
use App\Modules\HentleAi\Http\Controllers\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;

Route::post('conversations/{conversation}/ai/suggest', [\App\Modules\HentleAi\Http\Controllers\AiCopilotController::class, 'suggest']);
Route::post('conversations/{conversation}/ai/summary', [\App\Modules\HentleAi\Http\Controllers\AiCopilotController::class, 'summary']);
Route::post('conversations/{conversation}/ai/copilot/suggest', [HentleAiCopilotController::class, 'suggestReply']);
Route::get('conversations/{conversation}/ai/copilot/summary', [HentleAiCopilotController::class, 'summarize']);

Route::get('ai/knowledge-bases', [KnowledgeBaseController::class, 'index']);
Route::post('ai/knowledge-bases', [KnowledgeBaseController::class, 'store']);
Route::post('ai/knowledge-bases/{knowledgeBase}/chunks', [KnowledgeBaseController::class, 'addChunk']);
