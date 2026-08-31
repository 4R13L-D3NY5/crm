<?php

use App\Modules\AiAgent\Http\Controllers\ConversationAiSuggestionController;
use Illuminate\Support\Facades\Route;

Route::post('conversations/{conversation}/ai/reply-suggestion', [ConversationAiSuggestionController::class, 'store']);
