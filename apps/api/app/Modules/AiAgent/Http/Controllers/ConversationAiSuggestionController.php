<?php

namespace App\Modules\AiAgent\Http\Controllers;

use App\Modules\AiAgent\Actions\GenerateReplySuggestionAction;
use App\Modules\AiAgent\Http\Resources\AiAgentRunResource;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationAiSuggestionController
{
    public function store(
        Conversation $conversation,
        GenerateReplySuggestionAction $generateReplySuggestionAction,
    ): JsonResponse {
        abort_unless(request()->user()?->can('view', $conversation), 403);

        $run = $generateReplySuggestionAction->execute(
            request()->user(),
            $conversation,
        );

        return response()->json([
            'data' => (new AiAgentRunResource($run))->resolve(),
            'message' => 'Sugerencia generada correctamente.',
        ]);
    }
}
