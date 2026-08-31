<?php

namespace App\Modules\HentleAi\Http\Controllers;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HentleAiCopilotController
{
    public function suggestReply(
        Request $request,
        Conversation $conversation,
        HentleAiCopilotService $copilotService,
    ): JsonResponse {
        $instruction = $request->input('instruction');

        $result = $copilotService->generateReplySuggestion($conversation, $instruction);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function summarize(
        Conversation $conversation,
        HentleAiCopilotService $copilotService,
    ): JsonResponse {
        $result = $copilotService->summarizeTicket($conversation);

        return response()->json([
            'data' => $result,
        ]);
    }
}
